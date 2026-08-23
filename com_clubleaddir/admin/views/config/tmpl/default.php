<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
?>
<div class="row">
	<div class="col-md-12">
		<form action="<?php echo JRoute::_('index.php?option=com_clubleaddir&view=config'); ?>" method="post" name="adminForm" id="adminForm">
			<?php echo $this->form->renderFieldset('display'); ?>
			<?php echo $this->form->renderFieldset('contact'); ?>
			<?php echo $this->form->renderFieldset('vacancy'); ?>
			<input type="hidden" name="task" value="config.save" />
			<?php echo JHtml::_('form.token'); ?>
		</form>
	</div>
</div>
