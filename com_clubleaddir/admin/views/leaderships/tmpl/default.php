<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

// NOTE: do NOT call HTMLHelper::_('behavior.core') — the JHtmlBehavior helper
// was removed in Joomla 3.10 and calling it fatals the admin view.

$typeFilter        = $this->filters['type'];
$publishedFilter   = $this->filters['published'];
$statusFilter      = $this->filters['status'];
$search            = $this->filters['search'];
?>

<div class="clubleaddir-backend-note alert alert-info" style="margin: 10px 0 18px;">
    <?php echo Text::sprintf('COM_CLUBLEADDIR_BACKEND_NOTE', $this->escape($this->backendName)); ?>
</div>

<form action="<?php echo Route::_('index.php?option=com_clubleaddir'); ?>" method="post" name="adminForm" id="adminForm">

    <div class="row-fluid" style="margin-bottom: 14px;">
        <div class="span8">
            <div class="input-append" style="margin-bottom: 0;">
                <input type="text" name="filter_search" id="filter_search" class="input-xlarge"
                       placeholder="<?php echo Text::_('COM_CLUBLEADDIR_SEARCH_PLACEHOLDER'); ?>"
                       value="<?php echo $this->escape($search); ?>">
                <button type="submit" class="btn btn-primary">
                    <span class="icon-search" aria-hidden="true"></span>
                </button>
                <?php if ($search): ?>
                <button type="button" class="btn" onclick="document.getElementById('filter_search').value=''; this.form.submit();">
                    <span class="icon-remove" aria-hidden="true"></span>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="span4" style="text-align:right;">
            <select name="filter_type" id="filter_type" class="inputbox" style="width:auto;margin-bottom:0;" onchange="this.form.submit();">
                <?php foreach ($this->typeOptions as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo $typeFilter === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
            <select name="filter_published" id="filter_published" class="inputbox" style="width:auto;margin-bottom:0;" onchange="this.form.submit();">
                <?php foreach ($this->publishedOptions as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo $publishedFilter === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
            <select name="filter_status" id="filter_status" class="inputbox" style="width:auto;margin-bottom:0;" onchange="this.form.submit();">
                <?php foreach ($this->statusOptions as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo $statusFilter === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <table class="table table-striped table-hover" id="leadershipList" style="margin-top: 6px;">
        <thead>
            <tr>
                <th width="1%" class="center"><input type="checkbox" name="checkall-toggle" value="" onclick="Joomla.checkAll(this)"></th>
                <th width="6%" class="center"><?php echo Text::_('COM_CLUBLEADDIR_HEADING_ORDERING'); ?></th>
                <th width="7%" class="center"><?php echo Text::_('COM_CLUBLEADDIR_HEADING_PHOTO'); ?></th>
                <th><?php echo Text::_('COM_CLUBLEADDIR_HEADING_NAME'); ?></th>
                <th width="12%"><?php echo Text::_('COM_CLUBLEADDIR_HEADING_TYPE'); ?></th>
                <th width="15%"><?php echo Text::_('COM_CLUBLEADDIR_HEADING_ROLE'); ?></th>
                <th width="10%"><?php echo Text::_('COM_CLUBLEADDIR_HEADING_BOARD'); ?></th>
                <th width="9%" class="center"><?php echo Text::_('JSTATUS'); ?></th>
                <th width="5%" class="center"><?php echo Text::_('COM_CLUBLEADDIR_HEADING_ID'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($this->items)): ?>
            <tr>
                <td colspan="9" class="center"><?php echo Text::_('COM_CLUBLEADDIR_NO_ITEMS_FOUND'); ?></td>
            </tr>
            <?php else: ?>
            <?php foreach ($this->items as $i => $item): ?>
            <tr class="row<?php echo $i % 2; ?>">
                <td class="center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
                <td class="order center">
                    <input type="text" name="order[]" size="5" value="<?php echo (int) $item->ordering; ?>" class="input-mini" style="text-align:center;">
                </td>
                <td class="center">
                    <?php if (!empty($item->photo)): ?>
                        <img src="<?php echo $this->escape($item->photo); ?>" alt="<?php echo $this->escape($item->name); ?>" class="thumbnail" style="max-width:48px;max-height:48px;display:inline-block;">
                    <?php else: ?>
                        <span class="icon-user" style="opacity:.3;"></span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?php echo Route::_('index.php?option=com_clubleaddir&view=leadership&id=' . $item->id); ?>">
                        <?php echo $this->escape($item->name); ?>
                    </a>
                    <?php if (!empty($item->league_name)): ?>
                    <br><small class="muted"><?php echo $this->escape($item->league_name); ?></small>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?php echo $item->type_class; ?>"><?php echo $item->type_label; ?></span></td>
                <td>
                    <?php echo $this->escape($item->role); ?>
                    <?php if (!empty($item->term)): ?>
                    <br><small class="muted"><?php echo $this->escape($item->term); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (($item->status ?? 'active') === 'archived'): ?>
                        <span class="badge badge-inverse"><?php echo Text::_('COM_CLUBLEADDIR_STATUS_ARCHIVED'); ?></span>
                    <?php else: ?>
                        <span class="badge badge-success"><?php echo Text::_('COM_CLUBLEADDIR_STATUS_ACTIVE'); ?></span>
                    <?php endif; ?>
                </td>
                <td class="center"><?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'leadership.', true); ?></td>
                <td class="center"><?php echo (int) $item->id; ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
