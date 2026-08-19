<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

class ClubleaddirController extends BaseController
{
    protected $default_view = 'leaderships';

    public function display($cachable = false, $urlparams = array())
    {
        $view = Factory::getApplication()->input->get('view', 'leaderships');
        Factory::getApplication()->input->set('view', $view);
        parent::display($cachable, $urlparams);
    }
}
