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
}
