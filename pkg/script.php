<?php
/**
 * Package install/uninstall script for Club Leadership Directory.
 *
 * v2.0.136+ handles zombie extension rows and missing manifest files.
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
        $this->copyManifestsRobust();
    }

    /**
     * Remove zombie extension rows (broken entries that can't be uninstalled).
     */
    private function removeZombieExtensions()
    {
        $db = \Joomla\CMS\Factory::getDbo();

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
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Copy manifests using robust PHP file functions.
     * Tries multiple possible source locations.
     */
    private function copyManifestsRobust()
    {
        // Component: try multiple possible locations for the manifest
        $compSrcs = [
            JPATH_ADMINISTRATOR . '/components/com_clubleaddir/com_clubleaddir.xml',
            JPATH_ROOT . '/administrator/components/com_clubleaddir/com_clubleaddir.xml',
        ];
        foreach ($compSrcs as $src) {
            if (is_file($src)) {
                $dst = JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml';
                $this->safeCopy($src, $dst);
                break;
            }
        }

        // Module: try multiple possible locations for the manifest
        $modSrcs = [
            JPATH_ROOT . '/modules/mod_clubleaddir/mod_clubleaddir.xml',
            JPATH_SITE . '/modules/mod_clubleaddir/mod_clubleaddir.xml',
        ];
        foreach ($modSrcs as $src) {
            if (is_file($src)) {
                $dst = JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml';
                $this->safeCopy($src, $dst);
                break;
            }
        }
    }

    /**
     * Safely copy a file, creating directories if needed.
     */
    private function safeCopy($src, $dst)
    {
        if (!is_file($src) || is_file($dst)) {
            return false;
        }
        $dir = dirname($dst);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $content = @file_get_contents($src);
        if ($content === false) {
            return false;
        }
        return @file_put_contents($dst, $content) !== false;
    }
}