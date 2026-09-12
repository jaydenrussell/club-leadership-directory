<?php
/**
 * Install / update / uninstall script for com_clubleaddir.
 *
 * House rules (Joomla extension safeguards):
 *  - This script ONLY touches files and DB rows created by THIS extension.
 *  - It never creates menus, menu items, modules or core content.
 *  - It never writes debug log files.
 *  - On upgrade it repairs damage left by legacy 2.0.x installs (zombie
 *    package rows, hidden "stealth" menu leftovers, stale update sites,
 *    stray debug logs) — idempotently.
 *  - On uninstall it exports the roster to a JSON backup in a directory that
 *    survives the uninstall OUTSIDE the web root when the account allows
 *    (fallback: a hardened /logs/ folder), then removes every trace of itself
 *    (data file, audit log, photos, code).
 *
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/helpers.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

class com_clubleaddirInstallerScript
{
	/** @var string Relative path of the exported backup after uninstallExport() */
	private $backupPath = '';

	public function preflight($stage, $parent)
	{
		// Ensure the media destination folder exists before Joomla's installer
		// tries to delete it during update. Without this, updating from a
		// version that did not ship the <media> block triggers:
		// "JFolder: :delete: Path is not a folder. Path: [ROOT]/media/com_clubleaddir"
		$mediaDir = JPATH_ROOT . '/media/com_clubleaddir';
		if (!is_dir($mediaDir)) {
			@mkdir($mediaDir, 0755, true);
		}

		return true;
	}

	public function install($parent)
	{
		$this->initDataDir();
		$this->repairLegacy();
		return true;
	}

	public function update($parent)
	{
		$this->initDataDir();
		$this->repairLegacy();
		return true;
	}

	public function discover_install($parent)
	{
		$this->initDataDir();
		$this->repairLegacy();
		return true;
	}

	public function uninstall($parent)
	{
		$this->exportRosterBackup();
		$this->removeDataDirs();

		// Only after the roster is safely backed up: remove the live store
		// (which lives OUTSIDE the component folder in dataDir() when the host
		// allows it). Without this the PII would survive the uninstall in the
		// sibling directory.
		if ($this->backupPath !== '') {
			try {
				$this->deleteRecursive(ClubleaddirStore::dataDir());
			} catch (\Throwable $e) {
				Log::add('Clubleaddir uninstall: could not remove live data dir: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
			}
		}

		$this->repairLegacy();
		$this->removeOwnMenuItems();
		$this->ensureMediaDirForCoreCleanup($parent);

		if ($this->backupPath !== '') {
			try {
				Factory::getApplication()->enqueueMessage(
					Text::sprintf('COM_CLUBLEADDIR_UNINSTALL_BACKUP_SAVED', $this->backupPath),
					'notice'
				);
			} catch (\Throwable $e) {
				// Messaging must never break the uninstall.
			}
		}

		return true;
	}

	/**
	 * Resolve (or create) the isolated data directory. Preferred location is
	 * OUTSIDE the web root (a sibling of the site root); see
	 * ClubleaddirStore::dataDir() for the candidates and fallbacks.
	 */
	private function initDataDir()
	{
		try {
			ClubleaddirStore::dataDir();
		} catch (\Throwable $e) {
			Log::add('Clubleaddir install: cannot resolve data dir: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
		}

		$this->initUploadDir();
	}

	/**
	 * Create the public photo upload directory and lock down script execution
	 * inside it as defense-in-depth (uploads are MIME-checked, so no RCE is
	 * possible, but we never want a user-supplied file executed as code).
	 */
	private function initUploadDir()
	{
		// The photos folder is served statically by the web server, so BOTH it
		// and its parent must expose traverse + read (0755). mkdir(..., 0755,
		// true) only applies the mode to the final component; intermediate
		// folders are created as 0777 & ~umask, which under umask 0077 leaves
		// /images/clubleaddir at 0700 — PHP can still write through it, so the
		// upload/import "succeeds" but every rendered photo 403s. Reassert 0755
		// on the parent and the folder itself on every install/update so a
		// stuck-0700 parent self-heals.
		$base = JPATH_ROOT . '/images/clubleaddir';
		$dir  = $base . '/photos';

		if (!is_dir($base)) {
			@mkdir($base, 0755, true);
		}
		if (is_dir($base)) {
			@chmod($base, 0755);
		}

		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}

		if (is_dir($dir)) {
			@chmod($dir, 0755);
			$ht = $dir . '/.htaccess';
			if (!is_file($ht)) {
				file_put_contents($ht, ClubleaddirHelper::photoHtaccessRules());
			}

			$nx = $dir . '/nginx.conf';
			if (!is_file($nx)) {
				file_put_contents($nx,
					"# Nginx: deny execution of script files in the uploads directory\n"
					. "location ~* ^/images/clubleaddir/photos/.*\\.(php|phtml|phps|cgi|pl|py|asp|aspx|jsp|shtml)$ {\n"
					. "    deny all;\n"
					. "}\n"
				);
			}

			$idx = $dir . '/index.html';
			if (!is_file($idx)) {
				file_put_contents($idx, '');
			}
		}
	}

	/**
	 * Repair damage left behind by legacy 2.0.x releases. Every step targets
	 * rows/files this extension itself created, and every step is safe to run
	 * repeatedly.
	 */
	private function repairLegacy()
	{
		$db = Factory::getDbo();

		// 1. Zombie package row: very old packages used <packagename> that made
		//    Joomla register element "pkg_pkg_clubleaddir". That row could never
		//    match a manifest and blocked clean uninstalls.
		try {
			$query = $db->getQuery(true)
				->delete($db->quoteName('#__extensions'))
				->where($db->quoteName('type') . ' = ' . $db->quote('package'))
				->where($db->quoteName('element') . ' = ' . $db->quote('pkg_pkg_clubleaddir'));
			$db->setQuery($query)->execute();
		} catch (\Throwable $e) {
			Log::add('Clubleaddir repairLegacy step 1 failed: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
		}

		// ...and its orphaned manifest file, if any.
		$zombiePkgManifest = JPATH_ADMINISTRATOR . '/manifests/packages/pkg_pkg_clubleaddir.xml';
		if (is_file($zombiePkgManifest)) {
			unlink($zombiePkgManifest);
		}

		// 2. Re-enable our own extension row. A broken 2.0.x upgrade could leave
		//    enabled = 0, which made the admin area 404 even though files existed.
		//    Only the component itself is forced on; admins may have intentionally
		//    disabled the module or package row.
		try {
			$query = $db->getQuery(true)
				->update($db->quoteName('#__extensions'))
				->set($db->quoteName('enabled') . ' = 1')
				->where($db->quoteName('type') . ' = ' . $db->quote('component'))
				->where($db->quoteName('element') . ' = ' . $db->quote('com_clubleaddir'));
			$db->setQuery($query)->execute();
		} catch (\Throwable $e) {
			Log::add('Clubleaddir repairLegacy step 2 failed: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
		}

		// 3. Legacy update-site rows pointing at the old `main` branch on
		//    raw.githubusercontent.com were registered by earlier SQL hacks.
		//    Only remove that exact stale entry; the current valid URL uses
		//    `master` and must be preserved.
		try {
			$query = $db->getQuery(true)
				->delete($db->quoteName('#__update_sites'))
				->where($db->quoteName('name') . ' = ' . $db->quote('Club Leadership Directory Update'))
				->where($db->quoteName('location') . ' = ' . $db->quote(
					'https://raw.githubusercontent.com/jaydenrussell/club-leadership-directory/main/update-full.xml'
				));
			$db->setQuery($query)->execute();
		} catch (\Throwable $e) {
			Log::add('Clubleaddir repairLegacy step 3 failed: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
		}

		// 3b. Standardize the update feed. Since v3.28.4 the package manifest
		//    points at a single update.xml, a standard <update> extension feed
		//    (the update-full.xml indirection was removed). Joomla 3's
		//    CollectionAdapter only parses <extension> collection feeds while
		//    our update.xml is an <update> extension feed, so the row MUST use
		//    server type "extension". Rewrite the location of legacy rows that
		//    still point at update-full.xml (any branch), force the extension
		//    type and re-enable them.
		try {
			$updateLocation = 'https://raw.githubusercontent.com/jaydenrussell/club-leadership-directory/master/update.xml';

			$query = $db->getQuery(true)
				->select($db->quoteName('update_site_id'))
				->from($db->quoteName('#__update_sites'))
				->where($db->quoteName('name') . ' = ' . $db->quote('Club Leadership Directory Update'))
				->where($db->quoteName('location') . ' LIKE ' . $db->quote('https://raw.githubusercontent.com/jaydenrussell/club-leadership-directory/%'));
			$db->setQuery($query);
			$siteIds = (array) $db->loadColumn();

			foreach ($siteIds as $siteId) {
				$query = $db->getQuery(true)
					->update($db->quoteName('#__update_sites'))
					->set($db->quoteName('location') . ' = ' . $db->quote($updateLocation))
					->set($db->quoteName('type') . ' = ' . $db->quote('extension'))
					->set($db->quoteName('enabled') . ' = 1')
					->where($db->quoteName('update_site_id') . ' = ' . (int) $siteId);
				$db->setQuery($query)->execute();
			}
		} catch (\Throwable $e) {
			Log::add('Clubleaddir repairLegacy step 3b failed: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
		}

		// 4. Stray debug log files written by legacy installers.
		foreach (array(
			JPATH_ROOT . '/inquire_debug.log',
			JPATH_ADMINISTRATOR . '/cache/_clble_update.log',
		) as $logFile) {
			if (is_file($logFile)) {
				unlink($logFile);
			}
		}

		// 5. Legacy 2.0.x installers created a hidden menu type ("Hidden Menu")
		//    plus an "Inquire" menu item pointing at a hard-coded contact. That
		//    was an overstep into site content — remove any leftovers.
		$this->removeStealthMenu();
	}

	/**
	 * Remove the legacy "hiddenmenu" menu type and every menu item inside it,
	 * using JTable so the #__menu nested set stays intact.
	 */
	private function removeStealthMenu()
	{
		try {
			$db    = Factory::getDbo();
			$query = $db->getQuery(true)
				->select($db->quoteName('id'))
				->from($db->quoteName('#__menu'))
				->where($db->quoteName('menutype') . ' = ' . $db->quote('hiddenmenu'))
				->where($db->quoteName('link') . ' LIKE ' . $db->q('%com_clubleaddir%'));
			$db->setQuery($query);

			foreach ((array) $db->loadColumn() as $itemId) {
				$menuTable = null;
				if (class_exists('JTable')) {
					$menuTable = JTable::getInstance('Menu', 'JTable');
				} elseif (class_exists('\Joomla\CMS\Table\Table')) {
					$menuTable = \Joomla\CMS\Table\Table::getInstance('Menu');
				}

				if ($menuTable && $menuTable->load((int) $itemId)) {
					$menuTable->delete((int) $itemId);
				}
			}

			$query = $db->getQuery(true)
				->delete($db->quoteName('#__menu_types'))
				->where($db->quoteName('menutype') . ' = ' . $db->quote('hiddenmenu'));
			$db->setQuery($query)->execute();
		} catch (\Throwable $e) {
			Log::add('Clubleaddir removeStealthMenu failed: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
		}
	}

	/**
	 * Export every record in the roster store to a dated JSON backup in a
	 * directory that survives the uninstall, so uninstalling never destroys
	 * board history without recourse. The directory is resolved by
	 * ClubleaddirStore::backupDir(): a sibling of the web root when possible,
	 * otherwise a hardened /logs/ folder. Neither is a web-served location for
	 * PII once this release is running.
	 */
	private function exportRosterBackup()
	{
		$records = array();

		try {
			$storePath = JPATH_ADMINISTRATOR . '/components/com_clubleaddir/admin/store/Store.php';

			if (is_file($storePath)) {
				require_once $storePath;

				$rows = ClubleaddirStore::getInstance()->getAll(array());

				foreach ($rows as $row) {
					$records[] = (array) $row;
				}
			}
		} catch (\Throwable $e) {
			$records = array();
		}

		$payload = array(
			'_meta' => array(
				'extension' => 'com_clubleaddir',
				'version'   => 'uninstall-export',
				'date'      => date('c'),
				'note'      => 'Uploaded photos were removed with the extension; photo paths below refer to the deleted folder.',
			),
			'records' => $records,
		);

		// Outside the web root (survives the uninstall, never web-served), or a
		// hardened /logs fallback when the account cannot write above the
		// docroot. The component folder itself is removed right after this
		// returns, which is exactly why the backup cannot live inside it.
		try {
			$backupDir = ClubleaddirStore::backupDir();
		} catch (\Throwable $e) {
			Log::add('Clubleaddir uninstall: cannot resolve backup dir: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
			return;
		}

		// Older releases wrote backups to the web-visible /logs folder. Remove
		// those survivors now, while we still can.
		foreach ((array) glob(JPATH_ROOT . '/logs/com_clubleaddir-backup-*.json') as $stale) {
			if (is_file($stale)) {
				@unlink($stale);
			}
		}

		try {
			$rnd = bin2hex(random_bytes(4));
		} catch (\Throwable $e) {
			$rnd = bin2hex(openssl_random_pseudo_bytes(4));
		}
		$file = 'com_clubleaddir-backup-' . date('Ymd-His') . '-' . $rnd . '.json';
		$backupPath = $backupDir . '/' . $file;

		if (@file_put_contents($backupPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false) {
			@chmod($backupPath, 0600);
			$this->backupPath = $backupPath;
		} else {
			Log::add('Clubleaddir uninstall: cannot write backup to ' . $backupPath, Log::WARNING, 'com_clubleaddir');
		}
	}

	/**
	 * Remove the photo upload directory. The media folder under
	 * /media/com_clubleaddir is deliberately left for Joomla's own uninstaller;
	 * deleting it here made the core uninstaller error with
	 * "JFolder: :delete: Path is not a folder" on its follow-up pass.
	 */
	private function removeDataDirs()
	{
		$photosDir = JPATH_ROOT . '/images/clubleaddir/photos';
		$this->deleteRecursive($photosDir);

		// Drop the parent folder too when nothing else lives in it.
		if (is_dir(JPATH_ROOT . '/images/clubleaddir')) {
			$this->deleteIfEmpty(JPATH_ROOT . '/images/clubleaddir');
		}
	}

	/**
	 * Joomla's component uninstaller removes /media/com_clubleaddir AFTER this
	 * script returns (Installer::removeFiles on the <media> element). If that
	 * folder happens to be missing - e.g. a partially failed install - the
	 * core's unguarded trailing JFolder::delete() logs "Path is not a folder".
	 * Recreate it (empty) so the core cleanup succeeds. Only do this when the
	 * installed manifest actually declares the <media> block; otherwise core
	 * never touches media and we would leave an empty orphan folder behind.
	 */
	private function ensureMediaDirForCoreCleanup($parent)
	{
		$dir = JPATH_ROOT . '/media/com_clubleaddir';

		if (is_dir($dir)) {
			return;
		}

		try {
			$manifest = $parent && method_exists($parent, 'getManifest') ? $parent->getManifest() : null;
			$media    = $manifest ? $manifest->media : null;

			if ($media && (string) $media->attributes()->destination === 'com_clubleaddir' && count($media->children()) > 0) {
				@mkdir($dir, 0755, true);
			}
		} catch (\Throwable $e) {
			Log::add('Clubleaddir ensureMediaDirForCoreCleanup failed: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
		}
	}

	/**
	 * Delete site menu items that point at this component so no dead links are
	 * left behind after a full uninstall. Uses JTable to preserve the nested set.
	 */
	private function removeOwnMenuItems()
	{
		try {
			$db    = Factory::getDbo();
			$query = $db->getQuery(true)
				->select($db->quoteName('id'))
				->from($db->quoteName('#__menu'))
				->where($db->quoteName('type') . ' = ' . $db->quote('component'))
				->where($db->quoteName('link') . ' LIKE ' . $db->quote('index.php?option=com_clubleaddir%'));
			$db->setQuery($query);

			foreach ((array) $db->loadColumn() as $itemId) {
				$menuTable = null;
				if (class_exists('JTable')) {
					$menuTable = JTable::getInstance('Menu', 'JTable');
				} elseif (class_exists('\Joomla\CMS\Table\Table')) {
					$menuTable = \Joomla\CMS\Table\Table::getInstance('Menu');
				}

				if ($menuTable && $menuTable->load((int) $itemId)) {
					$menuTable->delete((int) $itemId);
				}
			}
		} catch (\Throwable $e) {
			Log::add('Clubleaddir removeOwnMenuItems failed: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
		}
	}

	private function deleteRecursive($dir)
	{
		if (!is_dir($dir)) {
			return;
		}

		$realDir = realpath($dir);
		if ($realDir === false || $realDir === '/') {
			return;
		}

		foreach (scandir($dir) as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$path = $dir . '/' . $item;

			if (is_dir($path) && !is_link($path)) {
				$realPath = realpath($path);
				if ($realPath === false || strpos($realPath, $realDir) !== 0) {
					continue;
				}
				$this->deleteRecursive($path);
			} else {
				$realPath = realpath($path);
				if ($realPath !== false && strpos($realPath, $realDir) === 0 && !is_link($path)) {
					unlink($path);
				}
			}
		}

		rmdir($dir);
	}

	private function deleteIfEmpty($dir)
	{
		if (!is_dir($dir)) {
			return;
		}

		$entries = array_diff(scandir($dir), array('.', '..'));

		if (empty($entries)) {
			rmdir($dir);
		}
	}
}
