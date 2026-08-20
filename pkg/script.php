<?php
/**
 * Package installer script for Club Leadership Directory.
 *
 * On install/update it ensures the "Club Leadership Directory Update" update
 * site points at the github.com-hosted update.xml (raw.githubusercontent.com is
 * blocked on some hosting) and is ENABLED, and removes any stale
 * raw.githubusercontent.com update site. Joomla sometimes registers
 * package-declared <updateservers> as disabled (or leaves stale duplicates),
 * which silently breaks update detection.
 *
 * A diagnostic log is written to the web-served photos directory so the run
 * can be confirmed without DB access.
 *
 * @package     Joomla.Administrator
 * @subpackage  pkg_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Package installer script.
 */
class pkg_clubleaddirInstallerScript
{
    /**
     * Run after install/update.
     *
     * @param   string  $type    'install' | 'update' | 'discover_install'
     * @param   object  $parent  Installer parent
     *
     * @return  void
     */
    public function postflight($type, $parent)
    {
        $this->fixUpdateSite();
    }

    /**
     * Make the github.com update site enabled and remove stale raw sites.
     *
     * @return  void
     */
    private function fixUpdateSite()
    {
        $log = array();
        try {
            $db = Factory::getDbo();
        } catch (\Throwable $e) {
            $this->log('DB_UNAVAILABLE: ' . $e->getMessage());
            return;
        }

        $name  = 'Club Leadership Directory Update';
        $good  = 'github.com/jaydenrussell/club-leadership-directory/releases/latest/download/update.xml';
        $bad   = 'raw.githubusercontent.com';

        try {
            // Enable the github.com-hosted site.
            $q = $db->getQuery(true)
                ->update($db->quoteName('#__update_sites'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('name') . ' = ' . $db->quote($name))
                ->where($db->quoteName('location') . ' LIKE ' . $db->quote('%' . $good . '%'));
            $db->setQuery($q);
            $db->execute();
            $log[] = 'enabled_github=' . $db->getAffectedRows();

            // Point any site with the right name but a stale/bad location at github.com.
            $q = $db->getQuery(true)
                ->update($db->quoteName('#__update_sites'))
                ->set($db->quoteName('location') . ' = ' . $db->quote('https://github.com/jaydenrussell/club-leadership-directory/releases/latest/download/update.xml'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('name') . ' = ' . $db->quote($name));
            $db->setQuery($q);
            $db->execute();
            $log[] = 'repointed=' . $db->getAffectedRows();

            // Remove stale raw.githubusercontent.com sites.
            $q = $db->getQuery(true)
                ->delete($db->quoteName('#__update_sites'))
                ->where($db->quoteName('name') . ' = ' . $db->quote($name))
                ->where($db->quoteName('location') . ' LIKE ' . $db->quote('%' . $bad . '%'));
            $db->setQuery($q);
            $db->execute();
            $log[] = 'removed_raw=' . $db->getAffectedRows();
        } catch (\Throwable $e) {
            $log[] = 'ERROR: ' . $e->getMessage();
        }

        $this->log(implode(' | ', $log));
    }

    /**
     * Write a diagnostic log line to the web-served photos directory.
     *
     * @param   string  $msg  Message
     *
     * @return  void
     */
    private function log($msg)
    {
        try {
            $dir = defined('JPATH_ROOT') ? JPATH_ROOT . '/images/clubleaddir/photos' : __DIR__;
            if (!is_dir($dir)) {
                $dir = __DIR__;
            }
            $file = $dir . '/_script_ran.log';
            $line = date('c') . ' ' . $msg . "\n";
            file_put_contents($file, $line, FILE_APPEND);
        } catch (\Throwable $e) {
            // Non-fatal.
        }
    }
}
