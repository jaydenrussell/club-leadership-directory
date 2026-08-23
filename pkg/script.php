<?php
/**
 * Package install/uninstall script for Club Leadership Directory.
 *
 * v2.0.138+ ensures clean installation with proper database registration.
 */

defined('_JEXEC') or die;

class pkg_clubleaddirInstallerScript
{
    /**
     * Preflight runs BEFORE children are installed.
     * Clean any leftover state from broken previous installs.
     */
    public function preflight($stage, $parent)
    {
        // Remove ANY existing extension entries (even if corrupted)
        $this->forceRemoveAllEntries();
        
        // Remove any orphaned files
        $this->removeOrphanedFiles();
    }

    /**
     * Postflight runs AFTER children are installed.
     * Ensure everything is registered and manifests are in place.
     */
    public function postflight($stage, $parent)
    {
        // If stage is 'install', force database sync
        if ($stage === 'install') {
            $this->forceRegisterExtensions();
        }
        
        // Clean up any zombie entries
        $this->removeZombieExtensions();
        
        // Ensure manifest directories exist
        $this->ensureManifestDirectories();
        
        // Copy manifests to canonical locations
        $this->copyManifestsRobust();
    }

    public function uninstall($parent)
    {
        // Clean up files when uninstalled
        $this->removeOrphanedFiles();
    }

    /**
     * Force removal of ALL extension entries related to this package.
     */
    private function forceRemoveAllEntries()
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $table = $db->quoteName('#__extensions');
        
        $elements = [
            'pkg_clubleaddir',
            'pkg_pkg_clubleaddir',
            'com_clubleaddir',
            'mod_clubleaddir',
        ];
        
        foreach ($elements as $element) {
            try {
                $query = $db->getQuery(true)
                    ->delete($table)
                    ->where($db->quoteName('element') . ' = ' . $db->quote($element));
                $db->setQuery($query);
                $db->execute();
            } catch (\Exception $e) {
                // Continue even on error
            }
        }
        
        // Also remove by name pattern
        try {
            $query = $db->getQuery(true)
                ->delete($table)
                ->where('name LIKE ' . $db->quote('%Club Leadership%'));
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {}
    }

    /**
     * Remove any orphaned files from previous broken installs.
     */
    private function removeOrphanedFiles()
    {
        $paths = [
            JPATH_ADMINISTRATOR . '/components/com_clubleaddir',
            JPATH_ROOT . '/administrator/components/com_clubleaddir',
            JPATH_ROOT . '/components/com_clubleaddir',
            JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml',
            JPATH_ROOT . '/administrator/manifests/components/com_clubleaddir.xml',
            JPATH_ROOT . '/modules/mod_clubleaddir',
            JPATH_ADMINISTRATOR . '/modules/mod_clubleaddir',
            JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml',
            JPATH_ROOT . '/administrator/manifests/modules/mod_clubleaddir.xml',
        ];
        
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $this->deleteDir($path);
            } elseif (is_file($path) && strpos($path, 'manifest') !== false) {
                @unlink($path);
            }
        }
    }

    /**
     * Recursively delete a directory.
     */
    private function deleteDir($dir)
    {
        if (!is_dir($dir)) return;
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Force registration of extensions by re-scanning manifests.
     */
    private function forceRegisterExtensions()
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $table = $db->quoteName('#__extensions');
        
        // Check if component is registered, if not add it
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($table)
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_clubleaddir'));
        $db->setQuery($query);
        $id = $db->loadResult();
        
        if (!$id) {
            // Insert component row
            $manifestPath = JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml';
            if (file_exists($manifestPath)) {
                $this->insertExtensionRow($db, 'com_clubleaddir', 'component', $manifestPath);
            }
        }
        
        // Check if module is registered, if not add it
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($table)
            ->where($db->quoteName('element') . ' = ' . $db->quote('mod_clubleaddir'));
        $db->setQuery($query);
        $id = $db->loadResult();
        
        if (!$id) {
            // Insert module row
            $manifestPath = JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml';
            if (file_exists($manifestPath)) {
                $this->insertExtensionRow($db, 'mod_clubleaddir', 'module', $manifestPath);
            }
        }
        
        // Add package row
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($table)
            ->where($db->quoteName('element') . ' = ' . $db->quote('pkg_clubleaddir'));
        $db->setQuery($query);
        $id = $db->loadResult();
        
        if (!$id) {
            $manifestPath = JPATH_ADMINISTRATOR . '/manifests/packages/pkg_clubleaddir.xml';
            if (file_exists($manifestPath)) {
                $this->insertExtensionRow($db, 'pkg_clubleaddir', 'package', $manifestPath);
            }
        }
    }

    /**
     * Insert an extension row.
     */
    private function insertExtensionRow($db, $element, $type, $manifestPath)
    {
        $xml = simplexml_load_file($manifestPath);
        
        $columns = [
            'name' => (string) $xml->name ?? $element,
            'type' => $type,
            'element' => $element,
            'client_id' => ($type === 'component' || $type === 'package') ? 0 : 1,
            'manifest_cache' => json_encode([
                'name' => (string) $xml->name ?? $element,
                'version' => (string) $xml->version ?? '1.0.0',
                'author' => (string) $xml->author ?? '',
            ]),
            'enabled' => 1,
            'protected' => 0,
            'access' => 1,
        ];
        
        // Add package-specific fields
        if ($type === 'package') {
            $columns['version'] = (string) $xml->version ?? '1.0.0';
        }
        
        $query = $db->getQuery(true)->insert($table)->columns(array_keys($columns))->values(
            '(' . implode(',', array_map([$db, 'quote'], array_values($columns))) . ')'
        );
        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Remove zombie extension entries.
     */
    private function removeZombieExtensions()
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $table = $db->quoteName('#__extensions');
        
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($table)
            ->where($db->quoteName('element') . ' = ' . $db->quote('pkg_pkg_clubleaddir'));
        $db->setQuery($query);
        $id = $db->loadResult();
        
        if ($id) {
            $del = $db->getQuery(true)
                ->delete($table)
                ->where($db->quoteName('extension_id') . ' = ' . (int) $id);
            $db->setQuery($del);
            $db->execute();
        }
    }

    /**
     * Ensure manifest directories exist with proper permissions.
     */
    private function ensureManifestDirectories()
    {
        $dirs = [
            JPATH_ADMINISTRATOR . '/manifests/components',
            JPATH_ADMINISTRATOR . '/manifests/modules',
            JPATH_ADMINISTRATOR . '/manifests/packages',
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Copy manifests from installed locations to manifest directories.
     */
    private function copyManifestsRobust()
    {
        // Component
        $src = JPATH_ADMINISTRATOR . '/components/com_clubleaddir/com_clubleaddir.xml';
        $dst = JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml';
        if (is_file($src) && !is_file($dst)) {
            copy($src, $dst);
        }
        
        // Module
        $src = JPATH_SITE . '/modules/mod_clubleaddir/mod_clubleaddir.xml';
        $dst = JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml';
        if (is_file($src) && !is_file($dst)) {
            copy($src, $dst);
        }
    }
}