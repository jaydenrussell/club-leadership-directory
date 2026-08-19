<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleadership
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.core');

$typeFilter        = $this->filters['type'];
$publishedFilter   = $this->filters['published'];
$statusFilter      = $this->filters['status'];
$search            = $this->filters['search'];
?>

<div class="clubleadership-backend-note alert alert-info" style="margin-bottom:1rem;">
    <?php echo Text::sprintf('COM_CLUBLEADERSHIP_BACKEND_NOTE', $this->escape($this->backendName)); ?>
</div>

<form action="<?php echo Route::_('index.php?option=com_clubleadership'); ?>" method="post" name="adminForm" id="adminForm">

    <div class="row">
        <div class="col-md-6">
            <div class="form-inline mb-3">
                <div class="input-group">
                    <input type="text" name="filter_search" id="filter_search" class="form-control"
                           placeholder="<?php echo Text::_('COM_CLUBLEADERSHIP_SEARCH_PLACEHOLDER'); ?>"
                           value="<?php echo $this->escape($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <span class="icon-search" aria-hidden="true"></span>
                    </button>
                    <?php if ($search): ?>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('filter_search').value=''; this.form.submit();">
                        <span class="icon-x" aria-hidden="true"></span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-inline mb-3 justify-content-md-end">
                <select name="filter_type" id="filter_type" class="form-select ml-2" onchange="this.form.submit();">
                    <?php foreach ($this->typeOptions as $value => $label): ?>
                    <option value="<?php echo $value; ?>" <?php echo $typeFilter === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="filter_published" id="filter_published" class="form-select ml-2" onchange="this.form.submit();">
                    <?php foreach ($this->publishedOptions as $value => $label): ?>
                    <option value="<?php echo $value; ?>" <?php echo $publishedFilter === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="filter_status" id="filter_status" class="form-select ml-2" onchange="this.form.submit();">
                    <?php foreach ($this->statusOptions as $value => $label): ?>
                    <option value="<?php echo $value; ?>" <?php echo $statusFilter === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped" id="leadershipList">
            <thead>
                <tr>
                    <th width="1%" class="text-center"><input type="checkbox" name="checkall-toggle" value="" onclick="Joomla.checkAll(this)"></th>
                    <th width="5%" class="text-center"><?php echo Text::_('COM_CLUBLEADERSHIP_HEADING_ORDERING'); ?></th>
                    <th><?php echo Text::_('COM_CLUBLEADERSHIP_HEADING_NAME'); ?></th>
                    <th width="12%"><?php echo Text::_('COM_CLUBLEADERSHIP_HEADING_TYPE'); ?></th>
                    <th width="15%"><?php echo Text::_('COM_CLUBLEADERSHIP_HEADING_ROLE'); ?></th>
                    <th width="10%"><?php echo Text::_('COM_CLUBLEADERSHIP_HEADING_BOARD'); ?></th>
                    <th width="10%" class="text-center"><?php echo Text::_('JSTATUS'); ?></th>
                    <th width="5%" class="text-center"><?php echo Text::_('COM_CLUBLEADERSHIP_HEADING_ID'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($this->items)): ?>
                <tr>
                    <td colspan="8" class="text-center"><?php echo Text::_('COM_CLUBLEADERSHIP_NO_ITEMS_FOUND'); ?></td>
                </tr>
                <?php else: ?>
                <?php foreach ($this->items as $i => $item): ?>
                <tr class="row<?php echo $i % 2; ?>">
                    <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
                    <td class="text-center order">
                        <span class="btn-toolbar d-inline-flex" role="group">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="Joomla.listItemTask('cb<?php echo $i; ?>', 'leadership.reorder', '&direction=-1')" title="<?php echo Text::_('JORDERINGUP'); ?>">
                                <span class="icon-up" aria-hidden="true"></span>
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="Joomla.listItemTask('cb<?php echo $i; ?>', 'leadership.reorder', '&direction=1')" title="<?php echo Text::_('JORDERINGDOWN'); ?>">
                                <span class="icon-down" aria-hidden="true"></span>
                            </button>
                        </span>
                    </td>
                    <td>
                        <a href="<?php echo Route::_('index.php?option=com_clubleadership&view=leadership&id=' . $item->id); ?>">
                            <?php echo $this->escape($item->name); ?>
                        </a>
                        <?php if (!empty($item->league_name)): ?>
                        <br><small class="text-muted"><?php echo $this->escape($item->league_name); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?php echo $item->type_class; ?>"><?php echo $item->type_label; ?></span></td>
                    <td>
                        <?php echo $this->escape($item->role); ?>
                        <?php if (!empty($item->term)): ?>
                        <br><small class="text-muted"><?php echo $this->escape($item->term); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (($item->status ?? 'active') === 'archived'): ?>
                            <span class="badge bg-secondary"><?php echo Text::_('COM_CLUBLEADERSHIP_STATUS_ARCHIVED'); ?></span>
                        <?php else: ?>
                            <span class="badge bg-success"><?php echo Text::_('COM_CLUBLEADERSHIP_STATUS_ACTIVE'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'leadership.', true); ?></td>
                    <td class="text-center"><?php echo (int) $item->id; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
