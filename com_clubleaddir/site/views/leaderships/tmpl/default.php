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
	: Text::_('MOD_CLUBLEADDIRECTION_LOGIN_TO_VIEW');

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
<div class="clbleaddir com-clubleaddir-view<?php echo $pageClassSfx ? ' ' . $pageClassSfx : ''; ?>">
	<?php if ($showPageHead && $pageHeading !== ''): ?>
	<h1 class="clbleaddir-page-heading"><?php echo $this->escape($pageHeading); ?></h1>
	<?php endif; ?>

	<?php
	if ($opts['vacancyBannerEnabled'] && ClubleaddirHelper::hasVacancies($groups)):
		echo ClubleaddirHelper::vacancyBannerHtml($opts['vacantContactId'], $opts['vacancyDefaultEmail']);
	endif;
	?>

	<?php foreach (array(
		'officers'         => array('label' => Text::_('MOD_CLUBLEADDIRECTION_OFFICERS'), 'icon' => '&#9733;', 'photoKey' => 'showPhotosOfficers'),
		'directors'        => array('label' => Text::_('MOD_CLUBLEADDIRECTION_DIRECTORS'), 'icon' => '&#128101;', 'photoKey' => 'showPhotosDirectors'),
		'directors_league' => array('label' => Text::_('MOD_CLUBLEADDIRECTION_LEAGUE_APPOINTED_DIRECTORS'), 'icon' => '&#128101;', 'photoKey' => 'showPhotosDirectors'),
		'staff'            => array('label' => Text::_('MOD_CLUBLEADDIRECTION_STAFF'), 'icon' => '&#9881;', 'photoKey' => 'showPhotosStaff'),
	) as $key => $section):
		$items = $groups[$key] ?? array();

		if (empty($items)) {
			continue;
		}

		$cardOpts['showPhoto'] = !empty($opts[$section['photoKey']]);
	?>
	<section class="clubleadership-section">
		<?php if ($opts['showSectionTitles']): ?>
		<h2 class="clubleadership-section-title">
			<span class="section-icon" aria-hidden="true"><?php echo $section['icon']; ?></span>
			<?php echo $section['label']; ?>
		</h2>
		<?php endif; ?>
		<div class="clubleadership-grid grid-<?php echo $key === 'directors_league' ? 'directors' : $key; ?>">
			<?php foreach ($items as $person): ?>
				<?php
				echo ($person->type === 'director_league')
					? ClubleaddirHelper::leagueCardHtml($person, $cardOpts)
					: ClubleaddirHelper::cardHtml($person, $cardOpts);
				?>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endforeach; ?>
</div>
