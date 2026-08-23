<?php
/**
 * Package install/uninstall script for Club Leadership Directory.
 *
 * v2.0.135+ handles zombie extension rows and missing manifest files.
 *
 * @package     Joomla.Administrator
 * @subpackage  pkg_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

class pkg_clubleaddirInstallerScript
{
    /**
     * Postflight for package install/upgrade.
     * Guarantees clean state and proper manifest placement.
     */
    public function postflight($stage, $parent)
    {
        // Step 1: Clean up any zombie extension rows FIRST
        $this->removeZombieExtensions();

        // Step 2: Ensure destination directories exist
        $this->ensureManifestDirectories();

        // Step 3: Copy manifests to canonical locations
        $this->copyManifests();
    }

    /**
     * Remove zombie extension rows (broken entries that can't be uninstalled).
     */
    private function removeZombieExtensions()
    {
        $db = \Joomla\CMS\Factory::getDbo();

        // Elements that could be zombie entries
        $elements = [
            'pkg_clubleaddir',
            'pkg_pkg_clubleaddir',  // buggy old packaging
            'com_clubleaddir',
            'mod_clubleaddir',
        ];

        foreach ($elements as $element) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($element));
            $db->setQuery($query);
            $id = $db->loadResult();

            if ($id) {
                $del = $db->getQuery(true)
                    ->delete($db->quoteName('#__extensions'))
                    ->where($db->quoteName('extension_id') . ' = ' . (int) $id);
                $db->setQuery($del);
                try { $db->execute(); } catch (\Exception $e) {}
            }
        }
    }

    /**
     * Ensure manifest directories exist.
     */
    private function ensureManifestDirectories()
    {
        $dirs = [
            JPATH_ADMINISTRATOR . '/manifests/components/',
            JPATH_ADMINISTRATOR . '/manifests/modules/',
            JPATH_ADMINISTRATOR . '/manifests/plugins/',
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Copy manifests to canonical locations.
     * These files are included in the package zip at their final destinations.
     */
    private function copyManifests()
    {
        // Component manifest (included at root of child zip, copied by Joomla)
        $destComp = JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml';
        $srcComp = JPATH_ADMINISTRATOR . '/components/com_clubleaddir/com_clubleaddir.xml';
        if (is_file($srcComp) && (!is_file($destComp) || md5_file($srcComp) !== md5_file($destComp))) {
            @copy($srcComp, $destComp);
        }
    }
}