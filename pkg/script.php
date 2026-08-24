<?php
/**
 * Package install/uninstall script for Club Leadership Directory.
 * Self-cleaning: removes all previous versions and zombie entries
 */

defined('_JEXEC') or die;

class pkg_clubleaddirInstallerScript
{
    public function preflight($stage, $parent)
    {
        $this->cleanPreviousBrokenInstalls();
    }

    /**
     * POSTFLIGHT - runs AFTER children install
     * Ensures everything is properly registered
     */
    public function postflight($stage, $parent)
    {
        // Only run for install stage
        if ($stage !== 'install') {
            return;
        }

        // Force manifest directory creation
        $this->createManifestDirectories();

        // Copy manifests if not already there
        $this->copyManifestsToCanonicalLocations();

        // Verify extension registration - Joomla should have done this
        $this->verifyOrFixExtensionRegistration();
    }

    public function install($parent)
    {
        // Nothing to do - Joomla handles installation
    }

    public function uninstall($parent)
    {
        $this->completeCleanup();
    }

    private function cleanPreviousBrokenInstalls()
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $table = $db->quoteName('#__extensions');

        // Remove ALL possible element variations
        $elements_to_remove = [
            'pkg_clubleaddir',
            'pkg_pkg_clubleaddir',  // The broken variant
            'com_clubleaddir',
            'mod_clubleaddir',
        ];

        foreach ($elements_to_remove as $element) {
            try {
                $query = $db->getQuery(true)
                    ->delete($table)
                    ->where($db->quoteName('element') . ' = ' . $db->quote($element));
                $db->setQuery($query);
                $db->execute();
            } catch (\Exception $e) {
                // Silently ignore
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

        // Clean up any orphaned files from previous installs
        $this->removeOrphanedFiles();
    }

    private function createManifestDirectories()
    {
        $dirs = [
            JPATH_ADMINISTRATOR . '/manifests/components',
            JPATH_ADMINISTRATOR . '/manifests/modules',
            JPATH_ADMINISTRATOR . '/manifests/packages',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
    }

    private function copyManifestsToCanonicalLocations()
    {
        // Component manifest
        $src = JPATH_ADMINISTRATOR . '/components/com_clubleaddir/com_clubleaddir.xml';
        $dst = JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml';
        if (is_file($src)) {
            copy($src, $dst);
        }

        // Module manifest
        $src = JPATH_SITE . '/modules/mod_clubleaddir/mod_clubleaddir.xml';
        $dst = JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml';
        if (is_file($src)) {
            copy($src, $dst);
        }

        // Package manifest
        $src = JPATH_ADMINISTRATOR . '/manifests/packages/pkg_clubleaddir.xml';
        if (!is_file($src)) {
            // Create package manifest if missing
            $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
            $xml .= '<extension type="package" version="2.5" method="upgrade">' . "\n";
            $xml .= '    <name>Club Leadership Directory</name>' . "\n";
            $xml .= '    <packagename>clubleaddir</packagename>' . "\n";
            $xml .= '    <version>2.0.143</version>' . "\n";
            $xml .= '</extension>';
            file_put_contents($src, $xml);
        }
    }

    private function verifyOrFixExtensionRegistration()
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $table = $db->quoteName('#__extensions');

        $extensions = [
            ['element' => 'com_clubleaddir', 'type' => 'component', 'client_id' => 0],
            ['element' => 'mod_clubleaddir', 'type' => 'module', 'client_id' => 1],
            ['element' => 'pkg_clubleaddir', 'type' => 'package', 'client_id' => 0],
        ];

        foreach ($extensions as $ext) {
            $exists = $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($table)
                ->where($db->quoteName('element') . ' = ' . $db->quote($ext['element']))
                ->toSql();

            $db->setQuery("SELECT COUNT(*) FROM " . $db->quoteName('#__extensions') . " WHERE element = " . $db->quote($ext['element']));
            $count = $db->loadResult();

            if ($count == 0) {
                // Try to insert using Joomla's standard approach
                try {
                    // Load manifest
                    $manifest = ($ext['element'] === 'mod_clubleaddir')
                        ? JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml'
                        : JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml';

                    if ($ext['element'] === 'pkg_clubleaddir') {
                        $manifest = JPATH_ADMINISTRATOR . '/manifests/packages/pkg_clubleaddir.xml';
                    }

                    if (is_file($manifest)) {
                        $xml = simplexml_load_file($manifest);
                        $name = (string) ($xml->name ?? $ext['element']);
                        $version = (string) ($xml->version ?? '1.0.0');

                        // Use Joomla's INSERT with proper escaping
                        $query = "INSERT INTO #__extensions 
                            (name, type, element, client_id, manifest_cache, enabled, protected, access, version) 
                            VALUES 
                            ('" . $db->escape($name) . "', '" . $db->escape($ext['type']) . "', 
                             '" . $db->escape($ext['element']) . "', " . (int)$ext['client_id'] . ",
                             '" . $db->escape(json_encode(['name'=>$name, 'version'=>$version])) . "', 
                             1, 0, 1, '" . $db->escape($version) . "')";
                        
                        $db->setQuery($query);
                        $db->execute();
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail
                }
            }
        }
    }

    private function removeOrphanedFiles()
    {
        $paths = [
            JPATH_ADMINISTRATOR . '/components/com_clubleaddir',
            JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml',
            JPATH_ROOT . '/administrator/components/com_clubleaddir',
            JPATH_ROOT . '/components/com_clubleaddir',
            
            JPATH_ADMINISTRATOR . '/modules/mod_clubleaddir',
            JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml',
            JPATH_ROOT . '/administrator/modules/mod_clubleaddir',
            JPATH_ROOT . '/modules/mod_clubleaddir',
            
            JPATH_ADMINISTRATOR . '/manifests/packages/pkg_clubleaddir.xml',
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $this->deleteDirRecursive($path);
            } elseif (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function deleteDirRecursive($dir)
    {
        if (!is_dir($dir)) return;
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function completeCleanup()
    {
        // Remove database entries
        $this->cleanPreviousBrokenInstalls();
        
        // Remove all files
        $this->removeOrphanedFiles();
    }
}