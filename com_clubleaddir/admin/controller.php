<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Club Leadership admin controller.
 *
 * Standard Joomla dispatcher: the list view ('leaderships') is the default and
 * the edit form ('leadership') is reached via the leadership.add / leadership.edit
 * tasks, which redirect here with view=leadership. No custom view forcing — the
 * framework resolves the view from the request (or default_view) as intended.
 */
class ClubleaddirController extends BaseController
{
	protected $default_view = 'leaderships';
}
