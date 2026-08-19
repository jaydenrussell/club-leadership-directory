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

require_once __DIR__ . '/../store/Store.php';

class ClubleaddirModelLeaderships
{
    private $store;

    public function __construct()
    {
        $this->store = ClubleaddirStore::getInstance();
    }

    public function getItems()
    {
        $app = Factory::getApplication();
        $filters = array(
            'type'      => $app->input->get('filter_type', '', 'string'),
            'published' => $app->input->get('filter_published', '', 'string'),
            'status'    => $app->input->get('filter_status', '', 'string'),
            'search'    => $app->input->get('filter_search', '', 'string'),
        );

        $items = $this->store->getAll($filters);

        foreach ($items as &$item) {
            $item->type_label = $this->getTypeLabel($item->type);
            $item->type_class = 'badge-' . $item->type;
        }

        return $items;
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
            case 'search':    return $app->input->get('filter_search', '', 'string');
            default:          return '';
        }
    }

    protected function getTypeLabel($type)
    {
        $labels = array(
            'officer'         => Text::_('COM_CLUBLEADDIRECTION_TYPE_OFFICER'),
            'director'        => Text::_('COM_CLUBLEADDIRECTION_TYPE_DIRECTOR'),
            'director_league' => Text::_('COM_CLUBLEADDIRECTION_TYPE_DIRECTOR_LEAGUE'),
            'staff'           => Text::_('COM_CLUBLEADDIRECTION_TYPE_STAFF'),
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

    public function getBackendName()
    {
        return ClubleaddirStore::backendName();
    }
}
