<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

class ClubleaddirController extends BaseController
{
	protected $default_view = 'leaderships';

	public function save($key = null, $urlVar = null)
	{
		$model = $this->getModel('config');
		$form = $model->getForm();
		$data = $this->input->post->get('jform', array(), 'array');

		if (!$model->save($data))
		{
			$this->setError($model->getError());
			$this->setMessage($this->getError(), 'error');

			$view = $this->getView('config', 'html');
			$view->setModel($model, true);
			$view->display();
			return false;
		}

		$this->setMessage(JText::_('COM_CLUBLEADDIR_CONFIG_SAVED'));
		$this->setRedirect(JRoute::_('index.php?option=com_clubleaddir&view=config', false));
	}
}
