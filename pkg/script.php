<?php
/**
 * Package install/uninstall script for Club Leadership Directory v2.0.141+
 * 
 * Minimal script - Joomla's native installer handles all database operations.
 */

defined('_JEXEC') or die;

class pkg_clubleaddirInstallerScript
{
    public function preflight($stage, $parent)
    {
        // Clean up any zombie extension entries that might block reinstall
        $db = \Joomla\CMS\Factory::getDbo();
        $table = $db->quoteName('#__extensions');
        
        $elements = ['pkg_pkg_clubleaddir']; // Only the buggy duplicate element
        foreach ($elements as $element) {
            try {
                $query = $db->getQuery(true)
                    ->delete($table)
                    ->where($db->quoteName('element') . ' = ' . $db->quote($element));
                $db->setQuery($query);
                $db->execute();
            } catch (\Exception $e) {}
        }
    }

    public function postflight($stage, $parent)
    {
        // Just ensure manifest directories and files are in place
        $dirs = [
            JPATH_ADMINISTRATOR . '/manifests/components',
            JPATH_ADMINISTRATOR . '/manifests/modules',
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
        
        // Copy component manifest
        $src = JPATH_ADMINISTRATOR . '/components/com_clubleaddir/com_clubleaddir.xml';
        $dst = JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml';
        if (is_file($src) && !is_file($dst)) {
            copy($src, $dst);
        }
        
        // Copy module manifest
        $src = JPATH_SITE . '/modules/mod_clubleaddir/mod_clubleaddir.xml';
        $dst = JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml';
        if (is_file($src) && !is_file($dst)) {
            copy($src, $dst);
        }
    }

    public function uninstall($parent)
    {
        // No cleanup needed - Joomla handles it
    }
}