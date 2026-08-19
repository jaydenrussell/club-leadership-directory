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
?>

<div class="com-clubleaddir">
    <?php foreach ($sections as $key => $title): ?>
        <?php if (!empty($this->groups[$key])): ?>
            <section class="clubleaddir-section">
                <h2 class="clubleaddir-section-title"><?php echo $title; ?></h2>
                <div class="clubleaddir-grid">
                    <?php foreach ($this->groups[$key] as $person): ?>
                        <article class="clubleaddir-card clubleaddir-card--<?php echo $this->escape($person->type); ?>">
                            <div class="clubleaddir-card-photo">
                                <?php if (!empty($person->photo)): ?>
                                    <img src="<?php echo $this->escape(clubleaddirSitePhoto($person->photo)); ?>" alt="" loading="lazy" width="120" height="120">
                                <?php else: ?>
                                    <div class="clubleaddir-card-photo--initials"><?php echo $this->escape(substr($person->name, 0, 2)); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="clubleaddir-card-content">
                                <h3 class="clubleaddir-card-name"><?php echo $this->escape($person->name); ?></h3>
                                <?php if (!empty($person->role)): ?>
                                    <div class="clubleaddir-card-role"><?php echo $this->escape($person->role); ?></div>
                                <?php endif; ?>
                                <?php if ($person->type === 'staff'): ?>
                                    <?php
                                    $sy = (int) ($person->start_year ?? 0);
                                    $ey = (int) ($person->end_year ?? 0);
                                    if ($sy > 0):
                                        $end = $ey > 0 ? (string) $ey : Text::_('MOD_CLUBLEADDIRECTION_EMPLOYED_CURRENT');
                                    ?>
                                        <div class="clubleaddir-card-term"><?php echo $this->escape($sy . ' – ' . $end); ?></div>
                                    <?php endif; ?>
                                <?php elseif (!empty($person->term)): ?>
                                    <div class="clubleaddir-card-term"><?php echo $this->escape($person->term); ?></div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
