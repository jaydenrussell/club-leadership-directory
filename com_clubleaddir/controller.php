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
 * Site controller for com_clubleaddir.
 *
 * The public-facing directory is rendered by mod_clubleaddir; this component
 * entry point exists so the option=com_clubleaddir route resolves instead of
 * 404-ing. The default view redirects to the module-driven display.
 */
class ClubleaddirController extends BaseController
{
	protected $default_view = 'leaderships';
}
