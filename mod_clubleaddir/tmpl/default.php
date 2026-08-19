<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$officers        = $rawData['officers']        ?? array();
$directors       = $rawData['directors']       ?? array();
$directorsLeague = $rawData['directors_league'] ?? array();
$staff           = $rawData['staff']           ?? array();

$hasContent = !empty($officers) || !empty($directors) || !empty($directorsLeague) || !empty($staff);
if (!$hasContent) {
    return;
}

function clubleaddirRenderCard($person, $showPhoto, $showContact, $contactHiddenText)
{
    $initials  = clubleaddirGetInitials($person->name);
    $photoHtml = '<div class="' . ($showPhoto ? 'clubleaddir-card-photo is-visible' : 'clubleaddir-card-photo') . '">';
    if (!empty($person->photo)) {
        $src = $person->photo;
        if ($src !== '' && $src[0] !== '/' && !preg_match('#^[a-z]+://#i', $src) && strpos($src, '//') !== 0) {
            $src = '/' . ltrim($src, '/');
        }
        $photoHtml .= '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy" width="120" height="120">';
    } else {
        $photoHtml .= '<div class="clubleaddir-card-photo--initials">' . $initials . '</div>';
    }
    $photoHtml .= '</div>';

    $metaHtml = '';
    if (!empty($person->role)) {
        $metaHtml .= '<div class="clubleaddir-card-role">' . htmlspecialchars($person->role, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (!empty($person->term)) {
        $metaHtml .= '<div class="clubleaddir-card-term">' . htmlspecialchars($person->term, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    $contactHtml = clubleaddirRenderContactHtml($person, $showContact, $contactHiddenText);

    return '<article class="clubleaddir-card clubleaddir-card--' . htmlspecialchars($person->type, ENT_QUOTES, 'UTF-8') . '">'
        . $photoHtml
        . '<div class="clubleaddir-card-content">'
        . '<h4 class="clubleaddir-card-name">' . htmlspecialchars($person->name, ENT_QUOTES, 'UTF-8') . '</h4>'
        . $metaHtml
        . $contactHtml
        . '</div>'
        . '</article>';
}

function clubleaddirRenderLeagueCard($person, $showContact, $contactHiddenText)
{
    $roleHtml   = '<div class="clubleaddir-card-role">' . Text::_('MOD_CLUBLEADDIRECTION_LEAGUE_REP_TITLE') . '</div>';
    $leagueHtml = '';
    if (!empty($person->league_name)) {
        $leagueHtml = '<div class="clubleaddir-card-league">' . htmlspecialchars($person->league_name, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    $contactHtml = clubleaddirRenderContactHtml($person, $showContact, $contactHiddenText);

    return '<article class="clubleaddir-card clubleaddir-card--director">'
        . '<div class="clubleaddir-card-photo"></div>'
        . '<div class="clubleaddir-card-content">'
        . '<h4 class="clubleaddir-card-name">' . htmlspecialchars($person->name, ENT_QUOTES, 'UTF-8') . '</h4>'
        . $roleHtml
        . $leagueHtml
        . $contactHtml
        . '</div>'
        . '</article>';
}

function clubleaddirRenderContactHtml($person, $showContact, $contactHiddenText)
{
    $email = $person->email ?? '';
    $phone = $person->phone ?? '';
    if (empty($email) && empty($phone)) {
        return '';
    }

    $html = '<div class="clubleaddir-card-contact">';
    if ($showContact) {
        if (!empty($email)) {
            $html .= '<a href="mailto:' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '" class="clubleaddir-contact-link">'
                . '<span class="icon-envelope" aria-hidden="true"></span>'
                . '<span class="clubleaddir-contact-text">Email</span></a>';
        }
        if (!empty($phone)) {
            $html .= '<a href="tel:' . htmlspecialchars(preg_replace('/[^0-9+]/', '', $phone), ENT_QUOTES, 'UTF-8') . '" class="clubleaddir-contact-link">'
                . '<span class="icon-phone" aria-hidden="true"></span>'
                . '<span class="clubleaddir-contact-text">' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</span></a>';
        }
    } else {
        $html .= '<span class="clubleaddir-contact-hidden">'
            . '<span class="icon-lock" aria-hidden="true"></span> '
            . htmlspecialchars($contactHiddenText, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    $html .= '</div>';
    return $html;
}

function clubleaddirGetInitials($name)
{
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }
    return strtoupper(mb_substr($parts[0], 0, 2));
}
?>

<div class="mod-clubleaddir<?php echo $moduleClassSfx ? ' ' . $moduleClassSfx : ''; ?>" data-module-id="<?php echo (int) $moduleId; ?>">
<div class="clubleaddir-wrapper">

    <?php if ($displayTitle): ?>
    <header class="clubleaddir-header">
        <h2 class="clubleaddir-title"><?php echo htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
        <?php if (!$showContact): ?>
        <p class="clubleaddir-login-notice">
            <span class="icon-lock" aria-hidden="true"></span>
            <?php echo htmlspecialchars($contactHiddenText, ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <?php endif; ?>
    </header>
    <?php endif; ?>

    <?php if ($showOfficers && !empty($officers)): ?>
    <section class="clubleaddir-section" aria-labelledby="officers-heading">
        <h3 id="officers-heading" class="clubleaddir-section-title">
            <span class="icon-star" aria-hidden="true"></span>
            <?php echo Text::_('MOD_CLUBLEADDIRECTION_OFFICERS'); ?>
        </h3>
        <div class="clubleaddir-grid clubleaddir-grid--officers">
            <?php foreach ($officers as $person): ?>
                <?php echo clubleaddirRenderCard($person, $showPhotosOfficers, $showContact, $contactHiddenText); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($showDirectors && (!empty($directors) || !empty($directorsLeague))): ?>
    <section class="clubleaddir-section" aria-labelledby="directors-heading">
        <h3 id="directors-heading" class="clubleaddir-section-title">
            <span class="icon-users" aria-hidden="true"></span>
            <?php echo Text::_('MOD_CLUBLEADDIRECTION_DIRECTORS'); ?>
        </h3>
        <?php if (!empty($directors)): ?>
        <div class="clubleaddir-grid clubleaddir-grid--directors">
            <?php foreach ($directors as $person): ?>
                <?php echo clubleaddirRenderCard($person, $showPhotosDirectors, $showContact, $contactHiddenText); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($directorsLeague)): ?>
        <div class="clubleaddir-subsection">
            <h4 class="clubleaddir-subsection-title"><?php echo Text::_('MOD_CLUBLEADDIRECTION_LEAGUE_APPOINTED_DIRECTORS'); ?></h4>
            <div class="clubleaddir-grid clubleaddir-grid--directors">
                <?php foreach ($directorsLeague as $person): ?>
                    <?php echo clubleaddirRenderLeagueCard($person, $showContact, $contactHiddenText); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($showStaff && !empty($staff)): ?>
    <section class="clubleaddir-section" aria-labelledby="staff-heading">
        <h3 id="staff-heading" class="clubleaddir-section-title">
            <span class="icon-cog" aria-hidden="true"></span>
            <?php echo Text::_('MOD_CLUBLEADDIRECTION_STAFF'); ?>
        </h3>
        <div class="clubleaddir-grid clubleaddir-grid--staff">
            <?php foreach ($staff as $person): ?>
                <?php echo clubleaddirRenderCard($person, $showPhotosStaff, $showContact, $contactHiddenText); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</div>
</div>
