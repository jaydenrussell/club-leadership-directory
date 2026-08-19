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

// NOTE: do NOT call HTMLHelper::_('behavior.core') or
// HTMLHelper::_('behavior.formvalidator') here. The JHtmlBehavior helper class
// was removed in Joomla 3.10 and calling either fatals the admin (HTTP 500).
// Core behaviors and the form validator script are loaded automatically by the
// framework; we provide a Joomla.submitbutton shim so the toolbar Save/Apply
// buttons validate + submit via native HTML5 constraints.

$item   = $this->item;
$isEdit = !empty($item->id);
?>

<script>
function toggleLeagueFields(type) {
    var leagueFields = document.getElementById('league-fields');
    if (type === 'director_league') {
        leagueFields.classList.remove('d-none');
    } else {
        leagueFields.classList.add('d-none');
    }
}
// Joomla 3.10 submit shim (replaces behavior.formvalidator).
if (typeof Joomla === 'undefined') { var Joomla = {}; }
Joomla.submitbutton = function (task) {
    if (task === 'leadership.cancel' || document.formvalidator && document.formvalidator.isValid(document.getElementById('adminForm'))) {
        Joomla.submitform(task, document.getElementById('adminForm'));
    } else if (document.getElementById('adminForm').checkValidity()) {
        Joomla.submitform(task, document.getElementById('adminForm'));
    }
};
</script>

<form action="<?php echo Route::_('index.php?option=com_clubleaddir&task=leadership.save'); ?>"
      method="post" name="adminForm" id="adminForm" class="form-validate" enctype="multipart/form-data">

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><h5><?php echo Text::_('COM_CLUBLEADDIR_LEADERSHIP_DETAILS'); ?></h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label required"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_NAME'); ?> *</label>
                        <input type="text" name="jform[name]" id="name" class="form-control" value="<?php echo $this->escape($item->name); ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label required"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_TYPE'); ?> *</label>
                            <select name="jform[type]" id="type" class="form-select" required onchange="toggleLeagueFields(this.value)">
                                <option value="officer" <?php echo $item->type === 'officer' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_OFFICER'); ?></option>
                                <option value="director" <?php echo $item->type === 'director' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_DIRECTOR'); ?></option>
                                <option value="director_league" <?php echo $item->type === 'director_league' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_DIRECTOR_LEAGUE'); ?></option>
                                <option value="staff" <?php echo $item->type === 'staff' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_STAFF'); ?></option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_ROLE'); ?></label>
                            <input type="text" name="jform[role]" id="role" class="form-control" value="<?php echo $this->escape($item->role); ?>">
                        </div>
                    </div>

                    <div id="league-fields" class="<?php echo $item->type !== 'director_league' ? 'd-none' : ''; ?>">
                        <div class="mb-3">
                            <label for="league_name" class="form-label"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_LEAGUE_NAME'); ?></label>
                            <input type="text" name="jform[league_name]" id="league_name" class="form-control" value="<?php echo $this->escape($item->league_name); ?>" placeholder="<?php echo Text::_('COM_CLUBLEADDIR_FIELD_LEAGUE_NAME_PLACEHOLDER'); ?>">
                            <small class="form-text text-muted"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_LEAGUE_NAME_HELP'); ?></small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="term" class="form-label"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_TERM'); ?></label>
                            <input type="text" name="jform[term]" id="term" class="form-control" value="<?php echo $this->escape($item->term); ?>" placeholder="2025-2027">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="contact_id" class="form-label"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_CONTACT_ID'); ?></label>
                            <input type="number" name="jform[contact_id]" id="contact_id" class="form-control" value="<?php echo (int) $item->contact_id; ?>">
                            <small class="form-text text-muted"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_CONTACT_ID_HELP'); ?></small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="bio" class="form-label"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_BIO'); ?></label>
                        <textarea name="jform[bio]" id="bio" class="form-control" rows="3"><?php echo $this->escape($item->bio); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5><?php echo Text::_('COM_CLUBLEADDIR_CONTACT_INFO'); ?></h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_EMAIL'); ?></label>
                            <input type="email" name="jform[email]" id="email" class="form-control" value="<?php echo $this->escape($item->email); ?>">
                            <small class="form-text text-muted"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_EMAIL_HELP'); ?></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_PHONE'); ?></label>
                            <input type="tel" name="jform[phone]" id="phone" class="form-control" value="<?php echo $this->escape($item->phone); ?>" placeholder="705-555-0100">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h5><?php echo Text::_('COM_CLUBLEADDIR_PUBLISHING'); ?></h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_BOARD_STATUS'); ?></label>
                        <select name="jform[status]" id="status" class="form-select">
                            <option value="active" <?php echo ($item->status ?? 'active') === 'active' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_STATUS_ACTIVE'); ?></option>
                            <option value="archived" <?php echo ($item->status ?? 'active') === 'archived' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_STATUS_ARCHIVED'); ?></option>
                        </select>
                        <small class="form-text text-muted"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_BOARD_STATUS_HELP'); ?></small>
                    </div>
                    <div class="mb-3">
                        <label for="published" class="form-label"><?php echo Text::_('JSTATUS'); ?></label>
                        <select name="jform[published]" id="published" class="form-select">
                            <option value="1" <?php echo $item->published ? 'selected' : ''; ?>><?php echo Text::_('JPUBLISHED'); ?></option>
                            <option value="0" <?php echo !$item->published ? 'selected' : ''; ?>><?php echo Text::_('JUNPUBLISHED'); ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="ordering" class="form-label"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_ORDERING'); ?></label>
                        <input type="number" name="jform[ordering]" id="ordering" class="form-control" value="<?php echo (int) $item->ordering; ?>">
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5><?php echo Text::_('COM_CLUBLEADDIR_PHOTO'); ?></h5></div>
                <div class="card-body">
                    <?php if ($item->photo): ?>
                    <div class="mb-3 text-center">
                        <img src="<?php echo $this->escape($item->photo); ?>" alt="" class="img-thumbnail" style="max-width: 150px;">
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="photo" class="form-label"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_PHOTO'); ?></label>
                        <input type="file" name="jform[photo]" id="photo" class="form-control" accept="image/*">
                        <small class="form-text text-muted"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_PHOTO_HELP'); ?></small>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5><?php echo Text::_('JMETADATA'); ?></h5></div>
                <div class="card-body">
                    <?php if ($item->id): ?>
                    <dl class="row mb-0">
                        <dt class="col-sm-5"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_ID'); ?></dt>
                        <dd class="col-sm-7"><?php echo (int) $item->id; ?></dd>
                        <dt class="col-sm-5"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_CREATED'); ?></dt>
                        <dd class="col-sm-7"><?php echo $this->escape($item->created); ?></dd>
                        <dt class="col-sm-5"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_MODIFIED'); ?></dt>
                        <dd class="col-sm-7"><?php echo $this->escape($item->modified); ?></dd>
                    </dl>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="jform[id]" value="<?php echo (int) $item->id; ?>">
    <input type="hidden" name="task" value="leadership.save">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
