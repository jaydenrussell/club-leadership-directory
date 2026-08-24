<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Site controller. All rendering is read-only; there are no state-changing
 * tasks on the front end.
 */
class ClubleaddirController extends BaseController
{
	protected $default_view = 'leaderships';
}
