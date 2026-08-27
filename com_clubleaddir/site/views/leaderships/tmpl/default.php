<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

JHtml::stylesheet('com_clubleaddir/site.css', array('relative' => true));

require_once JPATH_ADMINISTRATOR . '/components/com_clubleaddir/helpers.php';

$opts   = $this->displayOptions;
$groups = $this->groups;

$showContact       = !$opts['requireLogin'] || !JFactory::getUser()->guest;
$contactHiddenText = $opts['contactHiddenText'] !== ''
	? $opts['contactHiddenText']
	: Text::_('MOD_CLUBLEADDIR_LOGIN_TO_VIEW');

// Shared card options; the per-section photo flag is set in each loop below.
$cardOpts = array(
	'showContact'         => $showContact,
	'contactHiddenText'   => $contactHiddenText,
	'showTerm'            => $opts['showTerm'],
	'circular'            => $opts['circular'],
	'photoSize'           => $opts['photoSize'],
	'vacantContactId'     => $opts['vacantContactId'],
	'vacancyDefaultEmail' => $opts['vacancyDefaultEmail'],
);

$pageHeading    = '';
$showPageHead   = (int) $this->params->get('show_page_heading', 0) === 1;
$pageClassSfx   = htmlspecialchars($this->params->get('pageclass_sfx', ''), ENT_QUOTES, 'UTF-8');

if ($showPageHead) {
	$pageHeading = trim((string) ($this->params->get('page_heading', '')
		?: $this->params->get('page_title', '')));

	if ($pageHeading === '') {
		$active = JFactory::getApplication()->getMenu()->getActive();
		$pageHeading = $active ? (string) $active->title : '';
	}
}
?>
<div class="clubleaddir com-clubleaddir-view<?php echo $pageClassSfx ? ' ' . $pageClassSfx : ''; ?>">
	<?php
	$displayTitle = trim((string) ($opts['displayTitle'] ?? ''));
	$introText   = trim((string) ($opts['introText'] ?? ''));
	?>
	<?php if ($displayTitle !== '' || $introText !== ''): ?>
	<header class="clubleadership-header">
		<?php if ($displayTitle !== ''): ?>
		<<?php echo $opts['headerTag'] ?? 'h2'; ?> class="clubleadership-title"><?php echo $this->escape($displayTitle); ?></<?php echo $opts['headerTag'] ?? 'h2'; ?>>
		<hr class="clubleadership-title-accent" />
		<?php endif; ?>
		<?php if ($introText !== ''): ?>
		<p class="clubleadership-intro"><?php echo htmlspecialchars($introText, ENT_QUOTES, 'UTF-8'); ?></p>
		<?php endif; ?>
	</header>
	<?php endif; ?>

	<?php
	if ($opts['vacancyBannerEnabled'] && ClubleaddirHelper::hasVacancies($groups)):
		echo ClubleaddirHelper::vacancyBannerHtml($opts['vacantContactId'], $opts['vacancyDefaultEmail']);
	endif;
	?>

	<?php
	// Board of Directors + League Appointed Directors share one background — league is a subsection of Board
	$directors = $groups['directors'] ?? array();
	$league    = $groups['directors_league'] ?? array();
	$hasBoard  = !empty($directors) || !empty($league);
	?>
	<?php
	// Officers — first
	$items = $groups['officers'] ?? array();
	if (!empty($items)):
		$cardOpts['showPhoto'] = !empty($opts['showPhotosOfficers']);
	?>
	<section class="clubleadership-section">
		<?php if ($opts['showSectionTitles']): ?>
		<h2 class="clubleadership-section-title">
			<span class="section-icon" aria-hidden="true">&#9733;</span>
			<?php echo Text::_('MOD_CLUBLEADDIR_OFFICERS'); ?>
		</h2>
		<?php endif; ?>
		<div class="clubleadership-grid grid-officers">
			<?php foreach ($items as $person): ?>
				<?php echo ClubleaddirHelper::cardHtml($person, $cardOpts); ?>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ($hasBoard): ?>
	<?php $cardOpts['showPhoto'] = !empty($opts['showPhotosDirectors']); ?>
	<section class="clubleadership-section">
		<?php if ($opts['showSectionTitles']): ?>
		<h2 class="clubleadership-section-title">
			<span class="section-icon" aria-hidden="true">&#128101;</span>
			<?php echo Text::_('MOD_CLUBLEADDIR_DIRECTORS'); ?>
		</h2>
		<?php endif; ?>
		<?php if (!empty($directors)): ?>
		<div class="clubleadership-grid grid-directors">
			<?php foreach ($directors as $person): ?>
				<?php echo ClubleaddirHelper::cardHtml($person, $cardOpts); ?>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
		<?php if (!empty($league)): ?>
		<div class="clubleadership-subsection">
			<h4 class="clubleadership-subsection-title"><?php echo Text::_('MOD_CLUBLEADDIR_LEAGUE_APPOINTED_DIRECTORS'); ?></h4>
			<div class="clubleadership-grid grid-directors">
				<?php foreach ($league as $person): ?>
					<?php echo ClubleaddirHelper::leagueCardHtml($person, $cardOpts); ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<?php
	$items = $groups['staff'] ?? array();
	if (!empty($items)):
		$cardOpts['showPhoto'] = !empty($opts['showPhotosStaff']);
	?>
	<section class="clubleadership-section">
		<?php if ($opts['showSectionTitles']): ?>
		<h2 class="clubleadership-section-title">
			<span class="section-icon" aria-hidden="true">&#9881;</span>
			<?php echo Text::_('MOD_CLUBLEADDIR_STAFF'); ?>
		</h2>
		<?php endif; ?>
		<div class="clubleadership-grid grid-staff">
			<?php foreach ($items as $person): ?>
				<?php echo ClubleaddirHelper::cardHtml($person, $cardOpts); ?>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>
</div>
