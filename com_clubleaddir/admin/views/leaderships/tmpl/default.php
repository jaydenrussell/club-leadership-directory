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

    <?php if (empty($this->items)): ?>
        <div class="alert alert-no-items"><?php echo Text::_('COM_CLUBLEADDIR_NO_ITEMS_FOUND'); ?></div>
    <?php else: ?>
        <div class="clubleaddir-admin-cards">
            <?php foreach ($this->items as $i => $item): ?>
            <div class="clubleaddir-admin-card<?php echo $i % 2 ? ' odd' : ''; ?>">
                <div class="clble-row">
                    <div class="clble-check">
                        <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                    </div>
                    <div class="clble-order">
                        <input type="text" name="order[]" size="3" value="<?php echo (int) $item->ordering; ?>" class="input-mini" style="text-align:center;">
                    </div>
                    <div class="clble-photo">
                        <?php if (!empty($item->photo)): ?>
                            <img src="<?php echo $this->escape(ClubleaddirHelper::photoUrl($item->photo)); ?>" alt="<?php echo $this->escape($item->name); ?>" class="clble-avatar">
                        <?php else: ?>
                            <span class="clble-avatar clble-avatar-empty"><span class="icon-user"></span></span>
                        <?php endif; ?>
                    </div>
                    <div class="clble-body">
                        <div class="clble-name">
                            <a href="<?php echo Route::_('index.php?option=com_clubleaddir&view=leadership&id=' . $item->id); ?>">
                                <?php echo $this->escape($item->name); ?>
                            </a>
                            <?php if (($item->status ?? 'active') === 'archived'): ?>
                                <span class="badge badge-inverse" style="margin-left:6px;"><?php echo Text::_('COM_CLUBLEADDIR_STATUS_ARCHIVED'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="clble-meta">
                            <span class="badge <?php echo $item->type_class; ?>"><?php echo $item->type_label; ?></span>
                            <?php if (!empty($item->role)): ?>
                                <span class="clble-role"><?php echo $this->escape($item->role); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($item->league_name)): ?>
                                <span class="clble-role muted"><?php echo $this->escape($item->league_name); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="clble-status">
                        <?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'leadership.', true); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<style>
.clubleaddir-admin-cards { margin-top: 6px; }
.clubleaddir-admin-card {
    border: 1px solid #e3e3e3;
    border-radius: 4px;
    background: #fff;
    margin-bottom: 8px;
    padding: 8px 10px;
}
.clubleaddir-admin-card.odd { background: #fafafa; }
.clble-row { display: flex; align-items: center; }
.clble-check { width: 24px; flex: 0 0 24px; }
.clble-order { width: 48px; flex: 0 0 48px; padding: 0 6px; }
.clble-photo { width: 36px; flex: 0 0 36px; margin-right: 12px; }
.clble-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    object-fit: cover;
    display: inline-block;
    background: #eee;
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
}
.clble-body { flex: 1 1 auto; min-width: 0; }
.clble-name { font-weight: 600; font-size: 14px; }
.clble-name a { color: #333; text-decoration: none; }
.clble-name a:hover { color: #2a72b3; }
.clble-meta { margin-top: 2px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.clble-role { color: #555; font-size: 12px; }
.clble-status { width: 60px; flex: 0 0 60px; text-align: center; }
</style>
