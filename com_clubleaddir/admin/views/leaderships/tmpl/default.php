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
$termFilter        = $this->filters['term'];
$search            = $this->filters['search'];
?>

<div class="clubleaddir-backend-note alert alert-info" style="margin: 10px 0 18px;">
    <?php echo Text::sprintf('COM_CLUBLEADDIR_BACKEND_NOTE', $this->escape($this->backendName)); ?>
</div>

<form action="<?php echo Route::_('index.php?option=com_clubleaddir'); ?>" method="post" name="adminForm" id="adminForm">

    <div class="row-fluid" id="clubleaddirFilters" style="margin-bottom: 14px;">
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
            <select name="filter_term" id="filter_term" class="inputbox" style="width:auto;margin-bottom:0;" onchange="this.form.submit();">
                <?php foreach ($this->termOptions as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo $termFilter === $value ? 'selected' : ''; ?>><?php echo $this->escape($label); ?></option>
                <?php endforeach; ?>
            </select>
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

    <div class="clubleaddir-table-wrap">
    <table class="table table-striped" id="clubleaddirList">
        <thead>
            <tr>
                <th width="1%" class="center"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
                <th width="6%" class="center nowrap"><?php echo Text::_('COM_CLUBLEADDIR_HEADING_ORDERING'); ?></th>
                <th width="6%" class="center nowrap"><?php echo Text::_('COM_CLUBLEADDIR_HEADING_PHOTO'); ?></th>
                <th class="clble-col-name"><?php echo Text::_('COM_CLUBLEADDIR_HEADING_NAME'); ?></th>
                <th class="clble-col-role"><?php echo Text::_('COM_CLUBLEADDIR_HEADING_ROLE'); ?></th>
                <th class="clble-col-type"><?php echo Text::_('COM_CLUBLEADDIR_HEADING_TYPE'); ?></th>
                <th class="clble-col-term"><?php echo Text::_('COM_CLUBLEADDIR_HEADING_TERM'); ?></th>
                <th width="8%" class="center nowrap"><?php echo Text::_('JSTATUS'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($this->items)): ?>
                <tr><td colspan="7" class="center"><?php echo Text::_('COM_CLUBLEADDIR_NO_ITEMS_FOUND'); ?></td></tr>
            <?php else: ?>
                <?php foreach ($this->items as $i => $item): ?>
                <tr class="row<?php echo $i % 2; ?>">
                    <td class="center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
                    <td class="center nowrap">
                        <input type="text" name="order[]" size="4" value="<?php echo (int) $item->ordering; ?>" class="input-mini center" style="text-align:center;">
                    </td>
                    <td class="center">
                        <?php if (!empty($item->photo)): ?>
                            <img src="<?php echo $this->escape(ClubleaddirHelper::photoUrl($item->photo)); ?>" alt="<?php echo $this->escape($item->name); ?>" class="clble-avatar-sm">
                        <?php else: ?>
                            <span class="clble-avatar-sm clble-avatar-empty"><span class="icon-user"></span></span>
                        <?php endif; ?>
                    </td>
                    <td class="clble-col-name">
                        <a href="<?php echo Route::_('index.php?option=com_clubleaddir&view=leadership&id=' . $item->id); ?>">
                            <?php echo $this->escape($item->name ?: ($item->role ?: Text::_('COM_CLUBLEADDIR_VACANT'))); ?>
                        </a>
                        <?php if (!empty($item->vacant)): ?>
                            <span class="badge" style="margin-left:6px;background:#b8963e;color:#fff;"><?php echo Text::_('COM_CLUBLEADDIR_VACANT'); ?></span>
                        <?php endif; ?>
                        <?php if (($item->status ?? 'active') === 'archived'): ?>
                            <span class="badge badge-inverse" style="margin-left:6px;"><?php echo Text::_('COM_CLUBLEADDIR_STATUS_ARCHIVED'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="clble-col-role"><?php echo $this->escape($item->role ?: '—'); ?></td>
                    <td class="clble-col-type">
                        <span class="badge <?php echo $item->type_class; ?>"><?php echo $item->type_label; ?></span>
                        <?php if (!empty($item->league_name)): ?>
                            <div class="muted" style="font-size:11px;"><?php echo $this->escape($item->league_name); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="clble-col-term nowrap">
                        <?php
                        if ($item->type === 'staff') {
                            $sy = (int) ($item->start_year ?? 0);
                            $ey = (int) ($item->end_year ?? 0);
                            if ($sy > 0) {
                                echo $this->escape($sy . ' – ' . ($ey > 0 ? $ey : 'Current'));
                            } else {
                                echo '—';
                            }
                        } else {
                            echo $this->escape($item->term ?: '—');
                        }
                        ?>
                    </td>
                    <td class="center"><?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'leadership.', true); ?></td>
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

<style>
#clubleaddirList th, #clubleaddirList td { vertical-align: middle; }
#clubleaddirList .clble-col-name { min-width: 180px; }
#clubleaddirList .clble-col-role { min-width: 160px; }
#clubleaddirList .clble-col-type { min-width: 150px; }
#clubleaddirList .clble-col-term { min-width: 110px; }
.clble-avatar-sm {
    width: 36px; height: 36px;
    border-radius: 50%;
    object-fit: cover;
    display: inline-block;
    background: #eee;
    vertical-align: middle;
}
.clble-avatar-empty {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: #e9e9e9;
    color: #bbb;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    vertical-align: middle;
}

/* ---- Mobile / responsive (Bootstrap 2 breakpoint at 767px) ---- */
@media (max-width: 767px) {
    /* Stack the filter bar and let dropdowns fill width. */
    #clubleaddirFilters .span8,
    #clubleaddirFilters .span4 {
        width: 100% !important;
        margin-left: 0 !important;
        float: none !important;
        text-align: left !important;
    }
    #clubleaddirFilters select { width: 100% !important; max-width: 100%; box-sizing: border-box; margin-bottom: 6px; }
    #clubleaddirFilters .input-append { width: 100%; }
    #clubleaddirFilters .input-xlarge { width: 100%; box-sizing: border-box; }
    /* Keep the table readable: scroll horizontally instead of crushing columns. */
    .clubleaddir-table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
}
</style>
