<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

require_once __DIR__ . '/../store/Store.php';
require_once __DIR__ . '/../helpers.php';

class ClubleaddirModelLeaderships extends BaseDatabaseModel
{
    private $store;

    public function __construct($config = array())
    {
        parent::__construct($config);
        try {
            $this->store = ClubleaddirStore::getInstance();
        } catch (\Throwable $e) {
            $this->store = null;
        }
    }

    public function getItems()
    {
        if ($this->store === null) {
            return array();
        }

        $app = Factory::getApplication();
        $filters = array(
            'type'      => $app->input->get('filter_type', '', 'string'),
            'published' => $app->input->get('filter_published', '', 'string'),
            'status'    => $app->input->get('filter_status', '', 'string'),
            'term'      => $app->input->get('filter_term', '', 'string'),
            'search'    => $app->input->get('filter_search', '', 'string'),
        );

        $items = $this->store->getAll($filters);

        // Backend ordering: default to manual ordering so drag-to-reorder
        // is WYSIWYG.  The role-rank grouping in sortForDisplay() is for
        // front-end display only; the admin list must show the raw
        // ordering field so Save Order / drag actually persists.
        $orderCol  = $app->input->get('filter_order', 'ordering');
        $orderDirn = $app->input->get('filter_order_Dir', 'asc');
        if (!$orderCol) {
            $orderCol = 'ordering';
        }

        $dir = strtoupper($orderDirn) === 'DESC' ? -1 : 1;
        usort($items, function ($a, $b) use ($orderCol, $dir) {
            $av = $this->orderValue($a, $orderCol);
            $bv = $this->orderValue($b, $orderCol);
            if ($av === $bv) {
                // Tie-breaker keeps groups stable (type then name) when
                // ordering values are equal (e.g. all zeros on first use).
                $ta = strcmp($a->type ?? '', $b->type ?? '');
                if ($ta !== 0) {
                    return $ta;
                }
                return strcmp(strtolower($a->name ?? ''), strtolower($b->name ?? ''));
            }
            return ($av < $bv) ? -$dir : $dir;
        });

        foreach ($items as &$item) {
            $item->type_label = $this->getTypeLabel($item->type);
            $item->type_class = 'badge-' . $item->type;
        }

        return $items;
    }

    private function orderValue($item, $col)
    {
        switch ($col) {
            case 'name':
                return strtolower((string) ($item->name ?: ''));
            case 'role':
                return strtolower((string) ($item->role ?: ''));
            case 'type':
                return strtolower((string) ($item->type ?: ''));
            case 'term':
                return strtolower((string) ($item->term ?: ''));
            case 'published':
                return (int) $item->published;
            case 'ordering':
                return (int) $item->ordering;
            default:
                return strtolower((string) ($item->name ?: ''));
        }
    }

    public function getPagination()
    {
        return null;
    }

    public function getFilterValue($key)
    {
        $app = Factory::getApplication();
        switch ($key) {
            case 'type':      return $app->input->get('filter_type', '', 'string');
            case 'published': return $app->input->get('filter_published', '', 'string');
            case 'status':    return $app->input->get('filter_status', '', 'string');
            case 'term':      return $app->input->get('filter_term', '', 'string');
            case 'search':    return $app->input->get('filter_search', '', 'string');
            default:          return '';
        }
    }

    protected function getTypeLabel($type)
    {
        $labels = array(
            'officer'         => Text::_('COM_CLUBLEADDIR_TYPE_OFFICER'),
            'director'        => Text::_('COM_CLUBLEADDIR_TYPE_DIRECTOR'),
            'director_league' => Text::_('COM_CLUBLEADDIR_TYPE_DIRECTOR_LEAGUE'),
            'staff'           => Text::_('COM_CLUBLEADDIR_TYPE_STAFF'),
        );
        return $labels[$type] ?? $type;
    }

    public function getTypeOptions()
    {
        return ClubleaddirHelper::getTypeOptions();
    }

    public function getPublishedOptions()
    {
        return ClubleaddirHelper::getPublishedOptions();
    }

    public function getStatusOptions()
    {
        return ClubleaddirHelper::getStatusOptions();
    }

    public function getTermOptions()
    {
        return ClubleaddirHelper::getTermOptions();
    }

    public function getBackendName()
    {
        return ClubleaddirStore::backendName();
    }
}
