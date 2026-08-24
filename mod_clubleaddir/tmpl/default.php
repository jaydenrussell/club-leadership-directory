<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

JHtml::stylesheet('com_clubleaddir/site.css', array('relative' => true));

require_once JPATH_ADMINISTRATOR . '/components/com_clubleaddir/helpers.php';

$opts     = ClubleaddirHelper::displayOptions();
$groups   = $rawData;
$sfx      = htmlspecialchars($params->get('module_class_sfx', ''), ENT_QUOTES, 'UTF-8');
$header   = (string) $opts['displayTitle'];
$intro    = (string) $opts['introText'];

$officers        = $groups['officers'] ?? array();
$directors       = $groups['directors'] ?? array();
$directorsLeague = $groups['directors_league'] ?? array();
$staff           = $groups['staff'] ?? array();

$hasContent = !empty($officers) || !empty($directors) || !empty($directorsLeague) || !empty($staff);

if (!$hasContent) {
	return;
}

$showContact       = !$opts['requireLogin'] || !Factory::getUser()->guest;
$contactHiddenText = $opts['contactHiddenText'] !== ''
	? $opts['contactHiddenText']
	: Text::_('MOD_CLUBLEADDIRECTION_LOGIN_TO_VIEW');

$cardOpts = array(
	'showContact'         => $showContact,
	'contactHiddenText'   => $contactHiddenText,
	'showTerm'            => $opts['showTerm'],
	'circular'            => $opts['circular'],
	'photoSize'           => $opts['photoSize'],
	'vacantContactId'     => $opts['vacantContactId'],
	'vacancyDefaultEmail' => $opts['vacancyDefaultEmail'],
);
?>
<div class="clbleaddir mod-clubleaddir<?php echo $sfx ? ' ' . $sfx : ''; ?>">
	<?php if ($header !== ''): ?>
	<header class="clubleadership-header">
		<<?php echo $opts['headerTag']; ?> class="clubleadership-title"><?php echo htmlspecialchars($header, ENT_QUOTES, 'UTF-8'); ?></<?php echo $opts['headerTag']; ?>>
		<hr class="clubleadership-title-accent" />
		<?php if ($intro !== ''): ?>
		<p class="clubleadership-intro"><?php echo $intro; ?></p>
		<?php endif; ?>
		<?php if (!$showContact): ?>
		<p class="clubleadership-login-notice">
			<span class="icon-lock" aria-hidden="true"></span>
			<?php echo htmlspecialchars($contactHiddenText, ENT_QUOTES, 'UTF-8'); ?>
		</p>
		<?php endif; ?>
	</header>
	<?php endif; ?>

	<?php if ($opts['vacancyBannerEnabled'] && ClubleaddirHelper::hasVacancies($groups)):
		echo ClubleaddirHelper::vacancyBannerHtml($opts['vacantContactId'], $opts['vacancyDefaultEmail']);
	endif; ?>

	<?php if (!empty($officers)): ?>
	<section class="clubleadership-section">
		<?php if ($opts['showSectionTitles']): ?>
		<h3 class="clubleadership-section-title">
			<span class="section-icon" aria-hidden="true">&#9733;</span>
			<?php echo Text::_('MOD_CLUBLEADDIRECTION_OFFICERS'); ?>
		</h3>
		<?php endif; ?>
		<div class="clubleadership-grid grid-officers">
			<?php foreach ($officers as $person):
				$cardOpts['showPhoto'] = $opts['showPhotosOfficers'];
				echo ClubleaddirHelper::cardHtml($person, $cardOpts);
			endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if (!empty($directors) || !empty($directorsLeague)): ?>
	<section class="clubleadership-section">
		<?php if ($opts['showSectionTitles']): ?>
		<h3 class="clubleadership-section-title">
			<span class="section-icon" aria-hidden="true">&#128101;</span>
			<?php echo Text::_('MOD_CLUBLEADDIRECTION_DIRECTORS'); ?>
		</h3>
		<?php endif; ?>
		<?php if (!empty($directors)): ?>
		<div class="clubleadership-grid grid-directors">
			<?php foreach ($directors as $person):
				$cardOpts['showPhoto'] = $opts['showPhotosDirectors'];
				echo ClubleaddirHelper::cardHtml($person, $cardOpts);
			endforeach; ?>
		</div>
		<?php endif; ?>
		<?php if (!empty($directorsLeague)): ?>
		<div class="clubleadership-subsection">
			<h4 class="clubleadership-subsection-title"><?php echo Text::_('MOD_CLUBLEADDIRECTION_LEAGUE_APPOINTED_DIRECTORS'); ?></h4>
			<div class="clubleadership-grid grid-directors">
				<?php foreach ($directorsLeague as $person):
					echo ClubleaddirHelper::leagueCardHtml($person, $cardOpts);
				endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<?php if (!empty($staff)): ?>
	<section class="clubleadership-section">
		<?php if ($opts['showSectionTitles']): ?>
		<h3 class="clubleadership-section-title">
			<span class="section-icon" aria-hidden="true">&#9881;</span>
			<?php echo Text::_('MOD_CLUBLEADDIRECTION_STAFF'); ?>
		</h3>
		<?php endif; ?>
		<div class="clubleadership-grid grid-staff">
			<?php foreach ($staff as $person):
				$cardOpts['showPhoto'] = $opts['showPhotosStaff'];
				echo ClubleaddirHelper::cardHtml($person, $cardOpts);
			endforeach; ?>
		</div>
	</section>
	<?php endif; ?>
</div>
