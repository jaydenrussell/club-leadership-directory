<?php
/**
 * Shared helper for com_clubleaddir / mod_clubleaddir.
 *
 * Every behaviour option is read from ONE place: the component's global
 * Options (administrator config.xml). Menu items and modules are pure
 * presentation and never carry their own copies of these settings.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/store/Store.php';

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;

// Intentionally NOT extending Joomla\CMS\Helper\ContentHelper: on PHP 8.0 its
// getActions($component = '', $section = '', $id = 0) signature is enforced
// strictly and our parameter-less override fatals with "Declaration ... must be
// compatible". All helper methods are self-contained, so a plain class is used.
class ClubleaddirHelper
{
	public static function addSubmenu($submenu = null)
	{
		HTMLHelper::_('sidebar.addEntry',
			Text::_('COM_CLUBLEADDIR_MENU_LEADERSHIP'),
			Route::_('index.php?option=com_clubleaddir&view=leaderships'),
			$submenu == 'leaderships'
		);
	}

	/**
	 * Return the component global params — THE single source of configuration
	 * for this extension. The front-end view, the module and the admin screens
	 * all resolve their settings through this method.
	 *
	 * @return  \Joomla\CMS\Registry\Registry
	 */
	public static function getGlobalConfig()
	{
		return \Joomla\CMS\Component\ComponentHelper::getParams('com_clubleaddir');
	}

	/**
	 * Resolve every front-end display option from the global config, in one
	 * call, ready to be passed to the card renderers below.
	 *
	 * @return  array
	 */
	public static function displayOptions()
	{
		$cfg = self::getGlobalConfig();

		$photoSize = (int) $cfg->get('photo_size', 120);
		$photoSize = min(320, max(40, $photoSize));

		$headerTag = preg_replace('/[^a-z0-9]/i', '', (string) $cfg->get('header_tag', 'h3'));

		if (!in_array($headerTag, array('h1', 'h2', 'h3', 'h4', 'p', 'div'), true)) {
			$headerTag = 'h3';
		}

		return array(
			'showOfficers'         => (int) $cfg->get('show_officers', 1) === 1,
			'showDirectors'        => (int) $cfg->get('show_directors', 1) === 1,
			'showStaff'            => (int) $cfg->get('show_staff', 1) === 1,

			'showSectionTitles'    => (int) $cfg->get('show_section_titles', 1) === 1,
			'showTerm'             => (int) $cfg->get('show_term', 1) === 1,
			'maxItems'             => max(0, (int) $cfg->get('max_items', 0)),
			'displayTitle'         => trim((string) $cfg->get('display_title', '')),
			'introText'            => trim((string) $cfg->get('intro_text', '')),
			'showPhotosOfficers'   => (int) $cfg->get('show_photos_officers', 1) === 1,
			'showPhotosDirectors'  => (int) $cfg->get('show_photos_directors', 0) === 1,
			'showPhotosStaff'      => (int) $cfg->get('show_photos_staff', 0) === 1,
			'photoSize'            => $photoSize,
			'circular'             => (int) $cfg->get('circular_avatars', 1) === 1,
			'headerTag'            => $headerTag,
			'requireLogin'         => (int) $cfg->get('require_login_for_contact', 1) === 1,
			'contactHiddenText'    => trim((string) $cfg->get('contact_hidden_text', '')),
			'vacantContactId'      => (int) $cfg->get('vacant_contact_id', 0),
			'vacancyDefaultEmail'  => trim((string) $cfg->get('vacancy_default_email', '')),
			'vacancyBannerEnabled' => (int) $cfg->get('vacancy_banner_enabled', 1) === 1,
		);
	}

	/**
	 * Group published records by type and apply the predefined display order.
	 *
	 * @return  array  officers/directors/directors_league/staff arrays
	 */
	public static function getGroupedRoster()
	{
		$store = ClubleaddirStore::getInstance();
		$rows  = $store->getAll(array('published' => 1));

		$groups = array(
			'officers'         => array(),
			'directors'        => array(),
			'directors_league' => array(),
			'staff'            => array(),
		);

		foreach ($rows as $item) {
			switch ($item->type) {
				case 'officer':
					$groups['officers'][] = $item;
					break;
				case 'director':
					$groups['directors'][] = $item;
					break;
				case 'director_league':
					$groups['directors_league'][] = $item;
					break;
				case 'staff':
					$groups['staff'][] = $item;
					break;
			}
		}

		foreach ($groups as $type => $items) {
			$groups[$type] = self::sortForDisplay($items);
		}

		return $groups;
	}

	public static function getTypeOptions()
	{
		return array(
			''                => Text::_('COM_CLUBLEADDIR_FILTER_ALL_TYPES'),
			'officer'         => Text::_('COM_CLUBLEADDIR_TYPE_OFFICER'),
			'director'        => Text::_('COM_CLUBLEADDIR_TYPE_DIRECTOR'),
			'director_league' => Text::_('COM_CLUBLEADDIR_TYPE_DIRECTOR_LEAGUE'),
			'staff'           => Text::_('COM_CLUBLEADDIR_TYPE_STAFF'),
		);
	}

	public static function getPublishedOptions()
	{
		return array(
			''    => Text::_('COM_CLUBLEADDIR_FILTER_ALL_STATUS'),
			'1'   => Text::_('JPUBLISHED'),
			'0'   => Text::_('JUNPUBLISHED'),
			'-2'  => Text::_('JTRASHED'),
		);
	}

	public static function getStatusOptions()
	{
		return array(
			''         => Text::_('COM_CLUBLEADDIR_FILTER_ALL_BOARD'),
			'active'   => Text::_('COM_CLUBLEADDIR_STATUS_ACTIVE'),
			'archived' => Text::_('COM_CLUBLEADDIR_STATUS_ARCHIVED'),
		);
	}

	/**
	 * Build a filter dropdown of distinct terms (e.g. 2025-2027) present in the store.
	 *
	 * @return array
	 */
	public static function getTermOptions()
	{
		$options = array('' => Text::_('COM_CLUBLEADDIR_FILTER_ALL_TERMS'));
		try {
			$store = ClubleaddirStore::getInstance();
			$rows  = $store->getAll(array());
			$seen  = array();
			foreach ($rows as $row) {
				$term = is_object($row) ? ($row->term ?? '') : ($row['term'] ?? '');
				$term = trim((string) $term);
				if ($term !== '' && !isset($seen[$term])) {
					$seen[$term]  = true;
					$options[$term] = $term;
				}
			}
		} catch (\Throwable $e) {
			error_log('Clubleaddir getTypeOptions failed: ' . $e->getMessage());
		}
		return $options;
	}

	/**
	 * Normalise a stored photo path to a root-absolute URL path so it resolves
	 * correctly from both the administrator and site front end. Stored values
	 * may be "images/..." (no leading slash, legacy) or "/images/..." (current).
	 *
	 * @param   string  $path
	 * @return  string
	 */
	public static function photoUrl($path)
	{
		$path = (string) $path;
		if ($path === '') {
			return '';
		}
		if ($path[0] === '/') {
			return $path;
		}
		// Already a scheme/absolute URL? Only http(s) allowed — reject javascript:, data:, etc.
		if (preg_match('#^https?://#i', $path) || strpos($path, '//') === 0) {
			return $path;
		}
		return '/' . ltrim($path, '/');
	}

	/**
	 * Predefined display order for front-end grouping.
	 *
	 *  - Officers: President/Chair -> Vice President -> Secretary -> Treasurer,
	 *    then by manual ordering, then name.
	 *  - Staff:    Head Ice Technician -> Assistant Ice Technician -> others,
	 *    then by manual ordering, then name.
	 *  - Directors / League Directors: manual ordering only, then name.
	 *
	 * The admin "Ordering" field remains editable and acts as the tie-breaker
	 * after the predefined role rank, so the predefined sequence can still be
	 * fine-tuned per group.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	public static function sortForDisplay(array $items)
	{
		$officerRank = array(
			'president'         => 10,
			'chair'             => 10,
			'chairperson'       => 10,
			'vice president'    => 20,
			'vice-president'    => 20,
			'vp'                => 20,
			'secretary'         => 30,
			'treasurer'         => 40,
		);
		$staffRank = array(
			'head ice technician'     => 10,
			'head icetechnician'      => 10,
			'assistant ice technician' => 20,
			'assistant icetechnician'  => 20,
		);

		usort($items, function ($a, $b) use ($officerRank, $staffRank) {
			$typeA = $a->type ?? '';
			$typeB = $b->type ?? '';
			if ($typeA !== $typeB) {
				// Keep the existing grouping order (officers, directors, staff).
				return strcmp($typeA, $typeB);
			}

			$roleA = strtolower(trim($a->role ?? ''));
			$roleB = strtolower(trim($b->role ?? ''));

			if ($typeA === 'officer') {
				$ra = $officerRank[$roleA] ?? 90;
				$rb = $officerRank[$roleB] ?? 90;
			} elseif ($typeA === 'staff') {
				$ra = $staffRank[$roleA] ?? 50;
				$rb = $staffRank[$roleB] ?? 50;
			} else {
				// Directors / league directors: no fixed rank — fall back to
				// role (alphabetical) so equal-ordering rows sort predictably.
				$ra = 0;
				$rb = 0;
			}

			if ($ra !== $rb) {
				return $ra <=> $rb;
			}

			$ordA = (int) ($a->ordering ?? 0);
			$ordB = (int) ($b->ordering ?? 0);
			if ($ordA !== $ordB) {
				return $ordA <=> $ordB;
			}

			// Equal ordering (directors/league): sort by role then name.
			$roleCmp = strcmp($roleA, $roleB);
			if ($roleCmp !== 0) {
				return $roleCmp;
			}

			return strcmp(strtolower($a->name ?? ''), strtolower($b->name ?? ''));
		});

		return $items;
	}

	public static function getActions()
	{
		$user  = Factory::getUser();
		$canDo = new \Joomla\CMS\Object\CMSObject();

		$canDo->set('core.create',     $user->authorise('core.create', 'com_clubleaddir'));
		$canDo->set('core.edit',       $user->authorise('core.edit', 'com_clubleaddir'));
		$canDo->set('core.edit.own',   $user->authorise('core.edit.own', 'com_clubleaddir'));
		$canDo->set('core.edit.state', $user->authorise('core.edit.state', 'com_clubleaddir'));
		$canDo->set('core.delete',     $user->authorise('core.delete', 'com_clubleaddir'));
		$canDo->set('core.admin',      $user->authorise('core.admin', 'com_clubleaddir'));

		return $canDo;
	}

	/**
	 * Render one leadership card (officer / director / staff). Used verbatim by
	 * BOTH the component site view and the module template so they can never
	 * drift apart visually.
	 *
	 * @param   object  $person   Record from the store
	 * @param   array   $options  Display options; 'showPhoto' controls this card's photo visibility
	 * @return  string
	 */
	public static function cardHtml($person, array $options)
	{
		$showPhoto = !empty($options['showPhoto']);
		$hasPhoto  = !empty($person->photo);
		$isOfficer = (($person->type ?? '') === 'officer');
		$isVacant  = !empty($person->vacant);
		$circular  = empty($options['circular']) ? false : true;

		// Photo box only for officers/directors with photos — vacant cards are compact text-only with shaded background (no logo image).
		$showPhotoBox = $showPhoto && ($hasPhoto || $isOfficer);

		// Vacant: show "Position is Vacant" in the name slot. Backend still stores real name for admin access.
		if ($isVacant) {
			$displayName = Text::_('COM_CLUBLEADDIR_POSITION_VACANT');
		} else {
			$displayName = (string) ($person->name ?? '');
		}
		$size        = (int) ($options['photoSize'] ?? 120);
		$logoSize    = (int) round($size * 0.75);
		$boxSize     = $isVacant ? $logoSize : $size;

		$photoHtml = '';

		if ($showPhotoBox) {
			$shapeClass = $circular ? 'is-circular' : 'is-rect';
			$photoHtml  = '<div class="clubleadership-card-photo ' . $shapeClass . ' is-visible" style="width:' . $boxSize . 'px;height:' . $boxSize . 'px;">';

			// Circular avatar uses the square crop; non-circular shows the original.
			$src = $hasPhoto
				? ($circular && !empty($person->photo) ? $person->photo : (!empty($person->photo_full) ? $person->photo_full : $person->photo))
				: '';

			if ($src !== '') {
				$photoHtml .= '<img src="' . htmlspecialchars(self::photoUrl($src), ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy" width="' . $boxSize . '" height="' . $boxSize . '">';
			} elseif ($isVacant) {
				$photoHtml .= '<img src="' . htmlspecialchars(self::vacantLogo(), ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy" width="' . $logoSize . '" height="' . $logoSize . '" style="object-fit:contain;background:#fff;">';
			} elseif ($isOfficer) {
				$photoHtml .= '<div class="clubleadership-card-photo--initials">' . htmlspecialchars(self::initials($displayName), ENT_QUOTES, 'UTF-8') . '</div>';
			}

			$photoHtml .= '</div>';
		}

		$metaHtml = '';

		if (!empty($person->role)) {
			$metaHtml .= '<div class="clubleadership-card-role">' . htmlspecialchars($person->role, ENT_QUOTES, 'UTF-8') . '</div>';
		}

		if (!empty($options['showTerm'])) {
			if (($person->type ?? '') === 'staff') {
				$sy = (int) ($person->start_year ?? 0);
				$ey = (int) ($person->end_year ?? 0);

				if ($sy > 0) {
					$end = $ey > 0 ? (string) $ey : Text::_('MOD_CLUBLEADDIR_EMPLOYED_CURRENT');
					$metaHtml .= '<div class="clubleadership-card-term">' . htmlspecialchars($sy . ' – ' . $end, ENT_QUOTES, 'UTF-8') . '</div>';
				}
			} elseif (!empty($person->term)) {
				$metaHtml .= '<div class="clubleadership-card-term">' . htmlspecialchars($person->term, ENT_QUOTES, 'UTF-8') . '</div>';
			}
		}

		$contactHtml = self::contactHtml(
			$person,
			!empty($options['showContact']),
			(string) ($options['contactHiddenText'] ?? ''),
			(int) ($options['vacantContactId'] ?? 0),
			(string) ($options['vacancyDefaultEmail'] ?? '')
		);

		return '<article class="clubleadership-card clubleaddir-card--' . htmlspecialchars($person->type ?? '', ENT_QUOTES, 'UTF-8') . ($isVacant ? ' clubleaddir-card--vacant' : '') . '">'
			. $photoHtml
			. '<div class="clubleadership-card-content">'
			. '<h4 class="clubleadership-card-name">' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '</h4>'
			. $metaHtml
			. $contactHtml
			. '</div>'
			. '</article>';
	}

	/**
	 * Render a league-appointed director card (no photo slot).
	 *
	 * @param   object  $person   Record from the store
	 * @param   array   $options  Resolved display options (see displayOptions)
	 * @return  string
	 */
	public static function leagueCardHtml($person, array $options)
	{
		$isVacant   = !empty($person->vacant);
		if ($isVacant) {
			$displayName = Text::_('COM_CLUBLEADDIR_POSITION_VACANT');
		} else {
			$displayName = (string) ($person->name ?? '');
		}

		$metaHtml = '<div class="clubleadership-card-role">' . Text::_('MOD_CLUBLEADDIR_LEAGUE_REP_TITLE') . '</div>';

		if (!empty($person->league_name)) {
			$metaHtml .= '<div class="clubleadership-card-league">' . htmlspecialchars(self::leagueNameLabel($person->league_name), ENT_QUOTES, 'UTF-8') . '</div>';
		}

		$contactHtml = self::contactHtml(
			$person,
			!empty($options['showContact']),
			(string) ($options['contactHiddenText'] ?? ''),
			(int) ($options['vacantContactId'] ?? 0),
			(string) ($options['vacancyDefaultEmail'] ?? '')
		);

		return '<article class="clubleadership-card clubleaddir-card--director' . ($isVacant ? ' clubleaddir-card--vacant' : '') . '">'
			. '<div class="clubleadership-card-photo"></div>'
			. '<div class="clubleadership-card-content">'
			. '<h4 class="clubleadership-card-name">' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '</h4>'
			. $metaHtml
			. $contactHtml
			. '</div>'
			. '</article>';
	}

	/**
	 * True when at least one record in the grouped roster is vacant.
	 *
	 * @param   array  $groups  Result of getGroupedRoster()
	 * @return  boolean
	 */
	public static function hasVacancies(array $groups)
	{
		foreach ($groups as $items) {
			foreach ($items as $person) {
				if (!empty($person->vacant)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Two-letter initials for the placeholder avatar.
	 *
	 * @param   string  $name
	 * @return  string
	 */
	public static function initials($name)
	{
		$parts = explode(' ', trim((string) $name));

		if ($parts[0] === '') {
			return '';
		}

		if (count($parts) >= 2) {
			return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
		}

		return strtoupper(mb_substr($parts[0], 0, 2));
	}

	/**
	 * Resolve the global vacancy-enquiry target into a human-readable string
	 * for display in the admin record form. Reads ONLY the component Options.
	 *
	 * @return string
	 */
	public static function vacancyEnquiryDisplay()
	{
		$cfg       = self::getGlobalConfig();
		$contactId = (int) $cfg->get('vacant_contact_id', 0);
		$defaultEm = trim((string) $cfg->get('vacancy_default_email', ''));

		if ($contactId > 0) {
			$row = self::contactRow($contactId);

			if ($row) {
				$email = trim($row->email_to ?? '');
				return Text::sprintf('COM_CLUBLEADDIR_VACANCY_USES_CONTACT', $row->name, $email);
			}
		}

		if ($defaultEm !== '') {
			return Text::sprintf('COM_CLUBLEADDIR_VACANCY_USES_EMAIL', $defaultEm);
		}

		return Text::_('COM_CLUBLEADDIR_VACANCY_USES_NONE');
	}

	/**
	 * Load a #__contact_details row (id, name, email_to).
	 *
	 * @param   int         $contactId
	 * @return  object|null
	 */
	protected static function contactRow($contactId)
	{
		$contactId = (int) $contactId;

		if ($contactId <= 0) {
			return null;
		}

		try {
			$db    = Factory::getDbo();
			$query = $db->getQuery(true)
				->select(array('a.id', 'a.name', 'a.email_to'))
				->from($db->quoteName('#__contact_details', 'a'))
				->where($db->quoteName('a.id') . ' = ' . $contactId);
			$db->setQuery($query);

			return $db->loadObject();
		} catch (\Throwable $e) {
			error_log('Clubleaddir contactRoute failed: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Build a route to a Joomla contact page.
	 *
	 * The lookup tries progressively looser menu-item matches so that the
	 * contact form page opens correctly whether the site uses legacy IDs,
	 * full SEF routing, or a mix.  mailto: is the last resort.
	 *
	 * @param   int  $contactId
	 * @return  string  URL or '' if nothing works.
	 */
	public static function contactRoute($contactId)
	{
		$contactId = (int) $contactId;
		if ($contactId <= 0) {
			return '';
		}

		try {
			$db = Factory::getDbo();

			// Fetch the contact row with fields needed for Joomla's own
			// published / access / language / category checks. If any of
			// these would cause com_contact to raise a 404, we bail out
			// here and return '' so the caller falls through to mailto:/
			// plain email instead of generating a broken link.
			$query = $db->getQuery(true)
				->select(array(
					$db->qn('c.id'),
					$db->qn('c.catid'),
					$db->qn('c.email_to'),
					$db->qn('c.alias'),
					$db->qn('c.access'),
					$db->qn('c.language'),
					$db->qn('c.publish_up'),
					$db->qn('c.publish_down'),
				))
				->from($db->qn('#__contact_details', 'c'))
				->where($db->qn('c.id') . ' = ' . $contactId)
				->where($db->qn('c.published') . ' = 1');
			$db->setQuery($query);
			$row = $db->loadObject();
			if (!$row) {
				return '';
			}

			// Category must be published too — com_contact checks this and
			// raises 404 when the category is unpublished/archived.
			if ((int) $row->catid > 0) {
				$catQ = $db->getQuery(true)
					->select(array($db->qn('published'), $db->qn('access'), $db->qn('language')))
					->from($db->qn('#__categories'))
					->where($db->qn('id') . ' = ' . (int) $row->catid)
					->where($db->qn('extension') . ' = ' . $db->q('com_contact'));
				$db->setQuery($catQ);
				$cat = $db->loadObject();
				if ($cat) {
					if ((int) $cat->published !== 1) {
						return '';
					}
					// Respect category access / language the same as the contact.
					if ((int) $cat->access !== 0) {
						try {
							$user = Factory::getUser();
							if (!in_array((int) $cat->access, $user->getAuthorisedViewLevels(), true)) {
								return '';
							}
						} catch (\Throwable $e) { /* ignore */ }
					}
				}
			}

			// Contact access level — must be viewable by the current user
			// or the frontend will 404 for guests.
			if ((int) $row->access !== 0) {
				try {
					$user = Factory::getUser();
					if (!in_array((int) $row->access, $user->getAuthorisedViewLevels(), true)) {
						return '';
					}
				} catch (\Throwable $e) { /* ignore — best effort */ }
			}

			// Publish window — com_contact honours publish_up/down.
			$now = Factory::getDate()->toSql();
			if (!empty($row->publish_up) && $row->publish_up !== '0000-00-00 00:00:00' && $row->publish_up > $now) {
				return '';
			}
			if (!empty($row->publish_down) && $row->publish_down !== '0000-00-00 00:00:00' && $row->publish_down < $now) {
				return '';
			}

			// SEF-compatible routing.
			// For SEF to produce a pretty URL like /contacts/officers/president
			// Joomla needs a menu Itemid for com_contact to use as routing
			// context. We mirror what ContactHelperRoute does but ensure we
			// never fall back to home Itemid 101 (com_content) which caused
			// the bad /component/contact/contact/president?catid=8&Itemid=101.
			$catid = (int) $row->catid;
			$lang  = isset($row->language) ? (string) $row->language : '';

			// Try Joomla's own helper first — it already handles SEF,
			// modern vs legacy, and ID-in-URL. It returns a non-SEF route
			// like index.php?option=com_contact&view=contact&id=2&catid=8&Itemid=XX
			// with the correct alias/Itemid when a suitable menu exists.
			$helperRoute = null;
			try {
				$j3h = JPATH_SITE . '/components/com_contact/helpers/route.php';
				if (is_file($j3h)) { require_once $j3h; }
				if (class_exists('ContactHelperRoute', false)) {
					$helperRoute = ContactHelperRoute::getContactRoute($contactId, $catid, $lang);
				} elseif (class_exists('\\Joomla\\Component\\Contact\\Site\\Helper\\RouteHelper')) {
					$helperRoute = \Joomla\Component\Contact\Site\Helper\RouteHelper::getContactRoute($contactId, $catid ?: null, $lang ?: null);
				}
			} catch (\Throwable $e) { $helperRoute = null; }

			if (is_string($helperRoute) && $helperRoute !== '') {
				// If helper injected home 101, ignore it — it is not a
				// com_contact menu and will generate the bad component URL.
				if (strpos($helperRoute, 'Itemid=101') === false) {
					try { return Route::_($helperRoute); } catch (\Throwable $e) { /* fallback below */ }
				}
			}

			// Manual fallback: find a real com_contact menu item. Order:
			// 1) exact contact, 2) its category, 3) any com_contact menu.
			$menuId = 0;
			$q = $db->getQuery(true)
				->select($db->qn('id'))
				->from($db->qn('#__menu'))
				->where($db->qn('link') . ' = ' . $db->q('index.php?option=com_contact&view=contact&id=' . $contactId))
				->where($db->qn('type') . ' = ' . $db->q('component'))
				->where($db->qn('published') . ' = 1');
			$db->setQuery($q);
			$menuId = (int) $db->loadResult();

			if ($menuId === 0 && $catid > 0) {
				$q = $db->getQuery(true)
					->select($db->qn('id'))
					->from($db->qn('#__menu'))
					->where($db->qn('link') . ' = ' . $db->q('index.php?option=com_contact&view=category&id=' . $catid))
					->where($db->qn('type') . ' = ' . $db->q('component'))
					->where($db->qn('published') . ' = 1');
				$db->setQuery($q);
				$menuId = (int) $db->loadResult();
			}
			if ($menuId === 0) {
				$q = $db->getQuery(true)
					->select($db->qn('id'))
					->from($db->qn('#__menu'))
					->where($db->qn('link') . ' LIKE ' . $db->q('%option=com_contact%'))
					->where($db->qn('type') . ' = ' . $db->q('component'))
					->where($db->qn('published') . ' = 1')
					->order($db->qn('id') . ' ASC');
				$db->setQuery($q, 0, 1);
				$menuId = (int) $db->loadResult();
				// Defensively reject home 101 if it somehow is com_contact.
				if ($menuId === 101) { $menuId = 0; }
			}
			if ($menuId > 0) {
				$raw = 'index.php?option=com_contact&view=contact&id=' . $contactId;
				if ($catid > 0) { $raw .= '&catid=' . $catid; }
				$raw .= '&Itemid=' . $menuId;
				return Route::_($raw);
			}

			// No com_contact menu at all — return a raw non-SEF URL that
			// is guaranteed to load the contact form regardless of SEF or
			// modern/legacy/ID settings (your working example
			// index.php?option=com_contact&view=contact&id=7&catid=11).
			// This is the safe fallback; create a hidden com_contact Category
			// menu item to get pretty SEF URLs instead of this.
			if ($catid > 0) {
				return 'index.php?option=com_contact&view=contact&id=' . $contactId . '&catid=' . $catid;
			}
			return 'index.php?option=com_contact&view=contact&id=' . $contactId;
		} catch (\Throwable $e) {
			error_log('Clubleaddir photoUrl failed: ' . $e->getMessage());
		}

		return '';
	}

	/**
	 * Fetch a Joomla contact's display name (used as the link text so the URL
	 * itself stays hidden while the visible label shows who you're reaching).
	 *
	 * @param   int  $contactId
	 * @return  string
	 */
	public static function contactName($contactId)
	{
		$row = self::contactRow($contactId);

		return $row ? (string) $row->name : '';
	}

	/**
	 * Resolve a Joomla Contact's email address (email_to).
	 *
	 * @param int $contactId
	 * @return string  email address, or '' if not found
	 */
	public static function contactEmail($contactId)
	{
		$row = self::contactRow($contactId);

		return $row ? trim((string) $row->email_to) : '';
	}

	public static function contactHtml($person, $showContact, $contactHiddenText, $vacantContactId = 0, $vacancyDefaultEmail = '')
	{
		$email         = $person->email ?? '';
		$phone         = $person->phone ?? '';
		$contactId     = (int) ($person->contact_id ?? 0);
		$vacant        = (int) ($person->vacant ?? 0);

		// 1. Vacant position — uses ONLY the Vacant Enquiry Contact (component
		//    Options). When that is 0, the Vacancy Default Email is used. A
		//    vacant post never consults a record-level contact_id.
		if ($vacant === 1) {
			$vacantContactId = (int) $vacantContactId;
			if ($vacantContactId > 0) {
				$url   = self::contactRoute($vacantContactId);
			} else {
				$vacancyEmail = trim($vacancyDefaultEmail);
				if ($vacancyEmail !== '') {
					$url   = 'mailto:' . $vacancyEmail;
				} else {
					// No enquiry target configured. Still render the button
					// (never silently hide it); point it at the site contacts.
					self::logVacancyMisconfig();
					$url   = Route::_('index.php?option=com_contact&view=featured');
				}
			}
			return '<div class="clubleadership-card-contact">'
				. '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="clubleadership-contact-link clubleaddir-vacancy-link">'
				. '<span class="icon-mail" aria-hidden="true"></span>'
				. '<span class="clubleadership-contact-text">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE'), ENT_QUOTES, 'UTF-8') . '</span></a>'
				. '</div>';
		}

		// 2. Linked Joomla Contact — the single, focused way to reach the person.
		if ($contactId > 0) {
			$url   = self::contactRoute($contactId);
			if ($url !== '') {
				$label = Text::_('COM_CLUBLEADDIR_CONTACT_LINK');
				return '<div class="clubleadership-card-contact">'
					. '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="clubleadership-contact-link">'
					. '<span class="icon-envelope" aria-hidden="true"></span>'
					. '<span class="clubleadership-contact-text">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>'
					. '</div>';
			}
			// Contact was linked but doesn't exist or is unpublished — fall through to email/phone.
		}

		// 3. Plain email / phone.
		if (empty($email) && empty($phone)) {
			return '';
		}

		$html = '<div class="clubleadership-card-contact">';
		if ($showContact) {
			if (!empty($email)) {
				$html .= '<a href="mailto:' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '" class="clubleadership-contact-link">'
					. '<span class="icon-envelope" aria-hidden="true"></span>'
					. '<span class="clubleadership-contact-text">Email</span></a>';
			}
			if (!empty($phone)) {
				$html .= '<a href="tel:' . htmlspecialchars(preg_replace('/[^0-9+]/', '', $phone), ENT_QUOTES, 'UTF-8') . '" class="clubleadership-contact-link">'
					. '<span class="icon-phone" aria-hidden="true"></span>'
					. '<span class="clubleadership-contact-text">' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</span></a>';
			}
		} else {
			$html .= '<span class="clubleadership-contact-hidden">'
				. '<span class="icon-lock" aria-hidden="true"></span> '
				. htmlspecialchars($contactHiddenText, ENT_QUOTES, 'UTF-8') . '</span>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Log a backend warning when a vacant position has no enquiry target
	 * configured. Never fatals; goes to Joomla's standard logging category.
	 */
	protected static function logVacancyMisconfig()
	{
		try {
			Log::add('Club Leadership Directory: a vacant position is published but no Vacant Enquiry Contact or Vacancy Default Email is configured. Set one in Component Options.', Log::WARNING, 'com_clubleaddir');
		} catch (\Throwable $e) {
			// Logging is best-effort; never fatal.
		}
	}

	/**
	 * Club placeholder shown on a card when the position is vacant and no
	 * photo exists. Configurable via Component Options ("Vacant Logo URL");
	 * falls back to a bundled generic silhouette asset.
	 *
	 * @return string
	 */
	public static function vacantLogo()
	{
		$custom = trim((string) self::getGlobalConfig()->get('vacant_logo_url', ''));

		if ($custom !== '') {
			$lower = strtolower($custom);
			if (preg_match('#^(https?|ftp|file|javascript|data|vbscript|ldap|gopher):#i', $lower)) {
				return '/media/com_clubleaddir/images/vacant-person.svg';
			}
			if (strpos($lower, '//') === 0) {
				return '/media/com_clubleaddir/images/vacant-person.svg';
			}
			return self::photoUrl($custom);
		}

		return '/media/com_clubleaddir/images/vacant-person.svg';
	}

	/**
	 * Resolve a stored league Name KEY (e.g. 'senior_men') to its translatable
	 * human label for display.
	 *
	 * @param string $key
	 * @return string
	 */
	public static function leagueNameLabel($key)
	{
		static $map = null;

		if ($map === null) {
			$map = array(
				'day_ladies'     => Text::_('COM_CLUBLEADDIR_LEAGUE_DAY_LADIES'),
				'evening_ladies' => Text::_('COM_CLUBLEADDIR_LEAGUE_EVENING_LADIES'),
				'senior_men'     => Text::_('COM_CLUBLEADDIR_LEAGUE_SENIOR_MEN'),
			);
		}

		$key = (string) $key;

		return isset($map[$key]) ? $map[$key] : $key;
	}

	/**
	 * Render the "we have vacancies — step up!" recruitment banner. Shown when
	 * at least one position is vacant. The CTA routes to the configured Joomla
	 * Contact (stealth route when an alias exists), or mailto fallback.
	 *
	 * @param int    $contactId     Resolved Joomla Contact id for vacant enquiries
	 * @param string $defaultEmail  Fallback email when no contact id is set
	 * @return string
	 */
	public static function vacancyBannerHtml($contactId, $defaultEmail = '')
	{
		$contactId = (int) $contactId;
		$cfg       = self::getGlobalConfig();

		if ($contactId > 0) {
			$url = self::contactRoute($contactId);
			if ($url !== '') {
				$url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
			}
		}

		if (empty($url) && $defaultEmail !== '') {
			$url = 'mailto:' . htmlspecialchars($defaultEmail, ENT_QUOTES, 'UTF-8');
		}

		if (empty($url)) {
			self::logVacancyMisconfig();
			$url = htmlspecialchars(Route::_('index.php?option=com_contact&view=featured'), ENT_QUOTES, 'UTF-8');
		}

		$title   = trim((string) $cfg->get('vacancy_banner_title', Text::_('COM_CLUBLEADDIR_VACANCIES_TITLE')));
		$body    = trim((string) $cfg->get('vacancy_banner_text', Text::_('COM_CLUBLEADDIR_VACANCIES_BODY')));
		$summary = trim((string) $cfg->get('vacancy_banner_summary', ''));

		$cta = '<a class="clubleaddir-vacancy-banner-cta" href="' . $url . '">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_INQUIRE'), ENT_QUOTES, 'UTF-8') . '</a>';

		$summaryHtml = '';
		if ($summary !== '') {
			$summaryHtml = '<p class="clubleaddir-vacancy-banner-summary">' . htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') . '</p>';
		}

		return '<div class="clubleaddir-vacancy-banner" role="status">'
			. '<div class="clubleaddir-vacancy-banner-icon" aria-hidden="true"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M5 20c0-4 3-7 7-7s7 3 7 7"/><path d="M16 3l4 4m0-4l-4 4"/></svg></div>'
			. '<div class="clubleaddir-vacancy-banner-body">'
				. '<h3 class="clubleaddir-vacancy-banner-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>'
				. '<p class="clubleaddir-vacancy-banner-text">' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</p>'
				. $summaryHtml
			. '</div>'
			. '<div class="clubleaddir-vacancy-banner-actions">' . $cta . '</div>'
			. '</div>';
	}
}
