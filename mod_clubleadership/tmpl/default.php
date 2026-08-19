<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_clubleadership
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

function clubleadershipRenderCard($person, $showPhoto, $showContact, $contactHiddenText)
{
    $initials  = clubleadershipGetInitials($person->name);
    $photoHtml = '<div class="' . ($showPhoto ? 'clubleadership-card-photo is-visible' : 'clubleadership-card-photo') . '">';
    if (!empty($person->photo)) {
        $photoHtml .= '<img src="' . htmlspecialchars($person->photo, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy" width="120" height="120">';
    } else {
        $photoHtml .= '<div class="clubleadership-card-photo--initials">' . $initials . '</div>';
    }
    $photoHtml .= '</div>';

    $metaHtml = '';
    if (!empty($person->role)) {
        $metaHtml .= '<div class="clubleadership-card-role">' . htmlspecialchars($person->role, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (!empty($person->term)) {
        $metaHtml .= '<div class="clubleadership-card-term">' . htmlspecialchars($person->term, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    $contactHtml = clubleadershipRenderContactHtml($person, $showContact, $contactHiddenText);

    return '<article class="clubleadership-card clubleadership-card--' . htmlspecialchars($person->type, ENT_QUOTES, 'UTF-8') . '">'
        . $photoHtml
        . '<div class="clubleadership-card-content">'
        . '<h4 class="clubleadership-card-name">' . htmlspecialchars($person->name, ENT_QUOTES, 'UTF-8') . '</h4>'
        . $metaHtml
        . $contactHtml
        . '</div>'
        . '</article>';
}

function clubleadershipRenderLeagueCard($person, $showContact, $contactHiddenText)
{
    $roleHtml   = '<div class="clubleadership-card-role">' . Text::_('MOD_CLUBLEADERSHIP_LEAGUE_REP_TITLE') . '</div>';
    $leagueHtml = '';
    if (!empty($person->league_name)) {
        $leagueHtml = '<div class="clubleadership-card-league">' . htmlspecialchars($person->league_name, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    $contactHtml = clubleadershipRenderContactHtml($person, $showContact, $contactHiddenText);

    return '<article class="clubleadership-card clubleadership-card--director">'
        . '<div class="clubleadership-card-photo"></div>'
        . '<div class="clubleadership-card-content">'
        . '<h4 class="clubleadership-card-name">' . htmlspecialchars($person->name, ENT_QUOTES, 'UTF-8') . '</h4>'
        . $roleHtml
        . $leagueHtml
        . $contactHtml
        . '</div>'
        . '</article>';
}

function clubleadershipRenderContactHtml($person, $showContact, $contactHiddenText)
{
    $email = $person->email ?? '';
    $phone = $person->phone ?? '';
    if (empty($email) && empty($phone)) {
        return '';
    }

    $html = '<div class="clubleadership-card-contact">';
    if ($showContact) {
        if (!empty($email)) {
            $html .= '<a href="mailto:' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '" class="clubleadership-contact-link">'
                . '<span class="icon-envelope" aria-hidden="true"></span>'
                . '<span class="clubleadership-contact-text">Email</span></a>';
        }
        if (!empty($phone)) {
            $html .= '<a href="tel:' . htmlspecialchars(preg_replace('/[^0-9+]/', '', $phone), ENT_QUOTES, 'UTF-8') . '" class="clubleadership-contact-link">'
                . '<span class="icon-phone" aria-hidden="true"></span>'
                . '<span class="clubleadership-contact-text">' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</span></a>';
        }
    } else {
        $html .= '<span class="clubleadership-contact-hidden">'
            . '<span class="icon-lock" aria-hidden="true"></span> '
            . htmlspecialchars($contactHiddenText, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    $html .= '</div>';
    return $html;
}

function clubleadershipGetInitials($name)
{
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }
    return strtoupper(mb_substr($parts[0], 0, 2));
}
?>

<div class="mod-clubleadership<?php echo $moduleClassSfx ? ' ' . $moduleClassSfx : ''; ?>" data-module-id="<?php echo (int) $moduleId; ?>">
<div class="clubleadership-wrapper">

    <?php if ($displayTitle): ?>
    <header class="clubleadership-header">
        <h2 class="clubleadership-title"><?php echo htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
        <?php if (!$showContact): ?>
        <p class="clubleadership-login-notice">
            <span class="icon-lock" aria-hidden="true"></span>
            <?php echo htmlspecialchars($contactHiddenText, ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <?php endif; ?>
    </header>
    <?php endif; ?>

    <?php if ($showOfficers && !empty($officers)): ?>
    <section class="clubleadership-section" aria-labelledby="officers-heading">
        <h3 id="officers-heading" class="clubleadership-section-title">
            <span class="icon-star" aria-hidden="true"></span>
            <?php echo Text::_('MOD_CLUBLEADERSHIP_OFFICERS'); ?>
        </h3>
        <div class="clubleadership-grid clubleadership-grid--officers">
            <?php foreach ($officers as $person): ?>
                <?php echo clubleadershipRenderCard($person, $showPhotosOfficers, $showContact, $contactHiddenText); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($showDirectors && (!empty($directors) || !empty($directorsLeague))): ?>
    <section class="clubleadership-section" aria-labelledby="directors-heading">
        <h3 id="directors-heading" class="clubleadership-section-title">
            <span class="icon-users" aria-hidden="true"></span>
            <?php echo Text::_('MOD_CLUBLEADERSHIP_DIRECTORS'); ?>
        </h3>
        <?php if (!empty($directors)): ?>
        <div class="clubleadership-grid clubleadership-grid--directors">
            <?php foreach ($directors as $person): ?>
                <?php echo clubleadershipRenderCard($person, $showPhotosDirectors, $showContact, $contactHiddenText); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($directorsLeague)): ?>
        <div class="clubleadership-subsection">
            <h4 class="clubleadership-subsection-title"><?php echo Text::_('MOD_CLUBLEADERSHIP_LEAGUE_APPOINTED_DIRECTORS'); ?></h4>
            <div class="clubleadership-grid clubleadership-grid--directors">
                <?php foreach ($directorsLeague as $person): ?>
                    <?php echo clubleadershipRenderLeagueCard($person, $showContact, $contactHiddenText); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($showStaff && !empty($staff)): ?>
    <section class="clubleadership-section" aria-labelledby="staff-heading">
        <h3 id="staff-heading" class="clubleadership-section-title">
            <span class="icon-cog" aria-hidden="true"></span>
            <?php echo Text::_('MOD_CLUBLEADERSHIP_STAFF'); ?>
        </h3>
        <div class="clubleadership-grid clubleadership-grid--staff">
            <?php foreach ($staff as $person): ?>
                <?php echo clubleadershipRenderCard($person, $showPhotosStaff, $showContact, $contactHiddenText); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</div>
</div>
