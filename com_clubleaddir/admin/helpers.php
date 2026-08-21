<?php
/**
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
     * Default inbox used for vacancy enquiries when no per-record override is set.
     *
     * @return string
     */
    public static function vacancyEmail()
    {
        return 'info@simcoecurlingclub.ca';
    }

    /**
     * Render the contact block for a leadership card.
     *
     * Priority:
     *   1. Vacant position  -> an "Apply / Inquire" link (Joomla contact if a
     *      contact_id is set, otherwise a mailto to the vacancy inbox with the
     *      role as the subject).
     *   2. Linked Contact     -> a "Contact" link to the Joomla contact entry.
     *   3. Email / Phone      -> the usual mailto/tel links (or a login lock).
     *
     * Used by both the component view and the module so behaviour stays identical.
     *
     * @param   object  $person             Leadership record (stdClass from store)
     * @param   bool    $showContact        Whether contact details are revealed
     * @param   string  $contactHiddenText   Text shown when contact is hidden
     *
     * @return  string
     */
    /**
     * Resolve the global vacancy-enquiry target (from the component's global
     * Options) into a human-readable string for display in the admin record
     * form. The per-record "Vacancy Enquiry Email" field was removed; the
     * enquiry always follows these global settings.
     *
     * Priority: Joomla Contact (by id) -> default email -> hardcoded club inbox.
     *
     * @return string
     */
    public static function vacancyEnquiryDisplay()
    {
        try {
            $params    = \Joomla\CMS\Component\ComponentHelper::getParams('com_clubleaddir');
            $contactId = (int) $params->get('vacant_contact_id', 0);
            $defaultEm = trim((string) $params->get('vacancy_default_email', ''));
        } catch (\Throwable $e) {
            $contactId = 0;
            $defaultEm = '';
        }

        if ($contactId > 0) {
            $db    = \Joomla\CMS\Factory::getDbo();
            $query = $db->getQuery(true)
                ->select(array('a.name', 'a.email_to'))
                ->from($db->quoteName('#__contact_details', 'a'))
                ->where($db->quoteName('a.id') . ' = ' . (int) $contactId);
            $db->setQuery($query);
            $row = $db->loadObject();
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

    public static function contactHtml($person, $showContact, $contactHiddenText, $vacantContactId = 0, $vacancyDefaultEmail = '')
    {
        $email         = $person->email ?? '';
        $phone         = $person->phone ?? '';
        $contactId     = (int) ($person->contact_id ?? 0);
        $vacant        = (int) ($person->vacant ?? 0);
        $vacancyEmail  = trim($person->vacancy_email ?? '');
        if ($vacancyEmail === '') {
            $vacancyEmail = trim($vacancyDefaultEmail);
        }

        // 1. Vacant position — open the linked Joomla Contact's email
        //    directly (mailto:, no contact page, no prefilled subject).
        //    Falls back to a plain vacancy email when no contact is set.
        if ($vacant === 1) {
            $vacantContact = ($contactId > 0) ? $contactId : (int) $vacantContactId;
            if ($vacantContact > 0) {
                // Blend into the Joomla Contact component: open the email form directly.
                $url   = Route::_('index.php?option=com_contact&view=contact&id=' . $vacantContact . '#display-form');
                $label = Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');
            } else {
                if ($vacancyEmail === '') {
                    return ''; // No Joomla Contact and no Vacancy Default Email configured.
                }
                $url   = 'mailto:' . $vacancyEmail;
                $label = Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');
            }
            return '<div class="clubleadership-card-contact">'
                . '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="clubleadership-contact-link clubleaddir-vacancy-link">'
                . '<span class="icon-mail" aria-hidden="true"></span>'
                . '<span class="clubleadership-contact-text">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>'
                . '</div>';
        }

        // 2. Linked Joomla Contact — this is the single, focused way to reach the
        //    person; email/phone become irrelevant (the contact form covers them).
        if ($contactId > 0) {
            $url = Route::_('index.php?option=com_contact&view=contact&id=' . $contactId);
            return '<div class="clubleadership-card-contact">'
                . '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="clubleadership-contact-link">'
                . '<span class="icon-envelope" aria-hidden="true"></span>'
                . '<span class="clubleadership-contact-text">' . Text::_('COM_CLUBLEADDIR_CONTACT_LINK') . '</span></a>'
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
     * Club logo shown on a card when the position is vacant (no personal photo).
     *
     * @return string
     */
    public static function vacantLogo()
    {
        return 'https://simcoecurlingclub.ca/images/Logo/simcoe_curling_club_logo.svg';
    }

    /**
     * Resolve a Joomla Contact's email address (email_to). Used so a vacant
     * position's CTA opens mailto: directly instead of the contact page.
     *
     * @param int $contactId
     * @return string  email address, or '' if not found
     */
    public static function contactEmail($contactId)
    {
        $contactId = (int) $contactId;
        if ($contactId <= 0) {
            return '';
        }
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('email_to'))
                ->from($db->quoteName('#__contact_details'))
                ->where($db->quoteName('id') . ' = ' . (int) $contactId);
            $db->setQuery($query);
            $email = (string) $db->loadResult();
        } catch (\Throwable $e) {
            $email = '';
        }
        return trim($email);
    }
    /**
     * Render an engaging "we have vacancies — step up!" recruitment banner.
     * Shown at the top of the directory when at least one position is vacant.
     * The CTA opens the Joomla Contact's EMAIL FORM directly (layout=edit),
     * not the contact's profile page; falls back to a mailto: when no
     * contact is configured.
     *
     * @param int    $contactId     Resolved Joomla Contact id for vacant enquiries
     * @param string $defaultEmail  Fallback email when no contact id is set
     * @return string
     */
    public static function vacancyBannerHtml($contactId, $defaultEmail = '')
    {
        $contactId = (int) $contactId;
        if ($contactId > 0) {
            // Blend into the Joomla Contact component: open the email form directly
            // (contact profile above + form anchored via #display-form) - nicer than mailto.
            $url = Route::_('index.php?option=com_contact&view=contact&id=' . $contactId . '#display-form');
            $url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        } else {
            if ($defaultEmail === '') {
                // No Joomla Contact and no Vacancy Default Email configured -> no CTA link.
                $url = '';
            } else {
                $url = 'mailto:' . htmlspecialchars($defaultEmail, ENT_QUOTES, 'UTF-8');
            }
        }

        $cta = '';
        if ($url !== '') {
            $cta = '<a class="clubleaddir-vacancy-banner-cta" href="' . $url . '">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_CTA'), ENT_QUOTES, 'UTF-8') . '</a>';
        }

        return '<div class="clubleaddir-vacancy-banner" role="status">'
            . '<div class="clubleaddir-vacancy-banner-icon" aria-hidden="true">&#128101;</div>'
            . '<div class="clubleaddir-vacancy-banner-body">'
                . '<h3 class="clubleaddir-vacancy-banner-title">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_TITLE'), ENT_QUOTES, 'UTF-8') . '</h3>'
                . '<p class="clubleaddir-vacancy-banner-text">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_BODY'), ENT_QUOTES, 'UTF-8') . '</p>'
            . '</div>'
            . $cta
            . '</div>';
    }
}
