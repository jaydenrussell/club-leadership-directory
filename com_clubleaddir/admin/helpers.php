<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

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
            ''   => Text::_('COM_CLUBLEADDIR_FILTER_ALL_STATUS'),
            '1'  => Text::_('JPUBLISHED'),
            '0'  => Text::_('JUNPUBLISHED'),
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
