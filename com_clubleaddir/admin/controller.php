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
        // Respect an explicit view (e.g. the edit form redirected to by
        // leadership.add / leadership.edit), but default to the list when no
        // view is supplied (the admin menu link). Do NOT hard-code the view
        // here — that would override the edit redirect and re-render the list.
        $view = $this->input->get('view', $this->default_view, 'cmd');
        $this->input->set('view', $view);

        return parent::display($cachable, $urlparams);
    }
}
