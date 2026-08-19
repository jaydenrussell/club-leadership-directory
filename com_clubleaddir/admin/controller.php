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
        // Force the list view so an empty/missing view param can never fall
        // back to getName() and 404 (name "clubleaddir" with no view).
        $this->input->set('view', 'leaderships');

        return parent::display($cachable, $urlparams);
    }
}
