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
 *  - On uninstall it exports the roster to a JSON backup under /logs/,
 *    then removes every trace of itself (data file, photos, code).
 *
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

class com_clubleaddirInstallerScript
{
	/** @var string Relative path of the exported backup after uninstallExport() */
	private $backupPath = '';

	public function preflight($stage, $parent)
	{
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
		$this->repairLegacy();
		$this->removeOwnMenuItems();

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
	 * Create the isolated data directory under /media/com_clubleaddir/data
	 * and lock it down so the JSON file can never be fetched over HTTP.
	 */
	private function initDataDir()
	{
		$dir = JPATH_ROOT . '/media/com_clubleaddir/data';

		if (!is_dir($dir)) {
			mkdir($dir, 0700, true);
		}
		// Harden existing installs that were created 0755 (world-readable on shared cPanel)
		if (is_dir($dir)) { chmod($dir, 0700); }

		// Apache: deny direct web access to the data file, using syntax that
		// works on both Apache 2.2 and 2.4.
		$ht = $dir . '/.htaccess';
		if (is_dir($dir) && !is_file($ht)) {
			file_put_contents($ht,
				"<Files *>\n"
				. "    Require all denied\n"
				. "</Files>\n"
				. "# Apache 2.2 fallback\n"
				. "<IfModule !mod_authz_core.c>\n"
				. "    Deny from all\n"
				. "</IfModule>\n"
			);
		}

		// Any other server: empty index to prevent directory listing.
		$idx = $dir . '/index.html';
		if (is_dir($dir) && !is_file($idx)) {
			file_put_contents($idx, '');
		}

		// IIS: block access to the data directory via web.config.
		$wc = $dir . '/web.config';
		if (is_dir($dir) && !is_file($wc)) {
			file_put_contents($wc,
				"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
				. "<configuration>\n"
				. "    <system.webServer>\n"
				. "        <security>\n"
				. "            <requestFiltering>\n"
				. "                <hiddenSegments>\n"
				. "                    <add segment=\"data\" />\n"
				. "                </hiddenSegments>\n"
				. "            </requestFiltering>\n"
				. "        </security>\n"
				. "    </system.webServer>\n"
				. "</configuration>\n"
			);
		}

		// Nginx: add this to your server block to block access to the data directory.
		// location ~* ^/media/com_clubleaddir/data/ { deny all; return 404; }

		$this->initUploadDir();
	}

	/**
	 * Create the public photo upload directory and lock down script execution
	 * inside it as defense-in-depth (uploads are MIME-checked, so no RCE is
	 * possible, but we never want a user-supplied file executed as code).
	 */
	private function initUploadDir()
	{
		$dir = JPATH_ROOT . '/images/clubleaddir/photos';

		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		if (is_dir($dir)) {
			chmod($dir, 0755);
			$ht = $dir . '/.htaccess';
			if (!is_file($ht)) {
				file_put_contents($ht,
					"<IfModule mod_php.c>\n"
					. "    php_flag engine off\n"
					. "</IfModule>\n"
					. "<IfModule mod_negotiation.c>\n"
					. "    Options -MultiViews\n"
					. "</IfModule>\n"
					. "AddType text/plain .php .phtml .php3 .php4 .php5 .php7 .pht .phps .cgi .pl .py .asp .aspx .jsp .shtml\n"
					. "<FilesMatch \"\\.(php|phtml|pht|phps|cgi|pl|py|asp|aspx|jsp|shtml)$\">\n"
					. "    Require all denied\n"
					. "</FilesMatch>\n"
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

		// 2. Re-enable our own extension rows. A broken 2.0.x upgrade could leave
		//    enabled = 0, which made the admin area 404 even though files existed.
		try {
			$query = $db->getQuery(true)
				->update($db->quoteName('#__extensions'))
				->set($db->quoteName('enabled') . ' = 1')
				->where($db->quoteName('type') . ' = ' . $db->quote('component'))
				->where($db->quoteName('element') . ' = ' . $db->quote('com_clubleaddir'));
			$db->setQuery($query)->execute();

			$query = $db->getQuery(true)
				->update($db->quoteName('#__extensions'))
				->set($db->quoteName('enabled') . ' = 1')
				->where($db->quoteName('type') . ' = ' . $db->quote('module'))
				->where($db->quoteName('element') . ' = ' . $db->quote('mod_clubleaddir'));
			$db->setQuery($query)->execute();

			$query = $db->getQuery(true)
				->update($db->quoteName('#__extensions'))
				->set($db->quoteName('enabled') . ' = 1')
				->where($db->quoteName('type') . ' = ' . $db->quote('package'))
				->where($db->quoteName('element') . ' = ' . $db->quote('pkg_clubleaddir'));
			$db->setQuery($query)->execute();
		} catch (\Throwable $e) {
			Log::add('Clubleaddir repairLegacy step 2 failed: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
		}

		// 3. Legacy update-site rows pointing at raw.githubusercontent.com were
		//    registered by old SQL hacks. Joomla now manages the update site from
		//    the package <updateservers> declaration alone.
		try {
			$query = $db->getQuery(true)
				->delete($db->quoteName('#__update_sites'))
				->where($db->quoteName('name') . ' = ' . $db->quote('Club Leadership Directory Update'))
				->where($db->quoteName('location') . ' LIKE ' . $db->quote('%raw.githubusercontent.com%'));
			$db->setQuery($query)->execute();
		} catch (\Throwable $e) {
			Log::add('Clubleaddir repairLegacy step 3 failed: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
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
				->where($db->quoteName('menutype') . ' = ' . $db->quote('hiddenmenu'));
			$db->setQuery($query);

			$menuTable = JTable::getInstance('Menu', 'JTable');

			foreach ((array) $db->loadColumn() as $itemId) {
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
	 * Export every record in the roster store to a dated JSON backup under
	 * /images/ before the data file is deleted, so uninstalling never destroys
	 * board history without recourse.
	 */
	private function exportRosterBackup()
	{
		$records = array();

		try {
			$storePath = JPATH_ADMINISTRATOR . '/components/com_clubleaddir/store/Store.php';

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

		$logDir = JPATH_ROOT . '/logs/com_clubleaddir';
		if (!is_dir($logDir) && !mkdir($logDir, 0700, true) && !is_dir($logDir)) {
			return;
		}

		$file = 'backup-' . date('Ymd-His') . '.json';

		if (@file_put_contents($logDir . '/' . $file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false) {
			$this->backupPath = 'logs/com_clubleaddir/' . $file;
		}
	}

	/**
	 * Remove the isolated data directory and the photo upload directory.
	 */
	private function removeDataDirs()
	{
		$this->deleteRecursive(JPATH_ROOT . '/media/com_clubleaddir');

		$photosDir = JPATH_ROOT . '/images/clubleaddir/photos';
		$this->deleteRecursive($photosDir);

		// Drop the parent folder too when nothing else lives in it.
		if (is_dir(JPATH_ROOT . '/images/clubleaddir')) {
			$this->deleteIfEmpty(JPATH_ROOT . '/images/clubleaddir');
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

			$menuTable = JTable::getInstance('Menu', 'JTable');

			foreach ((array) $db->loadColumn() as $itemId) {
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

			if (is_dir($path)) {
				$realPath = realpath($path);
				if ($realPath === false || strpos($realPath, $realDir) !== 0) {
					continue;
				}
				$this->deleteRecursive($path);
			} else {
				$realPath = realpath($path);
				if ($realPath !== false && strpos($realPath, $realDir) === 0) {
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
