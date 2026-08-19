<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleadership
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

class ClubLeadershipHelper extends ContentHelper
{
    public static function addSubmenu($submenu = null)
    {
        HTMLHelper::_('sidebar.addEntry',
            Text::_('COM_CLUBLEADERSHIP_MENU_LEADERSHIP'),
            Route::_('index.php?option=com_clubleadership&view=leaderships'),
            $submenu == 'leaderships'
        );
    }

    public static function getTypeOptions()
    {
        return array(
            ''                => Text::_('COM_CLUBLEADERSHIP_FILTER_ALL_TYPES'),
            'officer'         => Text::_('COM_CLUBLEADERSHIP_TYPE_OFFICER'),
            'director'        => Text::_('COM_CLUBLEADERSHIP_TYPE_DIRECTOR'),
            'director_league' => Text::_('COM_CLUBLEADERSHIP_TYPE_DIRECTOR_LEAGUE'),
            'staff'           => Text::_('COM_CLUBLEADERSHIP_TYPE_STAFF'),
        );
    }

    public static function getPublishedOptions()
    {
        return array(
            ''   => Text::_('COM_CLUBLEADERSHIP_FILTER_ALL_STATUS'),
            '1'  => Text::_('JPUBLISHED'),
            '0'  => Text::_('JUNPUBLISHED'),
        );
    }

    public static function getStatusOptions()
    {
        return array(
            ''         => Text::_('COM_CLUBLEADERSHIP_FILTER_ALL_BOARD'),
            'active'   => Text::_('COM_CLUBLEADERSHIP_STATUS_ACTIVE'),
            'archived' => Text::_('COM_CLUBLEADERSHIP_STATUS_ARCHIVED'),
        );
    }

    public static function getActions()
    {
        $user  = Factory::getUser();
        $canDo = new \Joomla\CMS\Object\CMSObject();

        $canDo->set('core.create',     $user->authorise('core.create', 'com_clubleadership'));
        $canDo->set('core.edit',       $user->authorise('core.edit', 'com_clubleadership'));
        $canDo->set('core.edit.own',   $user->authorise('core.edit.own', 'com_clubleadership'));
        $canDo->set('core.edit.state', $user->authorise('core.edit.state', 'com_clubleadership'));
        $canDo->set('core.delete',     $user->authorise('core.delete', 'com_clubleadership'));
        $canDo->set('core.admin',      $user->authorise('core.admin', 'com_clubleadership'));

        return $canDo;
    }
}
