<?php
/**
 * Install / update script.
 *
 * Creates the isolated data directory under /media/com_clubleaddir/data
 * and locks it down so the SQLite/JSON file can never be fetched over HTTP.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;

class ComClubleaddirInstallerScript
{
    private function initDataDir()
    {
        $dir = JPATH_ROOT . '/media/com_clubleaddir/data';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Apache: deny direct web access to the data file, using syntax that
        // works on both Apache 2.2 and 2.4.
        $ht = $dir . '/.htaccess';
        if (is_dir($dir) && !is_file($ht)) {
            @file_put_contents($ht,
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
            @file_put_contents($idx, '');
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
        $dir = JPATH_ROOT . '/images/clubleaddir/photos';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (is_dir($dir)) {
            $ht = $dir . '/.htaccess';
            if (!is_file($ht)) {
                @file_put_contents($ht,
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
                @file_put_contents($idx, '');
            }
        }
    }

    public function install($parent)
    {
        $this->initDataDir();
        return true;
    }

    public function update($parent)
    {
        $this->initDataDir();
        return true;
    }

    public function discover_install($parent)
    {
        $this->initDataDir();
        return true;
    }

    /**
     * Force the component to enabled state after install/update.
     *
     * A prior broken install can leave a disabled (enabled = 0) extension row
     * in #__extensions. Because this package uses method="upgrade", Joomla
     * reuses that row and inherits the disabled flag, which makes every admin
     * request fail with "404 Component not found" even though the files are
     * present. Re-enabling here makes the package self-healing.
     */
    public function postflight($type, $parent)
    {
        try {
            $db = \Joomla\CMS\Factory::getDbo();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_clubleaddir'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
            $db->setQuery($query)->execute();
        } catch (\Throwable $e) {
            // Non-fatal: a clean install already enables the component.
        }

        $this->fixUpdateSite();

        return true;
    }

    /**
     * Ensure exactly one ENABLED update site pointing at the github.com-hosted
     * update.xml. raw.githubusercontent.com is blocked on some hosting, so we
     * serve the XML from releases/latest/download (github.com, reachable).
     * Joomla otherwise registers package <updateservers> as disabled, which
     * silently breaks update detection.
     */
    private function fixUpdateSite()
    {
        $log = array('START');
        try {
            $db   = \Joomla\CMS\Factory::getDbo();
            $log[] = 'DB_OK';
            $name = 'Club Leadership Directory Update';
            $url  = 'https://github.com/jaydenrussell/club-leadership-directory/releases/latest/download/update.xml';

            $q = $db->getQuery(true)
                ->delete($db->quoteName('#__update_sites'))
                ->where($db->quoteName('name') . ' = ' . $db->quote($name))
                ->where($db->quoteName('location') . ' LIKE ' . $db->quote('%raw.githubusercontent.com%'));
            $db->setQuery($q)->execute();
            $log[] = 'DELETE_RAW_AFFECTED=' . $db->getAffectedRows();

            $q = $db->getQuery(true)
                ->update($db->quoteName('#__update_sites'))
                ->set($db->quoteName('location') . ' = ' . $db->quote($url))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('name') . ' = ' . $db->quote($name));
            $db->setQuery($q)->execute();
            $log[] = 'UPDATE_AFFECTED=' . $db->getAffectedRows();

            $q = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__update_sites'))
                ->where($db->quoteName('name') . ' = ' . $db->quote($name));
            $db->setQuery($q);
            $count = (int) $db->loadResult();
            $log[] = 'EXISTING_COUNT=' . $count;
            if ($count === 0) {
                $q = $db->getQuery(true)
                    ->insert($db->quoteName('#__update_sites'))
                    ->columns(array(
                        $db->quoteName('name'), $db->quoteName('type'),
                        $db->quoteName('location'), $db->quoteName('enabled'),
                        $db->quoteName('last_check_timestamp'), $db->quoteName('extra_query'),
                    ))
                    ->values(
                        $db->quote($name) . ', ' . $db->quote('extension') . ', ' .
                        $db->quote($url) . ', 1, 0, ' . $db->quote('')
                    );
                $db->setQuery($q)->execute();
                $log[] = 'INSERTED';
            }
        } catch (\Throwable $e) {
            $log[] = 'EXCEPTION: ' . $e->getMessage();
        }

        // Log to administrator/cache (writable, not SEF-intercepted).
        try {
            $file = defined('JPATH_ADMINISTRATOR') ? JPATH_ADMINISTRATOR . '/cache/_clble_update.log' : __DIR__ . '/_clble_update.log';
            file_put_contents($file, date('c') . ' ' . implode(' | ', $log) . "\n", FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function uninstall($parent)
    {
        // Leave the data file in place on uninstall so board history is not
        // destroyed by accident. Site admin can delete the folder manually.
        return true;
    }
}
