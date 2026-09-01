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

HTMLHelper::_('behavior.modal');
HTMLHelper::_('behavior.core');
HTMLHelper::stylesheet('com_clubleaddir/admin-edit.css', array('relative' => true));
HTMLHelper::script('com_clubleaddir/admin-edit.js', array('relative' => true));

$item   = $this->item;
$itemDefaults = array(
    'id' => 0, 'name' => '', 'type' => '', 'role' => '', 'league_name' => '',
    'term' => '', 'bio' => '', 'photo' => '', 'email' => '', 'phone' => '',
    'contact_id' => 0, 'vacant' => 0, 'ordering' => 0, 'published' => 1, 'status' => 'active',
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

$leagueOptions = array(
    'day_ladies'       => Text::_('COM_CLUBLEADDIR_LEAGUE_DAY_LADIES'),
    'evening_ladies'   => Text::_('COM_CLUBLEADDIR_LEAGUE_EVENING_LADIES'),
    'senior_men'       => Text::_('COM_CLUBLEADDIR_LEAGUE_SENIOR_MEN'),
);

$officerRoles = array(
    'President'       => Text::_('COM_CLUBLEADDIR_ROLE_PRESIDENT'),
    'Vice President'  => Text::_('COM_CLUBLEADDIR_ROLE_VICE_PRESIDENT'),
    'Secretary'       => Text::_('COM_CLUBLEADDIR_ROLE_SECRETARY'),
    'Treasurer'      => Text::_('COM_CLUBLEADDIR_ROLE_TREASURER'),
);

$thisYear  = (int) date('Y');
$thisMonth = (int) date('n');
if ($thisMonth >= 6) {
    $defaultTerm = $thisYear . '-' . ($thisYear + 1);
} else {
    $defaultTerm = ($thisYear - 1) . '-' . $thisYear;
}
?>

<form action="<?php echo Route::_('index.php?option=com_clubleaddir&task=leadership.save'); ?>" method="post" name="adminForm" id="adminForm" class="form-validate" enctype="multipart/form-data">

    <div class="row-fluid clble-edit-grid">

        <div class="span3"></div>

        <div class="span4">

            <fieldset class="clble-edit-section">
                <legend><?php echo Text::_('COM_CLUBLEADDIR_LEADERSHIP_DETAILS'); ?></legend>

                <div class="control-group">
                    <div class="control-label">
                        <label for="name" class="required"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_NAME'); ?> <span class="star">*</span></label>
                    </div>
                    <div class="controls">
                        <input type="text" name="jform[name]" id="name" class="inputbox" value="<?php echo $this->escape($item->name); ?>" required>
                    </div>
                </div>

                <div class="control-group">
                    <div class="control-label">
                        <label for="type" class="required"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_TYPE'); ?> <span class="star">*</span></label>
                    </div>
                    <div class="controls">
                        <select name="jform[type]" id="type" class="inputbox" required onchange="toggleTypeFields(this.value)">
                            <option value="" <?php echo $item->type === '' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_SELECT_TYPE'); ?></option>
                            <option value="officer" <?php echo $item->type === 'officer' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_OFFICER'); ?></option>
                            <option value="director" <?php echo $item->type === 'director' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_DIRECTOR'); ?></option>
                            <option value="director_league" <?php echo $item->type === 'director_league' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_DIRECTOR_LEAGUE'); ?></option>
                            <option value="staff" <?php echo $item->type === 'staff' ? 'selected' : ''; ?>><?php echo Text::_('COM_CLUBLEADDIR_TYPE_STAFF'); ?></option>
                        </select>
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
                        <input type="hidden" name="jform[role]" id="role" value="<?php echo $this->escape($item->role); ?>">
                        <select id="role_select" class="inputbox" style="display:<?php echo $isOfficer ? 'block' : 'none'; ?>;"
                                onchange="document.getElementById('role').value = this.value;">
                            <option value=""><?php echo Text::_('COM_CLUBLEADDIR_SELECT_ROLE'); ?></option>
                            <?php foreach ($officerRoles as $val => $label): ?>
                                <option value="<?php echo $this->escape($val); ?>" <?php echo $officerRoleVal === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="role_text" class="inputbox" style="display:<?php echo (!$isOfficer && !$isLeague) ? 'block' : 'none'; ?>;"
                               value="<?php echo $this->escape($item->role); ?>" placeholder="<?php echo Text::_('COM_CLUBLEADDIR_FIELD_ROLE_PLACEHOLDER'); ?>"
                               oninput="document.getElementById('role').value = this.value;">
                        <?php if ($isLeague): ?>
                            <p class="clble-help-note"><?php echo Text::_('COM_CLUBLEADDIR_ROLE_DISABLED_FOR_LEAGUE'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="control-group">
                    <div class="control-label">
                        <label for="term"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_TERM'); ?></label>
                    </div>
                    <div class="controls">
                        <input type="text" name="jform[term]" id="term" class="inputbox" value="<?php echo $this->escape($item->term ?: ($isEdit ? '' : $defaultTerm)); ?>" placeholder="2025-2027" maxlength="9">
                    </div>
                </div>

                <div class="control-group">
                    <div class="control-label">
                        <label for="vacant"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_VACANT'); ?></label>
                    </div>
                    <div class="controls">
                        <label class="checkbox" for="vacant">
                            <input type="checkbox" name="jform[vacant]" id="vacant" value="1" <?php echo $item->vacant ? 'checked' : ''; ?> onchange="toggleVacantFields(this.checked)">
                            <?php echo Text::_('COM_CLUBLEADDIR_FIELD_VACANT_DESC'); ?>
                        </label>
                    </div>
                </div>

                <div id="league-fields" style="display:<?php echo $item->type === 'director_league' ? 'block' : 'none'; ?>;">
                    <div class="control-group">
                        <div class="control-label">
                            <label for="league_name" class="required"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_LEAGUE_NAME'); ?> <span class="star">*</span></label>
                        </div>
                        <div class="controls">
                            <select name="jform[league_name]" id="league_name" class="inputbox">
                                <option value=""><?php echo Text::_('COM_CLUBLEADDIR_SELECT_LEAGUE'); ?></option>
                                <?php foreach ($leagueOptions as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo ($item->league_name ?? '') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="help-block"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_LEAGUE_NAME_HELP'); ?></p>
                        </div>
                    </div>
                </div>

                <div id="staff-fields" style="display:<?php echo $item->type === 'staff' ? 'block' : 'none'; ?>;">
                    <div class="control-group">
                        <div class="control-label">
                            <label for="start_year"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_START_YEAR'); ?></label>
                        </div>
                        <div class="controls">
                            <input type="number" name="jform[start_year]" id="start_year" class="inputbox" value="<?php echo (int) $item->start_year; ?>" placeholder="<?php echo date('Y'); ?>">
                            <p class="help-block"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_START_YEAR_HELP'); ?></p>
                        </div>
                    </div>
                    <div class="control-group">
                        <div class="control-label">
                            <label for="end_year"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_END_YEAR'); ?></label>
                        </div>
                        <div class="controls">
                            <input type="number" name="jform[end_year]" id="end_year" class="inputbox" value="<?php echo (int) $item->end_year; ?>" placeholder="<?php echo Text::_('COM_CLUBLEADDIR_FIELD_END_YEAR_CURRENT'); ?>">
                            <p class="help-block"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_END_YEAR_HELP'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="control-group">
                    <div class="control-label">
                        <label for="bio"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_BIO'); ?></label>
                    </div>
                    <div class="controls">
                        <textarea name="jform[bio]" id="bio" class="inputbox" rows="4"><?php echo $this->escape($item->bio); ?></textarea>
                    </div>
                </div>
            </fieldset>

            <fieldset class="clble-edit-section" id="contact-info-fieldset">
                <legend><?php echo Text::_('COM_CLUBLEADDIR_CONTACT_INFO'); ?></legend>

                <div class="control-group">
                    <div class="control-label">
                        <label for="email"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_EMAIL'); ?></label>
                    </div>
                    <div class="controls">
                        <input type="email" name="jform[email]" id="email" class="inputbox" value="<?php echo $this->escape($item->email); ?>">
                    </div>
                </div>

                <div class="control-group">
                    <div class="control-label">
                        <label for="phone"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_PHONE'); ?></label>
                    </div>
                    <div class="controls">
                        <input type="tel" name="jform[phone]" id="phone" class="inputbox" value="<?php echo $this->escape($item->phone); ?>" placeholder="705-555-0100" maxlength="20" oninput="clbleStripPhone(this)">
                    </div>
                </div>

                <div class="control-group">
                    <div class="control-label">
                        <label for="contact_id"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_CONTACT_ID'); ?></label>
                    </div>
                    <div class="controls">
                        <div class="input-append">
                            <input type="number" name="jform[contact_id]" id="contact_id" class="inputbox" style="width:100px;"
                                   value="<?php echo (int) $item->contact_id; ?>">
                            <?php if ($hasContactComponent): ?>
                            <a class="btn modal" title="<?php echo Text::_('COM_CLUBLEADDIR_FIELD_CONTACT_ID_HELP'); ?>"
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
            </fieldset>

        </div>

        <div class="span3">

            <fieldset class="clble-edit-section">
                <legend><?php echo Text::_('COM_CLUBLEADDIR_PHOTO'); ?></legend>
                <div class="control-group">
                    <div class="controls clble-photo-col">
                        <div id="photo_preview" style="margin-bottom:8px;">
                            <?php if ($item->photo): ?>
                                <img src="<?php echo $this->escape(ClubleaddirHelper::photoUrl($item->photo)); ?>" alt="<?php echo $this->escape($item->name); ?>"
                                     class="thumbnail" style="max-width:120px;max-height:120px;display:inline-block;vertical-align:middle;">
                                <p class="help-block" style="word-break:break-all;font-size:11px;margin-top:4px;"><?php echo $this->escape(basename($item->photo)); ?></p>
                            <?php else: ?>
                                <div class="clble-photo-placeholder"><span class="icon-user" style="font-size:40px;"></span></div>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="jform[photo]" id="photo" class="inputbox" accept="image/*" onchange="clblePreviewPhoto(this)">
                        <p class="help-block"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_PHOTO_HELP'); ?><?php if ($item->photo): ?> <span class="muted">(<?php echo Text::_('COM_CLUBLEADDIR_FIELD_PHOTO_REPLACE'); ?>)</span><?php endif; ?></p>
                    </div>
                </div>
            </fieldset>

            <fieldset class="clble-edit-section">
                <legend><?php echo Text::_('COM_CLUBLEADDIR_PUBLISHING'); ?></legend>

                <div class="control-group">
                    <div class="control-label">
                        <label for="status"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_BOARD_STATUS'); ?></label>
                    </div>
                    <div class="controls">
                        <select name="jform[status]" id="status" class="inputbox">
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
                        <select name="jform[published]" id="published" class="inputbox">
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
                        <input type="number" name="jform[ordering]" id="ordering" class="inputbox" value="<?php echo (int) $item->ordering; ?>">
                    </div>
                </div>

                <?php if ($isEdit): ?>
                <hr class="clble-section-divider">
                <table class="table table-condensed" style="margin-bottom:0;font-size:11px;">
                    <tbody>
                        <tr>
                            <td style="border-top:none;color:#666;width:40%;"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_ID'); ?></td>
                            <td style="border-top:none;"><?php echo (int) $item->id; ?></td>
                        </tr>
                        <tr>
                            <td style="border-top:none;color:#666;"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_CREATED'); ?></td>
                            <td style="border-top:none;"><?php echo $this->escape($item->created); ?></td>
                        </tr>
                        <tr>
                            <td style="border-top:none;color:#666;"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_MODIFIED'); ?></td>
                            <td style="border-top:none;"><?php echo $this->escape($item->modified); ?></td>
                        </tr>
                    </tbody>
                </table>
                <?php endif; ?>
            </fieldset>

        </div>

        <div class="span2"></div>
    </div>

    <input type="hidden" name="jform[id]" value="<?php echo (int) $item->id; ?>">
    <input type="hidden" name="task" value="leadership.save">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
