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

// Load the modal behavior so the "Look up Contact" SqueezeBox link opens the
// Contact Component picker as a modal (search by name -> auto-fill ID).
// NOTE: behavior.modal exists in J3.10 (only behavior.core was removed).
HTMLHelper::_('behavior.modal');

// NOTE: do NOT call HTMLHelper::_('behavior.core') / ('behavior.formvalidator').
// The JHtmlBehavior helper was removed in Joomla 3.10 and calling it fatals the
// admin (HTTP 500). We use native HTML5 `required` + a submit shim that blocks
// submit on empty required fields and outlines them with a red border.

$item   = $this->item;
// Normalise: legacy records in the store may lack newer columns. Provide safe
// defaults so every property access below is defined (PHP 8.0 warns on reads
// of undefined stdClass properties).
$itemDefaults = array(
    'id' => 0, 'name' => '', 'type' => '', 'role' => '', 'league_name' => '',
    'term' => '', 'bio' => '', 'photo' => '', 'email' => '', 'phone' => '',
    'contact_id' => 0, 'vacant' => 0, 'vacancy_email' => '', 'ordering' => 0, 'published' => 1, 'status' => 'active',
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

// Officer role/title is restricted to these (per club governance).
$officerRoles = array(
    'President'       => Text::_('COM_CLUBLEADDIR_ROLE_PRESIDENT'),
    'Vice President'  => Text::_('COM_CLUBLEADDIR_ROLE_VICE_PRESIDENT'),
    'Secretary'       => Text::_('COM_CLUBLEADDIR_ROLE_SECRETARY'),
    'Treasurer'      => Text::_('COM_CLUBLEADDIR_ROLE_TREASURER'),
);

// Default Term for a new (Add) record: current season.
// Season starts in (assumed) June: on/after June -> YEAR-(YEAR+1), else (YEAR-1)-YEAR.
$thisYear  = (int) date('Y');
$thisMonth = (int) date('n');
if ($thisMonth >= 6) {
    $defaultTerm = $thisYear . '-' . ($thisYear + 1);
} else {
    $defaultTerm = ($thisYear - 1) . '-' . $thisYear;
}
?>

<script>
function toggleTypeFields(type) {
    // League reps only.
    var leagueWrap = document.getElementById('league-fields');
    if (type === 'director_league') {
        leagueWrap.style.display = 'block';
    } else {
        leagueWrap.style.display = 'none';
        var lsel = document.getElementById('league_name');
        if (lsel) { lsel.value = ''; }
    }
    // Officer role is a restricted dropdown; other types use free text.
    var isOfficer = (type === 'officer');
    var isLeague = (type === 'director_league');
    var roleSelect = document.getElementById('role_select');
    var roleText   = document.getElementById('role_text');
    var roleHidden = document.getElementById('role');
    var roleGroup  = document.getElementById('role-control-group');
    if (isOfficer) {
        roleSelect.style.display = 'block';
        roleText.style.display = 'none';
        roleText.value = '';
        roleHidden.value = roleSelect.value;
        setRoleDisabled(false);
    } else if (isLeague) {
        // League directors are identified by their league, not a job title.
        // Grey out and disable the Role/Title field so it can't be set.
        roleSelect.style.display = 'none';
        roleText.style.display = 'none';
        roleHidden.value = '';
        roleText.value = '';
        roleSelect.value = '';
        setRoleDisabled(true);
    } else {
        roleSelect.style.display = 'none';
        roleText.style.display = 'block';
        // Leaving officer/league: clear any restricted role so it isn't inherited.
        roleHidden.value = '';
        roleText.value = '';
        roleSelect.value = '';
        setRoleDisabled(false);
    }
    // Staff use employment years instead of a "term".
    var staffWrap = document.getElementById('staff-fields');
    if (staffWrap) {
        staffWrap.style.display = (type === 'staff') ? 'block' : 'none';
    }
}

// Grey out + disable the Role/Title control group (used for league directors).
function setRoleDisabled(disabled) {
    var group = document.getElementById('role-control-group');
    if (group) {
        group.classList.toggle('clble-disabled', disabled);
    }
    var inputs = ['role_text', 'role_select'];
    inputs.forEach(function (id) {
        var el = document.getElementById(id);
        if (el) { el.disabled = disabled; }
    });
}

// When a position is vacant: contact details are irrelevant (the vacancy enquiry
// email handles it), so grey those fields out; the role/title stays required;
// and the photo becomes the club logo (no personal upload needed).
function toggleVacantFields(isVacant) {
    // Vacancy enquiry email stays active — it IS what gets used.
    var ve = document.getElementById('vacancy-email-group');
    if (ve) { ve.style.display = isVacant ? 'block' : 'none'; }

    // Grey + disable the personal contact fields (email / phone / Joomla contact).
    var fieldset = document.getElementById('contact-info-fieldset');
    if (fieldset) {
        fieldset.classList.toggle('clble-disabled', isVacant);
        ['contact_id', 'email', 'phone'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.disabled = isVacant; }
        });
    }

    // Photo upload is unnecessary when vacant (club logo is shown instead).
    var photo = document.getElementById('photo');
    if (photo) { photo.disabled = isVacant; }

    // Role/Title is required for a vacancy (it is the role being advertised).
    // Joomla's form-validate reads the REQUIRED ATTRIBUTE (not the property), so
    // we toggle the attribute explicitly rather than el.required.
    ['role_text', 'role_select'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            if (isVacant) { el.setAttribute('required', 'required'); }
            else { el.removeAttribute('required'); }
        }
    });
    // A vacant post has no named person yet, so the name is optional.
    var nameEl = document.getElementById('name');
    if (nameEl) {
        if (isVacant) { nameEl.removeAttribute('required'); }
        else { nameEl.setAttribute('required', 'required'); }
    }
    var nameLabel = document.querySelector('label[for="name"]');
    if (nameLabel) {
        var star = nameLabel.querySelector('.star');
        if (isVacant && star) { star.remove(); }
        else if (!isVacant && !star) {
            var s = document.createElement('span');
            s.className = 'star'; s.textContent = '*';
            nameLabel.appendChild(s);
        }
    }
    var roleLabel = document.querySelector('#role-control-group .control-label label');
    if (roleLabel) {
        var star = roleLabel.querySelector('.star');
        if (isVacant && !star) {
            var s = document.createElement('span');
            s.className = 'star'; s.textContent = '*';
            roleLabel.appendChild(s);
        } else if (!isVacant && star) {
            star.remove();
        }
    }
}
function toggleLeagueFields(type) { toggleTypeFields(type); }

// Live preview of the chosen photo so the upload can be confirmed immediately
// (before saving). Falls back gracefully if FileReader is unavailable.
function clblePreviewPhoto(input) {
    var box = document.getElementById('photo_preview');
    if (!box) { return; }
    if (!input.files || !input.files[0]) {
        return;
    }
    var file = input.files[0];
    var name = document.createElement('p');
    name.className = 'help-block';
    name.style.cssText = 'word-break:break-all;font-size:11px;margin-top:4px;';
    name.textContent = file.name + ' (' + (file.size ? Math.round(file.size / 1024) + ' KB' : '') + ')';

    if (box.querySelector('img')) { box.querySelector('img').remove(); }
    if (box.querySelector('.clble-photo-placeholder')) { box.querySelector('.clble-photo-placeholder').remove(); }
    var old = box.querySelector('p.help-block');
    if (old) { old.remove(); }

    if (window.FileReader && file.type.indexOf('image/') === 0) {
        var reader = new FileReader();
        reader.onload = function (e) {
            var img = document.createElement('img');
            img.src = e.target.result;
            img.alt = '';
            img.className = 'thumbnail';
            img.style.cssText = 'max-width:140px;max-height:140px;display:inline-block;vertical-align:middle;';
            box.appendChild(img);
            box.appendChild(name);
        };
        reader.readAsDataURL(file);
    } else {
        box.appendChild(name);
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

// Contact Component picker: com_contact's modal overwrites window.jSelectContact
// with its own version that discards the id, so we use a unique callback name
// passed via the modal's `function` URL param. The modal calls
// window.parent['jClubleaddirSelectContact'](id, title, null, null, uri, lang, null).
function jClubleaddirSelectContact(id, name) {
    var field = document.getElementById('contact_id');
    if (field) { field.value = id; }
    var disp = document.getElementById('contact_name_display');
    if (disp) { disp.textContent = name; }
    if (window.parent.SqueezeBox) { window.parent.SqueezeBox.close(); }
    else if (window.parent.jModalClose) { window.parent.jModalClose(); }
    return false;
}

// Initialise field visibility (league/role/staff toggles) on load.
(function () {
    var typeEl = document.getElementById('type');
    if (typeEl) {
        toggleTypeFields(typeEl.value);
    }
    var vacantEl = document.getElementById('vacant');
    if (vacantEl) {
        toggleVacantFields(vacantEl.checked);
    }
})();
</script>

<style>
/* Lightly themed cards to group sections (off-white, slightly different from white). */
.clble-card {
    background: #f7f8fa;
    border: 1px solid #e3e7ea;
    border-radius: 6px;
    padding: 16px 18px 4px;
    margin-bottom: 18px;
}
.clble-card > legend,
.clble-card-title {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #44515e;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin: 0 0 12px;
    padding-bottom: 6px;
    border-bottom: 1px solid #e3e7ea;
}
.clble-invalid { border-color: #b94a48 !important; box-shadow: 0 0 0 1px #b94a48 !important; }
/* Uniform field widths so the form reads consistently. */
.clble-w-main { width: 350px; max-width: 100%; }
.clble-w-short { width: 160px; max-width: 100%; }
.clble-edit-grid .control-label { width: 160px; }
.clble-edit-grid .controls { margin-left: 180px; }
.clble-contact-picked { font-size: 12px; color: #555; margin-top: 4px; }
.clble-help-note { font-size: 11px; color: #8a6d3b; margin-top: 4px; font-style: italic; }
/* Greyed-out (disabled) control group, e.g. Role/Title for league directors. */
.clble-disabled { opacity: 0.5; pointer-events: none; }
.clble-disabled .control-label label { color: #999; }
.clble-photo-col { text-align: center; }
.clble-photo-col .thumbnail,
.clble-photo-col .clble-photo-placeholder {
    width: 140px; height: 140px; line-height: 140px;
    text-align: center; color: #bbb; margin: 0 auto 8px;
    background: #eef0f2; border: 1px solid #e3e7ea; border-radius: 4px;
}
.clble-photo-col img.thumbnail { object-fit: cover; }

/* ---- Mobile / responsive (Bootstrap 2 breakpoint at 767px) ---- */
@media (max-width: 767px) {
    .clble-edit-grid .row-fluid .span3,
    .clble-edit-grid .row-fluid .span9,
    .clble-edit-grid .row-fluid .span6,
    .clble-edit-grid .row-fluid .span4,
    .clble-edit-grid .row-fluid .span8 {
        width: 100% !important;
        margin-left: 0 !important;
        float: none !important;
    }
    .clble-edit-grid .control-label {
        width: 100% !important;
        margin-bottom: 2px;
        text-align: left !important;
    }
    .clble-edit-grid .controls { margin-left: 0 !important; }
    /* Inputs fill the card on phones instead of fixed 350px. */
    .clble-w-main, .clble-w-short,
    .clble-edit-grid input[type="text"],
    .clble-edit-grid input[type="email"],
    .clble-edit-grid input[type="tel"],
    .clble-edit-grid input[type="number"],
    .clble-edit-grid select,
    .clble-edit-grid textarea {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box;
    }
    .clble-photo-col { text-align: left; }
    .input-append { display: flex; flex-wrap: wrap; }
    .input-append .btn { margin-top: 6px; }
}
</style>

<form action="<?php echo Route::_('index.php?option=com_clubleaddir&task=leadership.save'); ?>"
      method="post" name="adminForm" id="adminForm" class="form-validate" enctype="multipart/form-data">

    <div class="row-fluid clble-edit-grid">
        <!-- MAIN COLUMN -->
        <div class="span8">
            <fieldset class="adminform clble-card">
                <legend class="clble-card-title"><?php echo Text::_('COM_CLUBLEADDIR_LEADERSHIP_DETAILS'); ?></legend>

                <div class="row-fluid">
                    <div class="span3 clble-photo-col">
                        <div class="control-group">
                            <div class="controls">
                                <div id="photo_preview" style="margin-bottom:8px;">
                                    <?php if ($item->photo): ?>
                                        <img src="<?php echo $this->escape(ClubleaddirHelper::photoUrl($item->photo)); ?>" alt="<?php echo $this->escape($item->name); ?>"
                                             class="thumbnail" style="max-width:140px;max-height:140px;display:inline-block;vertical-align:middle;">
                                        <p class="help-block" style="word-break:break-all;font-size:11px;margin-top:4px;"><?php echo $this->escape(basename($item->photo)); ?></p>
                                    <?php else: ?>
                                        <div class="clble-photo-placeholder"><span class="icon-user" style="font-size:46px;"></span></div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="jform[photo]" id="photo" class="inputbox" accept="image/*" onchange="clblePreviewPhoto(this)">
                                <p class="help-block"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_PHOTO_HELP'); ?><?php if ($item->photo): ?> <span class="muted">(<?php echo Text::_('COM_CLUBLEADDIR_FIELD_PHOTO_REPLACE'); ?>)</span><?php endif; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="span9">
                        <div class="control-group">
                            <div class="control-label">
                                <label for="name" class="required"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_NAME'); ?> <span class="star">*</span></label>
                            </div>
                            <div class="controls">
                                <input type="text" name="jform[name]" id="name" class="inputbox clble-w-main" value="<?php echo $this->escape($item->name); ?>" required>
                            </div>
                        </div>

                        <div class="control-group">
                            <div class="control-label">
                                <label for="type" class="required"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_TYPE'); ?> <span class="star">*</span></label>
                            </div>
                            <div class="controls">
                                <select name="jform[type]" id="type" class="inputbox clble-w-main" required onchange="toggleTypeFields(this.value)">
                                    <option value="" <?php echo $item->type === '' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_SELECT_TYPE'); ?></option>
                                    <option value="officer" <?php echo $item->type === 'officer' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_OFFICER'); ?></option>
                                    <option value="director" <?php echo $item->type === 'director' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_DIRECTOR'); ?></option>
                                    <option value="director_league" <?php echo $item->type === 'director_league' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_DIRECTOR_LEAGUE'); ?></option>
                                    <option value="staff" <?php echo $item->type === 'staff' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_STAFF'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="control-group">
                            <div class="controls">
                                <label class="checkbox">
                                    <input type="checkbox" name="jform[vacant]" id="vacant" value="1" <?php echo (!empty($item->vacant) ? 'checked' : ''); ?>
                                           onchange="toggleVacantFields(this.checked);">
                                    <?php echo Text::_('COM_CLUBLEADDIR_FIELD_VACANT'); ?>
                                </label>
                                <span class="clble-help-note"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_VACANT_HELP'); ?></span>
                            </div>
                        </div>

                        <div class="control-group" id="role-control-group">
                            <div class="control-label">
                                <label for="role"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_ROLE'); ?></label>
                            </div>
                            <div class="controls">
                                <?php
                                $isOfficer = ($item->type === 'officer');
                                $isLeague  = ($item->type === 'director_league');
                                $officerRoleVal = array_key_exists($item->role, $officerRoles) ? $item->role : '';
                                ?>
                                <!-- Hidden carries the actual submitted value. -->
                                <input type="hidden" name="jform[role]" id="role" value="<?php echo $this->escape($item->role); ?>">
                                <!-- Officer: restricted dropdown. -->
                                <select id="role_select" class="inputbox clble-w-main" style="display:<?php echo $isOfficer ? 'block' : 'none'; ?>;"
                                        onchange="document.getElementById('role').value = this.value;">
                                    <option value=""><?php echo Text::_('COM_CLUBLEADDIR_SELECT_ROLE'); ?></option>
                                    <?php foreach ($officerRoles as $val => $label): ?>
                                        <option value="<?php echo $this->escape($val); ?>" <?php echo $officerRoleVal === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- Other types: free text. -->
                                <input type="text" id="role_text" class="inputbox clble-w-main" style="display:<?php echo (!$isOfficer && !$isLeague) ? 'block' : 'none'; ?>;"
                                       value="<?php echo $this->escape($item->role); ?>" placeholder="<?php echo Text::_('COM_CLUBLEADDIR_FIELD_ROLE_PLACEHOLDER'); ?>"
                                       oninput="document.getElementById('role').value = this.value;">
                                <?php if ($isLeague): ?>
                                    <p class="clble-help-note"><?php echo Text::_('COM_CLUBLEADDIR_ROLE_DISABLED_FOR_LEAGUE'); ?></p>
                                <?php endif; ?>
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
                            <select name="jform[league_name]" id="league_name" class="inputbox clble-w-main">
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
                        <input type="text" name="jform[term]" id="term" class="inputbox clble-w-short" value="<?php echo $this->escape($item->term ?: ($isEdit ? '' : $defaultTerm)); ?>" placeholder="2025-2027">
                    </div>
                </div>

                <div id="staff-fields" style="display:<?php echo $item->type === 'staff' ? 'block' : 'none'; ?>;">
                    <div class="control-group">
                        <div class="control-label">
                            <label for="start_year"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_START_YEAR'); ?></label>
                        </div>
                        <div class="controls">
                            <input type="number" name="jform[start_year]" id="start_year" class="inputbox clble-w-short" value="<?php echo (int) $item->start_year; ?>" placeholder="<?php echo date('Y'); ?>">
                            <p class="help-block"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_START_YEAR_HELP'); ?></p>
                        </div>
                    </div>
                    <div class="control-group">
                        <div class="control-label">
                            <label for="end_year"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_END_YEAR'); ?></label>
                        </div>
                        <div class="controls">
                            <input type="number" name="jform[end_year]" id="end_year" class="inputbox clble-w-short" value="<?php echo (int) $item->end_year; ?>" placeholder="<?php echo Text::_('COM_CLUBLEADDIR_FIELD_END_YEAR_CURRENT'); ?>">
                            <p class="help-block"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_END_YEAR_HELP'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="control-group">
                    <div class="control-label">
                        <label for="bio"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_BIO'); ?></label>
                    </div>
                    <div class="controls">
                        <textarea name="jform[bio]" id="bio" class="inputbox clble-w-main" rows="4" style="height:auto;"><?php echo $this->escape($item->bio); ?></textarea>
                    </div>
                </div>
            </fieldset>

            <fieldset class="adminform clble-card" id="contact-info-fieldset">
                <legend class="clble-card-title"><?php echo Text::_('COM_CLUBLEADDIR_CONTACT_INFO'); ?></legend>

                <div class="control-group">
                    <div class="control-label">
                        <label for="contact_id"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_CONTACT_ID'); ?></label>
                    </div>
                    <div class="controls">
                        <div class="input-append">
                            <input type="number" name="jform[contact_id]" id="contact_id" class="inputbox clble-w-short" style="width:140px;"
                                   value="<?php echo (int) $item->contact_id; ?>">
                            <?php if ($hasContactComponent): ?>
                            <a class="btn modal btn-contact-pick" title="<?php echo Text::_('COM_CLUBLEADDIR_FIELD_CONTACT_ID_HELP'); ?>"
                               href="<?php echo Route::_('index.php?option=com_contact&view=contacts&layout=modal&tmpl=component&function=jClubleaddirSelectContact'); ?>"
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

                <div class="control-group" id="vacancy-email-group">
                    <div class="control-label">
                        <label for="vacancy_email"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_VACANCY_EMAIL'); ?></label>
                    </div>
                    <div class="controls">
                        <input type="email" name="jform[vacancy_email]" id="vacancy_email" class="inputbox clble-w-main"
                               value="<?php echo $this->escape($item->vacancy_email ?? ''); ?>"
                               placeholder="<?php echo $this->escape(ClubleaddirHelper::vacancyEmail()); ?>">
                        <p class="help-block muted" style="font-size:11px;"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_VACANCY_EMAIL_HELP'); ?></p>
                    </div>
                </div>

                <div class="row-fluid">
                    <div class="span6">
                        <div class="control-group">
                            <div class="control-label">
                                <label for="email"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_EMAIL'); ?></label>
                            </div>
                            <div class="controls">
                                <input type="email" name="jform[email]" id="email" class="inputbox clble-w-main" value="<?php echo $this->escape($item->email); ?>">
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
                                <input type="tel" name="jform[phone]" id="phone" class="inputbox clble-w-main" value="<?php echo $this->escape($item->phone); ?>" placeholder="705-555-0100">
                            </div>
                        </div>
                    </div>
                </div>
            </fieldset>
        </div>

        <!-- SIDE COLUMN -->
        <div class="span4">
            <fieldset class="adminform clble-card">
                <legend class="clble-card-title"><?php echo Text::_('COM_CLUBLEADDIR_PUBLISHING'); ?></legend>

                <div class="control-group">
                    <div class="control-label">
                        <label for="status"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_BOARD_STATUS'); ?></label>
                    </div>
                    <div class="controls">
                        <select name="jform[status]" id="status" class="inputbox clble-w-main">
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
                        <select name="jform[published]" id="published" class="inputbox clble-w-main">
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
                        <input type="number" name="jform[ordering]" id="ordering" class="inputbox clble-w-short" value="<?php echo (int) $item->ordering; ?>">
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
