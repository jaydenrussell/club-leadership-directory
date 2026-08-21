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

function clubleaddirGetInitials($name)
{
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }
    return strtoupper(mb_substr($parts[0], 0, 2));
}

function clubleaddirRenderCard($person, $showPhoto, $showContact, $contactHiddenText, $showTerm, $circular = 1, $photoSize = 120, $vacantContactId = 0)
{
    $initials  = clubleaddirGetInitials($person->name);
    $size      = (int) $photoSize;
    if ($size < 40) { $size = 40; }
    if ($size > 320) { $size = 320; }

    $hasPhoto  = !empty($person->photo);
    $isOfficer = ($person->type === 'officer');
    $isVacant  = !empty($person->vacant);
    // Photo box only appears when this section's photo toggle is ON. Officers
    // always get a photo slot (real photo or initials); a vacant post shows the
    // club logo; directors/staff only show a box when they actually have a photo.
    $showPhotoBox = $showPhoto && ($hasPhoto || $isOfficer || $isVacant);

    // When a vacant post has no named person, the role IS the title (so we don't
    // print "Vacant" twice — the pill handles that).
    $nameEmpty = $isVacant && empty(trim($person->name ?? ''));
    $displayName = $nameEmpty ? ($person->role ?? '') : $person->name;
    // Vacant photo (club logo) is shown at 75% of the normal photo size.
    $logoSize = (int) round($size * 0.75);

    // Circular avatar uses the square crop; non-circular shows the original upload.
    $photoSrc = (!empty($person->photo) && $circular)
        ? $person->photo
        : (!empty($person->photo_full) ? $person->photo_full : $person->photo);

    $shapeClass = $circular ? 'is-circular' : 'is-rect';
    $photoHtml = '';
    if ($showPhotoBox) {
        $boxSize = $isVacant ? $logoSize : $size;
        $photoHtml = '<div class="clubleadership-card-photo ' . $shapeClass . ' is-visible" style="width:' . $boxSize . 'px;height:' . $boxSize . 'px;">';
        if (!empty($photoSrc)) {
            $src = $photoSrc;
            if ($src !== '' && $src[0] !== '/' && !preg_match('#^[a-z]+://#i', $src) && strpos($src, '//') !== 0) {
                $src = '/' . ltrim($src, '/');
            }
            $photoHtml .= '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy" width="' . $boxSize . '" height="' . $boxSize . '">';
        } elseif ($isVacant) {
            $logo = method_exists('ClubleaddirHelper', 'vacantLogo')
                ? ClubleaddirHelper::vacantLogo()
                : 'https://simcoecurlingclub.ca/images/Logo/simcoe_curling_club_logo.svg';
            $photoHtml .= '<img src="' . htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy" width="' . $logoSize . '" height="' . $logoSize . '" style="object-fit:contain;background:#fff;">';
        } elseif ($isOfficer) {
            $photoHtml .= '<div class="clubleadership-card-photo--initials">' . $initials . '</div>';
        }
        $photoHtml .= '</div>';
    }

    $metaHtml = '';
    if (!empty($person->role) && !$nameEmpty) {
        $metaHtml .= '<div class="clubleadership-card-role">' . htmlspecialchars($person->role, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if ($showTerm) {
        if ($person->type === 'staff') {
            $sy = (int) ($person->start_year ?? 0);
            $ey = (int) ($person->end_year ?? 0);
            if ($sy > 0) {
                $end = $ey > 0 ? (string) $ey : Text::_('MOD_CLUBLEADDIRECTION_EMPLOYED_CURRENT');
                $metaHtml .= '<div class="clubleadership-card-term">' . htmlspecialchars($sy . ' &ndash; ' . $end, ENT_QUOTES, 'UTF-8') . '</div>';
            }
        } elseif (!empty($person->term)) {
            $metaHtml .= '<div class="clubleadership-card-term">' . htmlspecialchars($person->term, ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }
    if ($isVacant) {
        $metaHtml .= '<span class="clubleadership-card-vacant">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANT'), ENT_QUOTES, 'UTF-8') . '</span>';
    }

    $contactHtml = clubleaddirRenderContactHtml($person, $showContact, $contactHiddenText, $vacantContactId);

    return '<article class="clubleadership-card clubleaddir-card--' . htmlspecialchars($person->type, ENT_QUOTES, 'UTF-8') . ($isVacant ? ' clubleaddir-card--vacant' : '') . '">'
        . $photoHtml
        . '<div class="clubleadership-card-content">'
        . '<h4 class="clubleadership-card-name">' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '</h4>'
        . $metaHtml
        . $contactHtml
        . '</div>'
        . '</article>';
}

function clubleaddirRenderLeagueCard($person, $showContact, $contactHiddenText, $vacantContactId = 0)
{
    $isVacant  = !empty($person->vacant);
    $nameEmpty = $isVacant && empty(trim($person->name ?? ''));
    $displayName = $nameEmpty ? ($person->role ?? Text::_('MOD_CLUBLEADDIRECTION_LEAGUE_REP_TITLE')) : $person->name;
    $roleHtml   = '<div class="clubleadership-card-role">' . Text::_('MOD_CLUBLEADDIRECTION_LEAGUE_REP_TITLE') . '</div>';
    $leagueHtml = '';
    if (!empty($person->league_name)) {
        $leagueHtml = '<div class="clubleadership-card-league">' . htmlspecialchars($person->league_name, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    $metaHtml = $roleHtml . $leagueHtml;
    if ($isVacant) {
        $metaHtml .= '<span class="clubleadership-card-vacant">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANT'), ENT_QUOTES, 'UTF-8') . '</span>';
    }
    $contactHtml = clubleaddirRenderContactHtml($person, $showContact, $contactHiddenText, $vacantContactId);

    return '<article class="clubleadership-card clubleaddir-card--director' . ($isVacant ? ' clubleaddir-card--vacant' : '') . '">'
        . '<div class="clubleadership-card-photo"></div>'
        . '<div class="clubleadership-card-content">'
        . '<h4 class="clubleadership-card-name">' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '</h4>'
        . $metaHtml
        . $contactHtml
        . '</div>'
        . '</article>';
}

function clubleaddirRenderContactHtml($person, $showContact, $contactHiddenText, $vacantContactId = 0)
{
    // Delegate to the shared helper so the module and the component view render
    // contact (Joomla Contact link, vacancy Apply/Inquire, or email/phone) identically.
    if (!class_exists('ClubleaddirHelper', false)) {
        require_once JPATH_ADMINISTRATOR . '/components/com_clubleaddir/helpers.php';
    }
    return ClubleaddirHelper::contactHtml($person, $showContact, $contactHiddenText, $vacantContactId);
}
?>
<style>
/* Club Leadership Directory — front-end styling (scoped to this module only). */
.mod-clubleadership, .mod-clubleadership * { box-sizing: border-box; }
.mod-clubleadership {
    font-family: 'Muli', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    color: #555;
    line-height: 1.6;
    width: 100%;
}
.mod-clubleadership .clubleadership-header {
    text-align: center;
    padding: 2rem 1.5rem 1.25rem;
    max-width: 1100px;
    margin: 0 auto;
}
.mod-clubleadership .clubleadership-title {
    font-size: 1.6rem;
    font-weight: 700;
    color: #15324A;
    margin: 0;
    letter-spacing: 0.02em;
}
.mod-clubleadership .clubleadership-title-accent {
    display: block;
    width: 60px;
    height: 3px;
    background: #b8963e;
    border: none;
    border-radius: 2px;
    margin: 0.75rem auto 0;
}
.mod-clubleadership .clubleadership-login-notice {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.75rem;
    font-size: 0.8rem;
    color: #999;
}
.mod-clubleadership .clubleadership-login-notice .icon-lock { color: #305789; font-size: 0.9rem; }
.mod-clubleadership .clubleadership-section { padding: 1.75rem 1rem; }
.mod-clubleadership .clubleadership-section:nth-of-type(odd) { background: #fff; }
.mod-clubleadership .clubleadership-section:nth-of-type(even) { background: #f5f7fa; }
.mod-clubleadership .clubleadership-section-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 1.05rem;
    font-weight: 700;
    color: #15324A;
    margin: 0 auto 1rem;
    max-width: 1100px;
    padding-bottom: 0.4rem;
    border-bottom: 2px solid #305789;
}
.mod-clubleadership .section-icon { color: #b8963e; font-size: 1em; }
.mod-clubleadership .clubleadership-subsection { margin-top: 1.25rem; }
.mod-clubleadership .clubleadership-subsection-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #305789;
    margin: 0 auto 0.75rem;
    max-width: 1100px;
    padding-left: 0.5rem;
    border-left: 3px solid #b8963e;
}
.mod-clubleadership .clubleadership-grid { display: grid; gap: 1.25rem; max-width: 1100px; margin: 0 auto; }
.mod-clubleadership .grid-officers { grid-template-columns: repeat(4, 1fr); gap: 1rem; }
.mod-clubleadership .grid-directors { grid-template-columns: repeat(4, 230px); justify-content: space-evenly; }
.mod-clubleadership .grid-staff { grid-template-columns: repeat(4, 230px); justify-content: space-evenly; }
.mod-clubleadership .clubleadership-card {
    position: relative;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(21, 50, 74, 0.08);
    border: 1px solid #d5dfe8;
    overflow: hidden;
    transition: box-shadow 0.2s, transform 0.2s;
}
.mod-clubleadership .clubleadership-card:hover {
    box-shadow: 0 6px 24px rgba(21, 50, 74, 0.14);
    transform: translateY(-2px);
}
.mod-clubleadership .clubleaddir-card--officer { text-align: center; }
.mod-clubleadership .clubleaddir-card--director,
.mod-clubleadership .clubleaddir-card--staff { display: flex; flex-direction: column; min-height: 110px; align-items: center; text-align: center; }
.mod-clubleadership .clubleaddir-card--director.has-photo,
.mod-clubleadership .clubleaddir-card--staff.has-photo { height: 190px; }
.mod-clubleadership .clubleadership-card-photo { position: relative; overflow: hidden; background: #f5f7fa; display: none; align-items: center; justify-content: center; }
.mod-clubleadership .clubleadership-card-photo.is-visible { display: flex; }
.mod-clubleadership .clubleadership-card-photo.is-circular { border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(21,50,74,0.12); }
.mod-clubleadership .clubleadership-card-photo.is-rect { border-radius: 8px; border: 1px solid #d5dfe8; }
.mod-clubleadership .clubleadership-card-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
.mod-clubleadership .clubleaddir-card--officer .clubleadership-card-photo { margin: 0.875rem auto 0; }
.mod-clubleadership .clubleadership-card-photo--initials {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #305789;
    color: #fff;
    font-size: 1.1rem;
    font-weight: 700;
}
.mod-clubleadership .clubleadership-card-content {
    padding: 0.625rem 0.75rem 0.75rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
}
.mod-clubleadership .clubleadership-card-vacant {
    display: inline-block;
    margin-top: 0.25rem;
    padding: 0.1rem 0.5rem;
    border-radius: 50px;
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    background: #b8963e;
    color: #fff;
}
.mod-clubleadership .clubleaddir-card--officer .clubleadership-card-content { padding: 0.5rem 0.75rem 0.875rem; justify-content: flex-start; }
.mod-clubleadership .clubleadership-card-name { font-size: 0.8rem; font-weight: 600; color: #1a2a3a; margin: 0 0 0.1rem; line-height: 1.3; }
.mod-clubleadership .clubleaddir-card--officer .clubleadership-card-name { font-size: 1rem; margin-top: 0.35rem; }
.mod-clubleadership .clubleadership-card-role { font-size: 0.7rem; font-weight: 600; color: #305789; }
.mod-clubleadership .clubleaddir-card--officer .clubleadership-card-role { color: #b8963e; font-size: 0.8rem; }
.mod-clubleadership .clubleadership-card-department { font-size: 0.68rem; color: #555; }
.mod-clubleadership .clubleadership-card-term { font-size: 0.65rem; color: #999; }
.mod-clubleadership .clubleadership-card-league { font-size: 0.68rem; color: #555; font-style: italic; margin-top: 0.1rem; }
.mod-clubleadership .clubleadership-card-contact {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    margin-top: 0.4rem;
    padding-top: 0.4rem;
    border-top: 1px solid #d5dfe8;
}
.mod-clubleadership .clubleaddir-card--officer .clubleadership-card-contact { justify-content: center; }
.mod-clubleadership .clubleadership-contact-link {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.2rem 0.45rem;
    background: #e8f0f8;
    color: #305789;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.2s, color 0.2s;
}
.mod-clubleadership .clubleadership-contact-link:hover { background: #305789; color: #fff; }
.mod-clubleadership .clubleadership-contact-text { display: none; }
@media (min-width: 400px) { .mod-clubleadership .clubleadership-contact-text { display: inline; } }
.mod-clubleadership .clubleadership-contact-hidden { display: inline-flex; align-items: center; gap: 0.2rem; font-size: 0.65rem; color: #bbb; }
.mod-clubleadership .clubleadership-contact-hidden .icon-lock { color: #305789; }
@media (max-width: 1100px) {
    .mod-clubleadership .grid-officers { grid-template-columns: repeat(2, 1fr); }
    .mod-clubleadership .grid-directors { grid-template-columns: repeat(3, 1fr); }
    .mod-clubleadership .grid-staff { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .mod-clubleadership .grid-officers { grid-template-columns: repeat(2, 1fr); }
    .mod-clubleadership .grid-directors { grid-template-columns: repeat(2, 1fr); }
    .mod-clubleadership .grid-staff { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .mod-clubleadership .grid-officers,
    .mod-clubleadership .grid-directors { grid-template-columns: 1fr; }
}
/* Vacancy recruitment banner */
.mod-clubleadership .clubleaddir-vacancy-banner {
    display: flex;
    align-items: center;
    gap: 1rem;
    max-width: 1100px;
    margin: 0 auto 1.5rem;
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, #e3ebf5 0%, #f4f8fc 100%);
    border: 1px solid #c5d8ee;
    border-left: 5px solid #1890d7;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(24, 144, 215, 0.12);
}
.mod-clubleadership .clubleaddir-vacancy-banner-icon {
    flex: 0 0 auto;
    width: 46px; height: 46px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem;
    background: #1890d7;
    color: #fff;
    border-radius: 50%;
}
.mod-clubleadership .clubleaddir-vacancy-banner-body { flex: 1 1 auto; }
.mod-clubleadership .clubleaddir-vacancy-banner-title {
    margin: 0 0 0.25rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f5b8a;
}
.mod-clubleadership .clubleaddir-vacancy-banner-text {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.4;
    color: #305789;
}
.mod-clubleadership .clubleaddir-vacancy-banner-cta {
    flex: 0 0 auto;
    display: inline-block;
    padding: 0.6rem 1.25rem;
    background: #1890d7;
    color: #fff !important;
    font-weight: 600;
    text-decoration: none;
    border-radius: 999px;
    white-space: nowrap;
    transition: background 0.2s, transform 0.2s;
}
.mod-clubleadership .clubleaddir-vacancy-banner-cta:hover {
    background: #0f5b8a;
    transform: translateY(-1px);
}
@media (max-width: 560px) {
    .mod-clubleadership .clubleaddir-vacancy-banner { flex-wrap: wrap; }
    .mod-clubleadership .clubleaddir-vacancy-banner-cta { flex: 1 1 100%; text-align: center; }
}
</style>

<div class="mod-clubleadership<?php echo $moduleClassSfx ? ' ' . $moduleClassSfx : ''; ?>">
    <?php if ($displayTitle): ?>
    <header class="clubleadership-header">
        <<?php echo $headerTag; ?> class="clubleadership-title"><?php echo htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8'); ?></<?php echo $headerTag; ?>>
        <hr class="clubleadership-title-accent" />
        <?php if ($introText !== ''): ?>
        <p class="clubleadership-intro"><?php echo $introText; ?></p>
        <?php endif; ?>
        <?php if (!$showContact): ?>
        <p class="clubleadership-login-notice">
            <span class="icon-lock" aria-hidden="true"></span>
            <?php echo htmlspecialchars($contactHiddenText, ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <?php endif; ?>
    </header>
    <?php endif; ?>

    <?php
    // Recruitment banner when at least one published position is vacant.
    $anyVacant = false;
    foreach (array($officers, $directors, $directorsLeague, $staff) as $grp) {
        if (!empty($grp)) {
            foreach ($grp as $gp) {
                if (!empty($gp->vacant) && (!isset($gp->published) || (int) $gp->published === 1)) {
                    $anyVacant = true;
                    break 2;
                }
            }
        }
    }
    if ($anyVacant):
        echo ClubleaddirHelper::vacancyBannerHtml(
            $vacantContactId,
            (string) ($paramsData->get('vacancy_default_email', 'info@simcoecurlingclub.ca'))
        );
    endif;
    ?>

    <?php if ($showOfficers && !empty($officers)): ?>
    <section class="clubleadership-section">
        <?php if ($showSectionTitles): ?><h3 class="clubleadership-section-title">
            <span class="section-icon" aria-hidden="true">&#9733;</span>
            <?php echo Text::_('MOD_CLUBLEADDIRECTION_OFFICERS'); ?>
        </h3><?php endif; ?>
        <div class="clubleadership-grid grid-officers">
            <?php foreach ($officers as $person): ?>
                <?php echo clubleaddirRenderCard($person, $showPhotosOfficers, $showContact, $contactHiddenText, $showTerm, $circularAvatars, $photoSize, $vacantContactId); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($showDirectors && (!empty($directors) || !empty($directorsLeague))): ?>
    <section class="clubleadership-section">
        <?php if ($showSectionTitles): ?><h3 class="clubleadership-section-title">
            <span class="section-icon" aria-hidden="true">&#128101;</span>
            <?php echo Text::_('MOD_CLUBLEADDIRECTION_DIRECTORS'); ?>
        </h3><?php endif; ?>
        <?php if (!empty($directors)): ?>
        <div class="clubleadership-grid grid-directors">
            <?php foreach ($directors as $person): ?>
                <?php echo clubleaddirRenderCard($person, $showPhotosDirectors, $showContact, $contactHiddenText, $showTerm, $circularAvatars, $photoSize, $vacantContactId); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($directorsLeague)): ?>
        <div class="clubleadership-subsection">
            <h4 class="clubleadership-subsection-title"><?php echo Text::_('MOD_CLUBLEADDIRECTION_LEAGUE_APPOINTED_DIRECTORS'); ?></h4>
            <div class="clubleadership-grid grid-directors">
                <?php foreach ($directorsLeague as $person): ?>
                    <?php echo clubleaddirRenderLeagueCard($person, $showContact, $contactHiddenText, $vacantContactId); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($showStaff && !empty($staff)): ?>
    <section class="clubleadership-section">
        <?php if ($showSectionTitles): ?><h3 class="clubleadership-section-title">
            <span class="section-icon" aria-hidden="true">&#9881;</span>
            <?php echo Text::_('MOD_CLUBLEADDIRECTION_STAFF'); ?>
        </h3><?php endif; ?>
        <div class="clubleadership-grid grid-staff">
            <?php foreach ($staff as $person): ?>
                <?php echo clubleaddirRenderCard($person, $showPhotosStaff, $showContact, $contactHiddenText, $showTerm, $circularAvatars, $photoSize, $vacantContactId); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>
