<?php
/**
 * Package install/uninstall script for Club Leadership Directory v2.0.144
 * Uses Joomla's Extension class to ensure proper registration
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\Manager;
use Joomla\CMS\Factory;

class pkg_clubleaddirInstallerScript
{
    public function preflight($stage, $parent)
    {
        // Clean orphaned files BEFORE installation, but do NOT delete DB rows here.
        $this->removeOrphanedFiles();
    }

    public function postflight($stage, $parent)
    {
        if ($stage !== 'install') {
            return;
        }

        // Ensure everything is properly set up
        $this->setupExtension();
    }

    public function install($parent)
    {
        // Let Joomla do the work during install
    }

    public function uninstall($parent)
    {
        $this->completeCleanup();
    }

    /**
     * Force clean ANY previous installation state
     */
    private function forceCleanState()
    {
        $db = Factory::getDbo();
        $table = $db->quoteName('#__extensions');

        // Delete all related extension entries
        $elements = ['pkg_clubleaddir', 'pkg_pkg_clubleaddir', 'com_clubleaddir', 'mod_clubleaddir'];
        
        foreach ($elements as $element) {
            $query = $db->getQuery(true)
                ->delete($table)
                ->where($db->quoteName('element') . ' = ' . $db->quote($element));
            $db->setQuery($query);
            $db->execute();
        }

        // Delete by name
        $query = $db->getQuery(true)
            ->delete($table)
            ->where('name LIKE ' . $db->quote('%Club Leadership%'));
        $db->setQuery($query);
        $db->execute();

        // Clean files
        $this->removeOrphanedFiles();
    }

    /**
     * Setup extension properly using Joomla's approach
     */
    private function setupExtension()
    {
        $db = Factory::getDbo();
        
        // Ensure manifest directories exist
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

        // Copy manifests to authoritative locations
        $compSrc = JPATH_ADMINISTRATOR . '/components/com_clubleaddir/com_clubleaddir.xml';
        $compDst = JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml';
        if (is_file($compSrc) && !is_file($compDst)) {
            copy($compSrc, $compDst);
        }

        $modSrc = JPATH_SITE . '/modules/mod_clubleaddir/mod_clubleaddir.xml';
        $modDst = JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml';
        if (is_file($modSrc) && !is_file($modDst)) {
            copy($modSrc, $modDst);
        }

        // Check if entries already exist
        foreach (['com_clubleaddir', 'mod_clubleaddir', 'pkg_clubleaddir'] as $element) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($element));
            $db->setQuery($query);
            $count = $db->loadResult();

            if (!$count) {
                // Create using simple, direct INSERT
                $this->createExtensionEntry($db, $element);
            }
        }
    }

    /**
     * Create single extension entry - simple INSERT
     */
    private function createExtensionEntry($db, $element)
    {
        if ($element === 'pkg_clubleaddir') {
            $type = 'package';
        } elseif ($element === 'com_clubleaddir') {
            $type = 'component';
        } else {
            $type = 'module';
        }

        $clientId = 0;
        if ($type === 'module') {
            $clientId = 1;
        }

        if ($type === 'component') {
            $manifestPath = JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml';
        } elseif ($type === 'module') {
            $manifestPath = JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml';
        } else {
            $manifestPath = JPATH_ADMINISTRATOR . '/manifests/packages/pkg_clubleaddir.xml';
        }

        if (!is_file($manifestPath)) {
            return;
        }

        $xml = simplexml_load_file($manifestPath);
        $name = (string) ($xml->name ?? $element);
        $version = (string) ($xml->version ?? '1.0.0');
        
        // Simple data
        $data = [
            'name' => $name,
            'type' => $type,
            'element' => $element,
            'client_id' => $clientId,
            'manifest_cache' => json_encode(['name'=>$name, 'version'=>$version]),
            'enabled' => 1,
            'protected' => 0,
            'access' => 1,
        ];
        
        if ($type === 'package') {
            $data['version'] = $version;
        }

        // Build INSERT
        $columns = array_keys($data);
        $values = array_values($data);

        $query = "INSERT INTO #__extensions (" . implode(',', array_map([$db, 'quoteName'], $columns)) . ") VALUES (";
        $params = [];
        foreach ($values as $v) {
            if (is_int($v)) {
                $params[] = (string)$v;
            } else {
                $params[] = $db->quote($v);
            }
        }
        $query .= implode(',', $params) . ')';

        try {
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            // Silent fail - logged elsewhere
        }
    }

    private function removeOrphanedFiles()
    {
        $paths = [
            JPATH_ADMINISTRATOR . '/components/com_clubleaddir',
            JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml',
            JPATH_ADMINISTRATOR . '/modules/mod_clubleaddir',
            JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml',
            JPATH_ADMINISTRATOR . '/manifests/packages/pkg_clubleaddir.xml',
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $this->deleteDir($path);
            } elseif (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function deleteDir($dir)
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function completeCleanup()
    {
        $this->forceCleanState();
    }
}