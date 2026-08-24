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
			// Store unavailable — return just the "all" option.
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
		// Already a scheme/absolute URL?
		if (preg_match('#^[a-z]+://#i', $path) || strpos($path, '//') === 0) {
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

		// Photo box only appears when this section's photo toggle is ON. Officers
		// always get a photo slot (real photo or initials); a vacant post shows
		// the club logo; directors/staff only show a box when they have a photo.
		$showPhotoBox = $showPhoto && ($hasPhoto || $isOfficer || $isVacant);

		$nameEmpty   = $isVacant && empty(trim((string) ($person->name ?? '')));
		$displayName = $nameEmpty ? (string) ($person->role ?? '') : (string) ($person->name ?? '');
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

		if (!empty($person->role) && !$nameEmpty) {
			$metaHtml .= '<div class="clubleadership-card-role">' . htmlspecialchars($person->role, ENT_QUOTES, 'UTF-8') . '</div>';
		}

		if (!empty($options['showTerm'])) {
			if (($person->type ?? '') === 'staff') {
				$sy = (int) ($person->start_year ?? 0);
				$ey = (int) ($person->end_year ?? 0);

				if ($sy > 0) {
					$end = $ey > 0 ? (string) $ey : Text::_('MOD_CLUBLEADDIRECTION_EMPLOYED_CURRENT');
					$metaHtml .= '<div class="clubleadership-card-term">' . htmlspecialchars($sy . ' – ' . $end, ENT_QUOTES, 'UTF-8') . '</div>';
				}
			} elseif (!empty($person->term)) {
				$metaHtml .= '<div class="clubleadership-card-term">' . htmlspecialchars($person->term, ENT_QUOTES, 'UTF-8') . '</div>';
			}
		}

		if ($isVacant) {
			$metaHtml .= '<span class="clubleadership-card-vacant">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANT'), ENT_QUOTES, 'UTF-8') . '</span>';
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
		$nameEmpty  = $isVacant && empty(trim((string) ($person->name ?? '')));
		$displayName = $nameEmpty ? (string) ($person->role ?? '') : (string) ($person->name ?? '');

		$metaHtml = '<div class="clubleadership-card-role">' . Text::_('MOD_CLUBLEADDIRECTION_LEAGUE_REP_TITLE') . '</div>';

		if (!empty($person->league_name)) {
			$metaHtml .= '<div class="clubleadership-card-league">' . htmlspecialchars(self::leagueNameLabel($person->league_name), ENT_QUOTES, 'UTF-8') . '</div>';
		}

		if ($isVacant) {
			$metaHtml .= '<span class="clubleadership-card-vacant">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANT'), ENT_QUOTES, 'UTF-8') . '</span>';
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
			return null;
		}
	}

	/**
	 * Build a "stealth" route to a Joomla contact.
	 *
	 * If a published menu item already points at this exact contact, we route
	 * through its Itemid so the URL is a clean SEF alias instead of exposing
	 * index.php?option=com_contact&view=contact&id=N. Otherwise the standard
	 * component route is returned.
	 *
	 * @param   int  $contactId
	 * @return  string
	 */
	public static function contactRoute($contactId)
	{
		$contactId = (int) $contactId;
		if ($contactId <= 0) {
			return '';
		}

		try {
			$db     = Factory::getDbo();
			$query  = $db->getQuery(true)
				->select($db->qn('id'))
				->from($db->qn('#__menu'))
				->where($db->qn('link') . ' = ' . $db->q('index.php?option=com_contact&view=contact&id=' . $contactId))
				->where($db->qn('type') . ' = ' . $db->q('component'))
				->where($db->qn('published') . ' = 1');
			$db->setQuery($query);
			$menuId = (int) $db->loadResult();
			if ($menuId > 0) {
				return Route::_('index.php?Itemid=' . $menuId) . '#display-form';
			}
		} catch (\Throwable $e) {
			// Fall through to the raw component route below.
		}

		return Route::_('index.php?option=com_contact&view=contact&id=' . $contactId . '#display-form');
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
				$name  = self::contactName($vacantContactId);
				$label = $name !== '' ? $name : Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');
			} else {
				$vacancyEmail = trim($vacancyDefaultEmail);
				if ($vacancyEmail !== '') {
					$url   = 'mailto:' . $vacancyEmail;
					$label = Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');
				} else {
					// No enquiry target configured. Still render the button
					// (never silently hide it); point it at the site contacts.
					self::logVacancyMisconfig();
					$url   = Route::_('index.php?option=com_contact&view=featured');
					$label = Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');
				}
			}
			return '<div class="clubleadership-card-contact">'
				. '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="clubleadership-contact-link clubleaddir-vacancy-link">'
				. '<span class="icon-mail" aria-hidden="true"></span>'
				. '<span class="clubleadership-contact-text">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>'
				. '</div>';
		}

		// 2. Linked Joomla Contact — the single, focused way to reach the person.
		if ($contactId > 0) {
			$url   = self::contactRoute($contactId);
			$name  = self::contactName($contactId);
			$label = $name !== '' ? $name : Text::_('COM_CLUBLEADDIR_CONTACT_LINK');
			return '<div class="clubleadership-card-contact">'
				. '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="clubleadership-contact-link">'
				. '<span class="icon-envelope" aria-hidden="true"></span>'
				. '<span class="clubleadership-contact-text">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>'
				. '</div>';
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

		if ($contactId > 0) {
			$url = self::contactRoute($contactId);
			$url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
		} elseif ($defaultEmail !== '') {
			$url = 'mailto:' . htmlspecialchars($defaultEmail, ENT_QUOTES, 'UTF-8');
		} else {
			self::logVacancyMisconfig();
			$url = htmlspecialchars(Route::_('index.php?option=com_contact&view=featured'), ENT_QUOTES, 'UTF-8');
		}

		$cta = '<a class="clubleaddir-vacancy-banner-cta" href="' . $url . '">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_CTA'), ENT_QUOTES, 'UTF-8') . '</a>';

		return '<div class="clubleaddir-vacancy-banner" role="status">'
			. '<div class="clubleaddir-vacancy-banner-icon" aria-hidden="true">&#128101;</div>'
			. '<div class="clubleaddir-vacancy-banner-body">'
				. '<h3 class="clubleaddir-vacancy-banner-title">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_TITLE'), ENT_QUOTES, 'UTF-8') . '</h3>'
				. '<p class="clubleaddir-vacancy-banner-text">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_BODY'), ENT_QUOTES, 'UTF-8') . ' ' . $cta . '</p>'
			. '</div>'
			. '</div>';
	}
}
