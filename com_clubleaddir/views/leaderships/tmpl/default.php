<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

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

$icon = array(
    'officers'         => '&#9733;',
    'directors'        => '&#128101;',
    'directors_league' => '&#128101;',
    'staff'            => '&#9881;',
);
?>
<style>
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
}
.com-clubleaddir .clubleadership-card:hover {
    box-shadow: 0 6px 24px rgba(21, 50, 74, 0.14);
    transform: translateY(-2px);
}
.com-clubleaddir .clubleadership-card--officer { text-align: center; }
.com-clubleaddir .clubleadership-card--director,
.com-clubleaddir .clubleadership-card--staff { display: flex; flex-direction: column; min-height: 110px; }
.com-clubleaddir .clubleadership-card--officer .clubleadership-card-photo {
    margin: 0.875rem auto 0;
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
    justify-content: center;
    flex: 1;
}
.com-clubleaddir .clubleadership-card--officer .clubleadership-card-content { padding: 0.5rem 0.75rem 0.875rem; justify-content: flex-start; }
.com-clubleaddir .clubleadership-card-name { font-size: 0.8rem; font-weight: 600; color: #1a2a3a; margin: 0 0 0.1rem; line-height: 1.3; }
.com-clubleaddir .clubleadership-card--officer .clubleadership-card-name { font-size: 1rem; margin-top: 0.35rem; }
.com-clubleaddir .clubleadership-card-role { font-size: 0.7rem; font-weight: 600; color: #305789; }
.com-clubleaddir .clubleadership-card--officer .clubleadership-card-role { color: #b8963e; font-size: 0.8rem; }
.com-clubleaddir .clubleadership-card-term { font-size: 0.65rem; color: #999; }
.com-clubleaddir .clubleadership-card-league { font-size: 0.68rem; color: #555; font-style: italic; margin-top: 0.1rem; }
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
</style>

<div class="com-clubleaddir">
    <?php foreach ($sections as $key => $title): ?>
        <?php if (!empty($this->groups[$key])): ?>
            <section class="clubleadership-section">
                <h2 class="clubleadership-section-title">
                    <span class="section-icon" aria-hidden="true"><?php echo $icon[$key]; ?></span>
                    <?php echo $title; ?>
                </h2>
                <div class="clubleadership-grid grid-<?php echo $key === 'directors_league' ? 'directors' : ($key === 'officers' ? 'officers' : ($key === 'staff' ? 'staff' : 'directors')); ?>">
                    <?php foreach ($this->groups[$key] as $person): ?>
                        <article class="clubleadership-card clubleaddir-card--<?php echo $this->escape($person->type); ?>">
                            <div class="clubleadership-card-photo is-visible is-circular" style="width:120px;height:120px;">
                                <?php if (!empty($person->photo)): ?>
                                    <img src="<?php echo $this->escape(clubleaddirSitePhoto($person->photo)); ?>" alt="" loading="lazy" width="120" height="120">
                                <?php else: ?>
                                    <div class="clubleadership-card-photo--initials"><?php echo $this->escape(substr($person->name, 0, 2)); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="clubleadership-card-content">
                                <h3 class="clubleadership-card-name"><?php echo $this->escape($person->name); ?></h3>
                                <?php if (!empty($person->role)): ?>
                                    <div class="clubleadership-card-role"><?php echo $this->escape($person->role); ?></div>
                                <?php endif; ?>
                                <?php if ($person->type === 'staff'): ?>
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
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
