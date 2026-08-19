<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

// NOTE: do NOT call HTMLHelper::_('behavior.core') / ('behavior.formvalidator').
// The JHtmlBehavior helper was removed in Joomla 3.10 and calling it fatals the
// admin (HTTP 500). We use native HTML5 `required` + a submit shim that blocks
// submit on empty required fields and outlines them with a red border.

$item   = $this->item;
// Normalise: legacy records in the store may lack newer columns. Provide safe
// defaults so every property access below is defined (PHP 8.0 warns on reads
// of undefined stdClass properties).
$itemDefaults = array(
    'id' => 0, 'name' => '', 'type' => 'director', 'role' => '', 'league_name' => '',
    'term' => '', 'bio' => '', 'photo' => '', 'email' => '', 'phone' => '',
    'contact_id' => 0, 'ordering' => 0, 'published' => 1, 'status' => 'active',
    'created' => '', 'modified' => '',
);
if (is_object($item)) {
    foreach ($itemDefaults as $k => $v) {
        if (!property_exists($item, $k)) {
            $item->$k = $v;
        }
    }
} else {
    $item = (object) $itemDefaults;
}
$isEdit = !empty($item->id);
$hasContactComponent = ComponentHelper::isEnabled('com_contact');

// League representative options (only relevant when type = director_league).
$leagueOptions = array(
    'day_ladies'       => Text::_('COM_CLUBLEADDIR_LEAGUE_DAY_LADIES'),
    'evening_ladies'   => Text::_('COM_CLUBLEADDIR_LEAGUE_EVENING_LADIES'),
    'senior_men'       => Text::_('COM_CLUBLEADDIR_LEAGUE_SENIOR_MEN'),
);
?>

<script>
function toggleLeagueFields(type) {
    var wrap = document.getElementById('league-fields');
    if (type === 'director_league') {
        wrap.style.display = 'block';
    } else {
        wrap.style.display = 'none';
        var sel = document.getElementById('league_name');
        if (sel) { sel.value = ''; }
    }
}

// Joomla 3.10 submit shim (replaces behavior.formvalidator).
if (typeof Joomla === 'undefined') { var Joomla = {}; }
Joomla.submitbutton = function (task) {
    if (task === 'leadership.cancel') {
        Joomla.submitform(task, document.getElementById('adminForm'));
        return;
    }
    var form = document.getElementById('adminForm');
    var ok = true;
    var prev = form.querySelectorAll('.clble-invalid');
    for (var i = 0; i < prev.length; i++) { prev[i].classList.remove('clble-invalid'); }
    var req = form.querySelectorAll('[required]');
    for (var j = 0; j < req.length; j++) {
        var el = req[j];
        if (el.id === 'league_name' && document.getElementById('league-fields').style.display === 'none') { continue; }
        if (!el.value || !el.value.trim()) { el.classList.add('clble-invalid'); ok = false; }
    }
    if (!ok) {
        alert('<?php echo Text::_('COM_CLUBLEADDIR_ERROR_REQUIRED_FIELDS'); ?>');
        return;
    }
    Joomla.submitform(task, form);
};

// Contact Component picker: com_contact calls this when a contact is chosen.
function jSelectContact(id, name) {
    var field = document.getElementById('contact_id');
    if (field) { field.value = id; }
    var disp = document.getElementById('contact_name_display');
    if (disp) { disp.textContent = name; }
    if (window.jQuery && jQuery('.modal').length) { jQuery('.modal').modal('hide'); }
    else if (window.SqueezeBox) { SqueezeBox.close(); }
    return false;
}
</script>

<style>
.clble-invalid { border-color: #b94a48 !important; box-shadow: 0 0 0 1px #b94a48 !important; }
.clble-edit-grid .control-label { width: 180px; }
.clble-edit-grid .controls { margin-left: 200px; }
.clble-contact-picked { font-size: 12px; color: #555; margin-top: 4px; }
</style>

<form action="<?php echo Route::_('index.php?option=com_clubleaddir&task=leadership.save'); ?>"
      method="post" name="adminForm" id="adminForm" class="form-validate" enctype="multipart/form-data">

    <div class="row-fluid clble-edit-grid">
        <!-- MAIN COLUMN -->
        <div class="span8">
            <fieldset class="adminform">
                <legend><?php echo Text::_('COM_CLUBLEADDIR_LEADERSHIP_DETAILS'); ?></legend>

                <div class="row-fluid">
                    <div class="span3" style="text-align:center;">
                        <div class="control-group">
                            <div class="controls">
                                <?php if ($item->photo): ?>
                                    <img src="<?php echo $this->escape(ClubleaddirHelper::photoUrl($item->photo)); ?>" alt="<?php echo $this->escape($item->name); ?>"
                                         class="thumbnail" style="max-width:150px;max-height:150px;display:inline-block;margin-bottom:8px;">
                                    <p class="help-block" style="word-break:break-all;font-size:11px;"><?php echo $this->escape($item->photo); ?></p>
                                <?php else: ?>
                                    <div class="thumbnail" style="width:150px;height:150px;line-height:150px;text-align:center;color:#bbb;margin:0 auto 8px;"><span class="icon-user" style="font-size:48px;"></span></div>
                                <?php endif; ?>
                                <input type="file" name="jform[photo]" id="photo" class="inputbox" accept="image/*">
                                <p class="help-block"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_PHOTO_HELP'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="span9">
                        <div class="control-group">
                            <div class="control-label">
                                <label for="name" class="required"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_NAME'); ?> <span class="star">*</span></label>
                            </div>
                            <div class="controls">
                                <input type="text" name="jform[name]" id="name" class="input-xxlarge" value="<?php echo $this->escape($item->name); ?>" required>
                            </div>
                        </div>

                        <div class="control-group">
                            <div class="control-label">
                                <label for="type" class="required"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_TYPE'); ?> <span class="star">*</span></label>
                            </div>
                            <div class="controls">
                                <select name="jform[type]" id="type" class="input-xxlarge" required onchange="toggleLeagueFields(this.value)">
                                    <option value="officer" <?php echo $item->type === 'officer' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_OFFICER'); ?></option>
                                    <option value="director" <?php echo $item->type === 'director' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_DIRECTOR'); ?></option>
                                    <option value="director_league" <?php echo $item->type === 'director_league' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_DIRECTOR_LEAGUE'); ?></option>
                                    <option value="staff" <?php echo $item->type === 'staff' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_STAFF'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="control-group">
                            <div class="control-label">
                                <label for="role"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_ROLE'); ?></label>
                            </div>
                            <div class="controls">
                                <input type="text" name="jform[role]" id="role" class="input-xxlarge" value="<?php echo $this->escape($item->role); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div id="league-fields" style="display:<?php echo $item->type === 'director_league' ? 'block' : 'none'; ?>;">
                    <div class="control-group">
                        <div class="control-label">
                            <label for="league_name" class="required"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_LEAGUE_NAME'); ?> <span class="star">*</span></label>
                        </div>
                        <div class="controls">
                            <select name="jform[league_name]" id="league_name" class="input-xxlarge">
                                <option value=""><?php echo Text::_('COM_CLUBLEADDIR_SELECT_LEAGUE'); ?></option>
                                <?php foreach ($leagueOptions as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo ($item->league_name ?? '') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="help-block"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_LEAGUE_NAME_HELP'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="control-group">
                    <div class="control-label">
                        <label for="term"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_TERM'); ?></label>
                    </div>
                    <div class="controls">
                        <input type="text" name="jform[term]" id="term" class="input-medium" value="<?php echo $this->escape($item->term); ?>" placeholder="2025-2027">
                    </div>
                </div>

                <div class="control-group">
                    <div class="control-label">
                        <label for="bio"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_BIO'); ?></label>
                    </div>
                    <div class="controls">
                        <textarea name="jform[bio]" id="bio" class="input-xxlarge" rows="4"><?php echo $this->escape($item->bio); ?></textarea>
                    </div>
                </div>
            </fieldset>

            <fieldset class="adminform">
                <legend><?php echo Text::_('COM_CLUBLEADDIR_CONTACT_INFO'); ?></legend>

                <div class="control-group">
                    <div class="control-label">
                        <label for="contact_id"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_CONTACT_ID'); ?></label>
                    </div>
                    <div class="controls">
                        <div class="input-append">
                            <input type="number" name="jform[contact_id]" id="contact_id" class="input-medium" style="width:120px;"
                                   value="<?php echo (int) $item->contact_id; ?>">
                            <?php if ($hasContactComponent): ?>
                            <a class="btn modal btn-contact-pick" title="<?php echo Text::_('COM_CLUBLEADDIR_FIELD_CONTACT_ID_HELP'); ?>"
                               href="<?php echo Route::_('index.php?option=com_contact&view=contacts&layout=modal&tmpl=component&field=contact_id'); ?>"
                               rel="{handler: 'iframe', size: {x: 800, y: 500}}">
                                <span class="icon-search"></span> <?php echo Text::_('COM_CLUBLEADDIR_LOOKUP_CONTACT'); ?>
                            </a>
                            <?php else: ?>
                            <a class="btn" href="<?php echo Uri::base(); ?>index.php?option=com_contact&view=contacts" target="_blank">
                                <span class="icon-list"></span> <?php echo Text::_('COM_CLUBLEADDIR_OPEN_CONTACTS'); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="clble-contact-picked">
                            <?php if ($hasContactComponent): ?>
                                <?php echo Text::_('COM_CLUBLEADDIR_FIELD_CONTACT_ID_HELP'); ?>
                                <span id="contact_name_display"><?php echo ((int)$item->contact_id ? Text::_('COM_CLUBLEADDIR_CONTACT_ID_SET') : ''); ?></span>
                                <?php if ((int) $item->contact_id): ?>
                                    <a class="btn btn-small" style="margin-left:8px;"
                                       href="<?php echo Route::_('index.php?option=com_contact&task=contact.edit&id=' . (int) $item->contact_id); ?>" target="_blank">
                                        <span class="icon-link"></span> <?php echo Text::_('COM_CLUBLEADDIR_VIEW_CONTACT'); ?>
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php echo Text::_('COM_CLUBLEADDIR_CONTACT_COMPONENT_MISSING'); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row-fluid">
                    <div class="span6">
                        <div class="control-group">
                            <div class="control-label">
                                <label for="email"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_EMAIL'); ?></label>
                            </div>
                            <div class="controls">
                                <input type="email" name="jform[email]" id="email" class="input-xxlarge" value="<?php echo $this->escape($item->email); ?>">
                                <p class="help-block muted" style="font-size:11px;">@simcoecurlingclub.ca</p>
                            </div>
                        </div>
                    </div>
                    <div class="span6">
                        <div class="control-group">
                            <div class="control-label">
                                <label for="phone"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_PHONE'); ?></label>
                            </div>
                            <div class="controls">
                                <input type="tel" name="jform[phone]" id="phone" class="input-xxlarge" value="<?php echo $this->escape($item->phone); ?>" placeholder="705-555-0100">
                            </div>
                        </div>
                    </div>
                </div>
            </fieldset>
        </div>

        <!-- SIDE COLUMN -->
        <div class="span4">
            <fieldset class="adminform">
                <legend><?php echo Text::_('COM_CLUBLEADDIR_PUBLISHING'); ?></legend>

                <div class="control-group">
                    <div class="control-label">
                        <label for="status"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_BOARD_STATUS'); ?></label>
                    </div>
                    <div class="controls">
                        <select name="jform[status]" id="status" class="inputbox" style="width:100%;">
                            <option value="active" <?php echo ($item->status ?? 'active') === 'active' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_STATUS_ACTIVE'); ?></option>
                            <option value="archived" <?php echo ($item->status ?? 'active') === 'archived' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_STATUS_ARCHIVED'); ?></option>
                        </select>
                        <p class="help-block"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_BOARD_STATUS_HELP'); ?></p>
                    </div>
                </div>

                <div class="control-group">
                    <div class="control-label">
                        <label for="published"><?php echo Text::_('JSTATUS'); ?></label>
                    </div>
                    <div class="controls">
                        <select name="jform[published]" id="published" class="inputbox" style="width:100%;">
                            <option value="1" <?php echo $item->published ? 'selected' : ''; ?>><?php echo Text::_('JPUBLISHED'); ?></option>
                            <option value="0" <?php echo !$item->published ? 'selected' : ''; ?>><?php echo Text::_('JUNPUBLISHED'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="control-group">
                    <div class="control-label">
                        <label for="ordering"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_ORDERING'); ?></label>
                    </div>
                    <div class="controls">
                        <input type="number" name="jform[ordering]" id="ordering" class="input-medium" value="<?php echo (int) $item->ordering; ?>">
                    </div>
                </div>

                <?php if ($isEdit): ?>
                <hr>
                <table class="table table-condensed" style="margin-bottom:0;">
                    <tbody>
                        <tr style="font-size:12px;">
                            <td style="border-top:none;color:#666;width:40%;"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_ID'); ?></td>
                            <td style="border-top:none;"><?php echo (int) $item->id; ?></td>
                        </tr>
                        <tr style="font-size:12px;">
                            <td style="border-top:none;color:#666;"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_CREATED'); ?></td>
                            <td style="border-top:none;"><?php echo $this->escape($item->created); ?></td>
                        </tr>
                        <tr style="font-size:12px;">
                            <td style="border-top:none;color:#666;"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_MODIFIED'); ?></td>
                            <td style="border-top:none;"><?php echo $this->escape($item->modified); ?></td>
                        </tr>
                    </tbody>
                </table>
                <?php endif; ?>
            </fieldset>
        </div>
    </div>

    <input type="hidden" name="jform[id]" value="<?php echo (int) $item->id; ?>">
    <input type="hidden" name="task" value="leadership.save">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
