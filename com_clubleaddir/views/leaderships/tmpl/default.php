<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

require_once JPATH_ADMINISTRATOR . '/components/com_clubleaddir/helpers.php';

function clubleaddirSitePhoto($path)
{
    if ($path === '' || $path === null) {
        return '';
    }
    if ($path[0] !== '/' && !preg_match('#^[a-z]+://#i', $path) && strpos($path, '//') !== 0) {
        $path = '/' . ltrim($path, '/');
    }
    return $path;
}

$sections = array(
    'officers'         => Text::_('MOD_CLUBLEADDIRECTION_OFFICERS'),
    'directors'        => Text::_('MOD_CLUBLEADDIRECTION_DIRECTORS'),
    'directors_league' => Text::_('MOD_CLUBLEADDIRECTION_LEAGUE_APPOINTED_DIRECTORS'),
    'staff'            => Text::_('MOD_CLUBLEADDIRECTION_STAFF'),
);

// Per-section photo display (from menu params, defaulting to the same
// convention the module uses: officers on, directors/staff off).
$showPhotos = array(
    'officers'         => (int) ($this->params->get('show_photos_officers', 1)),
    'directors'        => (int) ($this->params->get('show_photos_directors', 0)),
    'directors_league' => (int) ($this->params->get('show_photos_directors', 0)),
    'staff'            => (int) ($this->params->get('show_photos_staff', 0)),
);

$icon = array(
    'officers'         => '&#9733;',
    'directors'        => '&#128101;',
    'directors_league' => '&#128101;',
    'staff'            => '&#9881;',
);
?><style>
/* Club Leadership Directory — front-end styling (scoped to this view only). */
.com-clubleaddir, .com-clubleaddir * { box-sizing: border-box; }
.com-clubleaddir {
    font-family: 'Muli', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    color: #555;
    line-height: 1.6;
    width: 100%;
}
.com-clubleaddir .clubleadership-section { padding: 1.75rem 1rem; }
.com-clubleaddir .clubleadership-section:nth-of-type(odd) { background: #fff; }
.com-clubleaddir .clubleadership-section:nth-of-type(even) { background: #f5f7fa; }
.com-clubleaddir .clubleadership-section-title {
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
.com-clubleaddir .section-icon { color: #b8963e; font-size: 1em; }
.com-clubleaddir .clubleadership-subsection { margin-top: 1.25rem; }
.com-clubleaddir .clubleadership-subsection-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #305789;
    margin: 0 auto 0.75rem;
    max-width: 1100px;
    padding-left: 0.5rem;
    border-left: 3px solid #b8963e;
}
.com-clubleaddir .clubleadership-grid { display: grid; gap: 1.25rem; max-width: 1100px; margin: 0 auto; }
.com-clubleaddir .grid-officers { grid-template-columns: repeat(4, 1fr); gap: 1rem; }
.com-clubleaddir .grid-directors { grid-template-columns: repeat(4, 230px); justify-content: space-evenly; }
.com-clubleaddir .grid-staff { grid-template-columns: repeat(4, 230px); justify-content: space-evenly; }
.com-clubleaddir .clubleadership-card {
    position: relative;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(21, 50, 74, 0.08);
    border: 1px solid #d5dfe8;
    overflow: hidden;
    transition: box-shadow 0.2s, transform 0.2s;
    text-align: center;
}
.com-clubleaddir .clubleadership-card:hover {
    box-shadow: 0 6px 24px rgba(21, 50, 74, 0.14);
    transform: translateY(-2px);
}
.com-clubleaddir .clubleaddir-card--officer { }
.com-clubleaddir .clubleaddir-card--director,
.com-clubleaddir .clubleaddir-card--staff {
    display: flex;
    flex-direction: column;
    min-height: 110px;
    align-items: center;
}
.com-clubleaddir .clubleadership-card-photo {
    position: relative;
    overflow: hidden;
    background: #f5f7fa;
    display: none;
    align-items: center;
    justify-content: center;
}
.com-clubleaddir .clubleadership-card-photo.is-visible { display: flex; }
.com-clubleaddir .clubleadership-card-photo.is-circular { border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(21,50,74,0.12); }
.com-clubleaddir .clubleadership-card-photo.is-rect { border-radius: 8px; border: 1px solid #d5dfe8; }
.com-clubleaddir .clubleadership-card-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
.com-clubleaddir .clubleaddir-card--officer .clubleadership-card-photo {
    width: 120px; height: 120px;
    margin: 0.875rem auto 0;
}
.com-clubleaddir .clubleadership-card-photo--initials {
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
.com-clubleaddir .clubleadership-card-content {
    padding: 0.625rem 0.75rem 0.75rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
}
.com-clubleaddir .clubleadership-card-name { font-size: 0.8rem; font-weight: 600; color: #1a2a3a; margin: 0 0 0.1rem; line-height: 1.3; }
.com-clubleaddir .clubleaddir-card--officer .clubleadership-card-name { font-size: 1rem; margin-top: 0.35rem; }
.com-clubleaddir .clubleadership-card-role { font-size: 0.7rem; font-weight: 600; color: #305789; }
.com-clubleaddir .clubleaddir-card--officer .clubleadership-card-role { color: #b8963e; font-size: 0.8rem; }
.com-clubleaddir .clubleadership-card-term { font-size: 0.65rem; color: #999; }
.com-clubleaddir .clubleadership-card-league { font-size: 0.68rem; color: #555; font-style: italic; margin-top: 0.1rem; }
.com-clubleaddir .clubleadership-card-vacant {
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
.com-clubleaddir .clubleadership-card-contact {
    display: flex; flex-wrap: wrap; gap: 0.3rem;
    margin-top: 0.4rem; padding-top: 0.4rem;
    border-top: 1px solid #d5dfe8;
    justify-content: center;
}
.com-clubleaddir .clubleadership-contact-link {
    display: inline-flex; align-items: center; gap: 0.2rem;
    padding: 0.2rem 0.45rem; background: #e8f0f8;
    color: #305789; border-radius: 6px;
    font-size: 0.65rem; font-weight: 600; text-decoration: none;
    transition: background 0.2s, color 0.2s;
}
.com-clubleaddir .clubleadership-contact-link:hover { background: #305789; color: #fff; }
.com-clubleaddir .clubleadership-contact-text { display: none; }
@media (min-width: 400px) { .com-clubleaddir .clubleadership-contact-text { display: inline; } }
.com-clubleaddir .clubleadership-contact-hidden {
    display: inline-flex; align-items: center; gap: 0.2rem;
    font-size: 0.65rem; color: #bbb;
}
.com-clubleaddir .clubleadership-contact-hidden .icon-lock { color: #305789; }
@media (max-width: 1100px) {
    .com-clubleaddir .grid-officers { grid-template-columns: repeat(2, 1fr); }
    .com-clubleaddir .grid-directors { grid-template-columns: repeat(3, 1fr); }
    .com-clubleaddir .grid-staff { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .com-clubleaddir .grid-officers { grid-template-columns: repeat(2, 1fr); }
    .com-clubleaddir .grid-directors { grid-template-columns: repeat(2, 1fr); }
    .com-clubleaddir .grid-staff { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .com-clubleaddir .grid-officers,
    .com-clubleaddir .grid-directors { grid-template-columns: 1fr; }
}
/* Vacancy recruitment banner */
.com-clubleaddir .clubleaddir-vacancy-banner {
    display: flex;
    align-items: center;
    gap: 1rem;
    max-width: 1100px;
    margin: 0 auto 1.75rem;
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, #e3ebf5 0%, #f4f8fc 100%);
    border: 1px solid #c5d8ee;
    border-left: 5px solid #1890d7;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(24, 144, 215, 0.12);
}
.com-clubleaddir .clubleaddir-vacancy-banner-icon {
    flex: 0 0 auto;
    width: 46px; height: 46px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem;
    background: #1890d7;
    color: #fff;
    border-radius: 50%;
}
.com-clubleaddir .clubleaddir-vacancy-banner-body { flex: 1 1 auto; }
.com-clubleaddir .clubleaddir-vacancy-banner-title {
    margin: 0 0 0.25rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f5b8a;
}
.com-clubleaddir .clubleaddir-vacancy-banner-text {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.4;
    color: #305789;
}
.com-clubleaddir .clubleaddir-vacancy-banner-cta {
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
.com-clubleaddir .clubleaddir-vacancy-banner-cta:hover {
    background: #0f5b8a;
    transform: translateY(-1px);
}
@media (max-width: 560px) {
    .com-clubleaddir .clubleaddir-vacancy-banner { flex-wrap: wrap; }
    .com-clubleaddir .clubleaddir-vacancy-banner-cta { flex: 1 1 100%; text-align: center; }
}
</style>

<div class="com-clubleaddir">
    <?php
    // Show the recruitment banner only when at least one published position is vacant.
    $anyVacant = false;
    foreach (array('officers', 'directors', 'directors_league', 'staff') as $gk) {
        if (!empty($this->groups[$gk])) {
            foreach ($this->groups[$gk] as $gp) {
                if (!empty($gp->vacant) && (!isset($gp->published) || (int) $gp->published === 1)) {
                    $anyVacant = true;
                    break 2;
                }
            }
        }
    }
    if ($anyVacant):
        echo ClubleaddirHelper::vacancyBannerHtml(
            (int) ($this->params->get('vacant_contact_id', 0)),
            (string) ($this->params->get('vacancy_default_email', ''))
        );
    endif;
    ?>
    <?php foreach ($sections as $key => $title): ?>
        <?php if (!empty($this->groups[$key])): ?>
            <section class="clubleadership-section">
                <h2 class="clubleadership-section-title">
                    <span class="section-icon" aria-hidden="true"><?php echo $icon[$key]; ?></span>
                    <?php echo $title; ?>
                </h2>
                <div class="clubleadership-grid grid-<?php echo $key === 'directors_league' ? 'directors' : ($key === 'officers' ? 'officers' : ($key === 'staff' ? 'staff' : 'directors')); ?>">
                    <?php foreach ($this->groups[$key] as $person):
                        $isOfficer = ($person->type === 'officer');
                        $hasPhoto  = !empty($person->photo);
                        $isVacant  = !empty($person->vacant);
                        $showPhoto = !empty($showPhotos[$key]);
                        // Photo box only appears when this section's photos are on.
                        // Officers always show a photo slot (real photo or initials);
                        // a vacant post shows the club logo; directors/staff only
                        // show a box when they actually have a real photo.
                        $showPhotoBox = $showPhoto && ($hasPhoto || $isOfficer || $isVacant);
                        // When a vacant post has no named person, the role IS the
                        // title (so we don't print "Vacant" twice — the pill does that).
                        $nameEmpty = $isVacant && empty(trim($person->name ?? ''));
                        $displayName = $nameEmpty ? ($person->role ?? '') : $person->name;
                        // Vacant photo (club logo) is shown at 75% of the normal size.
                        $boxSize = $isVacant ? (int) round(120 * 0.75) : 120;
                    ?>
                        <article class="clubleadership-card clubleaddir-card--<?php echo $this->escape($person->type); ?><?php echo $isVacant ? ' clubleaddir-card--vacant' : ''; ?>">
                            <?php if ($showPhotoBox): ?>
                            <div class="clubleadership-card-photo is-visible is-circular" style="width:<?php echo $boxSize; ?>px;height:<?php echo $boxSize; ?>px;">
                                <?php if ($hasPhoto): ?>
                                    <img src="<?php echo $this->escape(clubleaddirSitePhoto($person->photo)); ?>" alt="" loading="lazy" width="<?php echo $boxSize; ?>" height="<?php echo $boxSize; ?>">
                                <?php elseif ($isVacant): ?>
                                    <img src="<?php echo $this->escape(ClubleaddirHelper::vacantLogo()); ?>" alt="" loading="lazy" width="<?php echo $boxSize; ?>" height="<?php echo $boxSize; ?>" style="object-fit:contain;background:#fff;">
                                <?php else: ?>
                                    <div class="clubleadership-card-photo--initials"><?php echo $this->escape(substr($displayName, 0, 2)); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div class="clubleadership-card-content">
                                <h3 class="clubleadership-card-name"><?php echo $this->escape($displayName); ?></h3>
                                <?php if (!empty($person->role) && !$nameEmpty): ?>
                                    <div class="clubleadership-card-role"><?php echo $this->escape($person->role); ?></div>
                                <?php endif; ?>
                                <?php if ($isVacant): ?>
                                    <span class="clubleadership-card-vacant"><?php echo Text::_('COM_CLUBLEADDIR_VACANT'); ?></span>
                                <?php elseif ($person->type === 'staff'): ?>
                                    <?php
                                    $sy = (int) ($person->start_year ?? 0);
                                    $ey = (int) ($person->end_year ?? 0);
                                    if ($sy > 0):
                                        $end = $ey > 0 ? (string) $ey : Text::_('MOD_CLUBLEADDIRECTION_EMPLOYED_CURRENT');
                                    ?>
                                        <div class="clubleadership-card-term"><?php echo $this->escape($sy . ' – ' . $end); ?></div>
                                    <?php endif; ?>
                                <?php elseif (!empty($person->term)): ?>
                                    <div class="clubleadership-card-term"><?php echo $this->escape($person->term); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($person->league_name)): ?>
                                    <div class="clubleadership-card-league"><?php echo $this->escape($person->league_name); ?></div>
                                <?php endif; ?>
                                <?php echo ClubleaddirHelper::contactHtml($person, true, '', (int) ($this->params->get('vacant_contact_id', 0)), (string) ($this->params->get('vacancy_default_email', ''))); ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
