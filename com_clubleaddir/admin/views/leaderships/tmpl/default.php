<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.core');
HTMLHelper::_('sortablelist.sortable', 'clubleaddirList', 'adminForm', 'asc', 'index.php?option=com_clubleaddir&task=leadership.saveorderAjax&tmpl=component');

$typeFilter        = $this->filters['type'];
$publishedFilter   = $this->filters['published'];
$statusFilter      = $this->filters['status'];
$termFilter        = $this->filters['term'];
$search            = $this->filters['search'];

$listOrder = $this->escape($this->listOrder);
$listDirn  = $this->escape($this->listDirn);
$saveOrder = ($listOrder === 'ordering');
?>

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

    <table class="table table-striped" id="clubleaddirList">
        <thead>
            <tr>
                <th width="1%" class="nowrap center hidden-phone">
                    <?php echo HTMLHelper::_('grid.sort', '<span class="icon-menu-2"></span>', 'ordering', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING'); ?>
                </th>
                <th width="1%" class="center hidden-phone">
                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                </th>
                <th width="1%" class="nowrap center">
                    <?php echo HTMLHelper::_('grid.sort', 'JSTATUS', 'published', $listDirn, $listOrder); ?>
                </th>
                <th>
                    <?php echo HTMLHelper::_('grid.sort', 'JGLOBAL_TITLE', 'name', $listDirn, $listOrder); ?>
                </th>
                <th width="14%" class="nowrap hidden-phone">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_CLUBLEADDIR_HEADING_ROLE', 'role', $listDirn, $listOrder); ?>
                </th>
                <th width="15%" class="nowrap">
                    <?php echo Text::_('COM_CLUBLEADDIR_HEADING_CONTACT'); ?>
                </th>
                <th width="10%" class="nowrap hidden-phone">
                    <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ACCESS', 'type', $listDirn, $listOrder); ?>
                </th>
                <th width="10%" class="nowrap hidden-phone">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_CLUBLEADDIR_HEADING_TERM', 'term', $listDirn, $listOrder); ?>
                </th>
                <th width="1%" class="nowrap center hidden-phone">
                    <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ID', 'id', $listDirn, $listOrder); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($this->items)): ?>
                <tr><td colspan="9" class="center"><?php echo Text::_('COM_CLUBLEADDIR_NO_ITEMS_FOUND'); ?></td></tr>
            <?php else: ?>
                <?php foreach ($this->items as $i => $item): ?>
                    <?php
                    $canChange = true;
                    $ordering = ($listOrder === 'ordering');
                    $displayName = $item->name ?: ($item->role ?: Text::_('COM_CLUBLEADDIR_VACANT'));
                    $roleAlias   = $item->role ? ' (Alias: ' . $item->role . ')' : '';
                    // Category line mimics com_contact "Category: Officers" — map type label.
                    $catLabel = $item->type_label;
                    if (!empty($item->league_name)) {
                        $catLabel .= ' &middot; ' . $item->league_name;
                    }
                    ?>
                <tr class="row<?php echo $i % 2; ?>" sortable-group-id="1">
                    <td class="order nowrap center hidden-phone">
                        <?php if ($canChange) : ?>
                            <span class="sortable-handler<?php echo $ordering ? '' : ' inactive'; ?>">
                                <span class="icon-menu" aria-hidden="true"></span>
                            </span>
                        <?php else : ?>
                            <span class="sortable-handler inactive"><span class="icon-menu" aria-hidden="true"></span></span>
                        <?php endif; ?>
                        <input type="text" style="display:none" name="order[]" size="5" value="<?php echo (int) $item->ordering; ?>" class="width-20 text-area-order" />
                    </td>
                    <td class="center hidden-phone">
                        <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                    </td>
                    <td class="center">
                        <?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'leadership.', $canChange); ?>
                    </td>
                    <td class="has-context">
                        <div class="pull-left" style="margin-right:8px;">
                            <?php if (!empty($item->photo)): ?>
                                <img src="<?php echo $this->escape(ClubleaddirHelper::photoUrl($item->photo)); ?>" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;background:#eee;">
                            <?php else: ?>
                                <span style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;border-radius:50%;background:#e9e9e9;color:#bbb;"><span class="icon-user" style="font-size:16px;"></span></span>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo Route::_('index.php?option=com_clubleaddir&view=leadership&id=' . (int) $item->id); ?>">
                            <?php echo $this->escape($displayName); ?>
                        </a>
                        <span class="small" style="color:#777;"><?php echo $this->escape($roleAlias); ?></span>
                        <?php if (!empty($item->vacant)): ?>
                            <span class="badge" style="margin-left:6px;background:#b8963e;color:#fff;vertical-align:middle;"><?php echo Text::_('COM_CLUBLEADDIR_VACANT'); ?></span>
                        <?php endif; ?>
                        <?php if (($item->status ?? 'active') === 'archived'): ?>
                            <span class="badge badge-inverse" style="margin-left:6px;vertical-align:middle;"><?php echo Text::_('COM_CLUBLEADDIR_STATUS_ARCHIVED'); ?></span>
                        <?php endif; ?>
                        <div class="small" style="color:#777;">
                            <?php echo Text::_('JCATEGORY'); ?>: <?php echo $catLabel; ?>
                        </div>
                    </td>
                    <td class="small hidden-phone">
                        <?php
                        if (($item->type ?? '') === 'director_league' && !empty($item->league_name)) {
                            echo $this->escape(ClubleaddirHelper::leagueNameLabel($item->league_name));
                        } else {
                            echo $this->escape($item->role ?? '');
                            if (empty($item->role)) { echo '<span style="color:#999;">&mdash;</span>'; }
                        }
                        ?>
                    </td>
                    <td class="small">
                        <?php
                        // Mimics "Linked User" in com_contact — shows who the contact resolves to.
                        $linked = '';
                        if (!empty($item->contact_id)) {
                            $cName = ClubleaddirHelper::contactName((int) $item->contact_id);
                            $linked = $cName ?: ('ID ' . (int) $item->contact_id);
                            echo $this->escape($linked);
                            if (!empty($item->email)) {
                                echo '<br><span class="small" style="color:#777;">' . $this->escape($item->email) . '</span>';
                            }
                        } elseif (!empty($item->email)) {
                            echo $this->escape($item->email);
                            if (!empty($item->phone)) {
                                echo '<br><span class="small" style="color:#777;">' . $this->escape($item->phone) . '</span>';
                            }
                        } elseif (!empty($item->phone)) {
                            echo $this->escape($item->phone);
                        } else {
                            echo '<span style="color:#999;">&mdash;</span>';
                        }
                        ?>
                    </td>
                    <td class="small hidden-phone">
                        <?php echo $this->escape($item->type_label); ?>
                    </td>
                    <td class="small hidden-phone">
                        <?php
                        if ($item->type === 'staff') {
                            $sy = (int) ($item->start_year ?? 0);
                            $ey = (int) ($item->end_year ?? 0);
                            if ($sy > 0) {
                                echo $this->escape($sy . ' - ' . ($ey > 0 ? $ey : 'Current'));
                            } else {
                                echo '<span style="color:#999;">&mdash;</span>';
                            }
                        } else {
                            echo $item->term ? $this->escape($item->term) : '<span style="color:#999;">&mdash;</span>';
                        }
                        ?>
                    </td>
                    <td class="center hidden-phone">
                        <?php echo (int) $item->id; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>">
    <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<style>
#clubleaddirList th, #clubleaddirList td { vertical-align: middle; }
#clubleaddirList td.order { text-align: center; }
#clubleaddirList .sortable-handler { cursor: move; color: #999; }
#clubleaddirList .sortable-handler.inactive { opacity: 0.3; cursor: default; }
#clubleaddirList .sortable-handler:hover:not(.inactive) { color: #333; }
@media (max-width: 767px) {
    #clubleaddirFilters .span8,
    #clubleaddirFilters .span4 { width: 100% !important; margin-left: 0 !important; float: none !important; text-align: left !important; }
    #clubleaddirFilters select { width: 100% !important; max-width: 100%; box-sizing: border-box; margin-bottom: 6px; }
    #clubleaddirFilters .input-append { width: 100%; }
    #clubleaddirFilters .input-xlarge { width: 100%; box-sizing: border-box; }
}
</style>
