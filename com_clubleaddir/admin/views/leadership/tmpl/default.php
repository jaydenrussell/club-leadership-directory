<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

HTMLHelper::_('behavior.modal');
HTMLHelper::_('behavior.core');
HTMLHelper::stylesheet('com_clubleaddir/admin-edit.css', array('relative' => true, 'version' => 'auto'));
HTMLHelper::script('com_clubleaddir/admin-edit.js', array('relative' => true, 'version' => 'auto'));

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

/* Server-side initial visibility so toggles never flash before JS runs. */
$isOfficer = ($item->type === 'officer');
$isLeague  = ($item->type === 'director_league');
$roleShowSelect = $isOfficer;
$roleShowText   = (!$isOfficer && !$isLeague);
$officerRoleVal = array_key_exists($item->role, $officerRoles) ? $item->role : '';
$bioEnabled = !empty($item->bio);
?>

<form action="<?php echo Route::_('index.php?option=com_clubleaddir&task=leadership.save'); ?>" method="post" name="adminForm" id="adminForm" class="form-validate clble-edit-form" enctype="multipart/form-data">

    <div class="clble-form-shell">

        <div class="row-fluid">

            <div class="span6 clble-col-left">

                <fieldset class="form-horizontal">
                    <legend><?php echo Text::_('COM_CLUBLEADDIR_LEADERSHIP_DETAILS'); ?></legend>

                    <div class="control-group">
                        <div class="control-label">
                            <label for="name" class="required"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_NAME'); ?> <span class="star">*</span></label>
                        </div>
                        <div class="controls">
                            <input type="text" name="jform[name]" id="name" class="inputbox" value="<?php echo $this->escape($item->name); ?>" required maxlength="120">
                        </div>
                    </div>

                    <div class="control-group">
                        <div class="control-label">
                            <label for="type" class="required"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_TYPE'); ?> <span class="star">*</span></label>
                        </div>
                        <div class="controls">
                            <select name="jform[type]" id="type" class="inputbox" required>
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
                            <label for="role_select"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_ROLE'); ?></label>
                        </div>
                        <div class="controls">
                            <input type="hidden" name="jform[role]" id="role" value="<?php echo $this->escape($item->role); ?>">
                            <select id="role_select" class="inputbox"
                                    style="display:<?php echo $roleShowSelect ? 'block' : 'none'; ?>;">
                                <option value=""><?php echo Text::_('COM_CLUBLEADDIR_SELECT_ROLE'); ?></option>
                                <?php foreach ($officerRoles as $val => $label): ?>
                                    <option value="<?php echo $this->escape($val); ?>" <?php echo $officerRoleVal === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" id="role_text" class="inputbox"
                                   style="display:<?php echo $roleShowText ? 'block' : 'none'; ?>;"
                                   value="<?php echo $this->escape($item->role); ?>" placeholder="<?php echo Text::_('COM_CLUBLEADDIR_FIELD_ROLE_PLACEHOLDER'); ?>">
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
                                <input type="checkbox" name="jform[vacant]" id="vacant" value="1" <?php echo $item->vacant ? 'checked' : ''; ?>>
                                <?php echo Text::_('COM_CLUBLEADDIR_FIELD_VACANT_DESC'); ?>
                            </label>
                        </div>
                    </div>

                    <div id="league-fields" style="display:<?php echo $isLeague ? 'block' : 'none'; ?>;">
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
                </fieldset>

                <fieldset class="form-horizontal" id="contact-info-fieldset">
                    <legend><?php echo Text::_('COM_CLUBLEADDIR_CONTACT_INFO'); ?></legend>

                    <div class="control-group">
                        <div class="control-label">
                            <label for="email"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_EMAIL'); ?></label>
                        </div>
                        <div class="controls">
                            <input type="email" name="jform[email]" id="email" class="inputbox" value="<?php echo $this->escape($item->email); ?>" maxlength="254">
                        </div>
                    </div>

                    <div class="control-group">
                        <div class="control-label">
                            <label for="phone"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_PHONE'); ?></label>
                        </div>
                        <div class="controls">
                            <input type="tel" name="jform[phone]" id="phone" class="inputbox" value="<?php echo $this->escape($item->phone); ?>" placeholder="705-555-0100" maxlength="30">
                        </div>
                    </div>

                    <div class="control-group">
                        <div class="control-label">
                            <label for="contact_id"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_CONTACT_ID'); ?></label>
                        </div>
                        <div class="controls">
                            <div class="input-append">
                                <input type="number" name="jform[contact_id]" id="contact_id" class="inputbox"
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
                                        <a class="btn btn-small clble-btn-margin-left"
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

            <div class="span6 clble-col-right">

<div class="well">
    <fieldset>
        <legend><?php echo Text::_('COM_CLUBLEADDIR_PHOTO'); ?></legend>
                <?php
                $photoForm = Form::getInstance(
                    'com_clubleaddir.leadership.photo',
                    JPATH_COMPONENT_ADMINISTRATOR . '/forms/leadership.xml',
                    array('control' => 'jform')
                );
                $photoForm->bind((array) $item);
                echo $photoForm->renderField('photo');
                ?>
    </fieldset>
</div>

            <div class="well">
                    <fieldset>
                        <legend><?php echo Text::_('COM_CLUBLEADDIR_FIELD_BIO'); ?></legend>
                        <div class="control-group">
                            <div class="controls">
                                <label class="checkbox" for="bio_enabled">
                                    <input type="checkbox" id="bio_enabled" value="1" <?php echo $bioEnabled ? 'checked' : ''; ?>>
                                    <?php echo Text::_('COM_CLUBLEADDIR_FIELD_BIO_TOGGLE'); ?>
                                </label>
                            </div>
                        </div>
                        <div id="bio-wrap"<?php echo $bioEnabled ? '' : ' style="display:none;"'; ?>>
                            <div class="control-group">
                                <div class="controls">
                                    <textarea name="jform[bio]" id="bio" class="inputbox" rows="5" maxlength="5000"><?php echo $this->escape($item->bio); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <div class="well">
                    <fieldset>
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
                        <table class="table table-condensed clble-table-compact">
                            <tbody>
                                <tr>
                                    <td class="clble-table-meta clble-table-meta-width"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_ID'); ?></td>
                                    <td class="clble-table-meta"><?php echo (int) $item->id; ?></td>
                                </tr>
                                <tr>
                                    <td class="clble-table-meta"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_CREATED'); ?></td>
                                    <td class="clble-table-meta"><?php echo $this->escape($item->created); ?></td>
                                </tr>
                                <tr>
                                    <td class="clble-table-meta"><?php echo Text::_('COM_CLUBLEADDIR_FIELD_MODIFIED'); ?></td>
                                    <td class="clble-table-meta"><?php echo $this->escape($item->modified); ?></td>
                                </tr>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </fieldset>
                </div>

            </div>
        </div>
    </div>

    <input type="hidden" name="jform[id]" value="<?php echo (int) $item->id; ?>">
    <input type="hidden" name="task" value="leadership.save">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>