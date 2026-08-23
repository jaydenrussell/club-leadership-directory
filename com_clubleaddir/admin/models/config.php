<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Factory;

class ClubleaddirModelConfig extends FormModel
{
	protected $text = 'COM_CLUBLEADDIR';

	public function getForm($data = array(), $loadData = true)
	{
		$form = $this->loadForm($this->text, 'config', array('load_data' => $loadData));
		if (empty($form))
		{
			return null;
		}
		return $form;
	}

	protected function loadFormData()
	{
		$params = Factory::getApplication()->getParams('com_clubleaddir');
		return (object) $params->toArray();
	}
}
