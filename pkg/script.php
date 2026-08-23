<?php
/**
 * Package install/uninstall script for Club Leadership Directory.
 * 
 * v2.0.139+ ensures clean installation with proper database registration.
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
        // Remove orphaned files first
        $this->removeOrphanedFiles();
    }

    /**
     * Postflight runs AFTER children are installed.
     * Ensure everything is registered and manifests are in place.
     */
    public function postflight($stage, $parent)
    {
        // Ensure manifest directories exist
        $this->ensureManifestDirectories();
        
        // Copy manifests to canonical locations
        $this->copyManifestsRobust();
        
        // Force register extensions if they don't exist
        $this->forceRegisterExtensions();
    }

    public function uninstall($parent)
    {
        // Clean up files when uninstalled
        $this->removeOrphanedFiles();
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
            JPATH_ADMINISTRATOR . '/manifests/packages/pkg_clubleaddir.xml',
            JPATH_ROOT . '/administrator/manifests/packages/pkg_clubleaddir.xml',
        ];
        
        $db = \Joomla\CMS\Factory::getDbo();
        $table = $db->quoteName('#__extensions');
        
        // Remove zombie extension entries
        $elements = ['pkg_clubleaddir', 'pkg_pkg_clubleaddir', 'com_clubleaddir', 'mod_clubleaddir'];
        foreach ($elements as $element) {
            $query = $db->getQuery(true)
                ->delete($table)
                ->where($db->quoteName('element') . ' = ' . $db->quote($element));
            $db->setQuery($query);
            try { $db->execute(); } catch (\Exception $e) {}
        }
        
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $this->deleteDir($path);
            } elseif (is_file($path)) {
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
        $db = \Joomla\CMS\Factory::getDbo();
        $table = $db->quoteName('#__extensions');
        
        // Component manifest
        $src = JPATH_ADMINISTRATOR . '/components/com_clubleaddir/com_clubleaddir.xml';
        $dst = JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml';
        if (is_file($src) && !is_file($dst)) {
            copy($src, $dst);
            $this->registerExtension($db, 'com_clubleaddir', 'component', $dst);
        }
        
        // Module manifest
        $src = JPATH_SITE . '/modules/mod_clubleaddir/mod_clubleaddir.xml';
        $dst = JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml';
        if (is_file($src) && !is_file($dst)) {
            copy($src, $dst);
            $this->registerExtension($db, 'mod_clubleaddir', 'module', $dst);
        }
    }

    /**
     * Force registration of extensions by reading manifests.
     */
    private function forceRegisterExtensions()
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $table = $db->quoteName('#__extensions');
        
        // Check and register component
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($table)
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_clubleaddir'));
        $db->setQuery($query);
        
        if (!$db->loadResult()) {
            $manifest = JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml';
            if (is_file($manifest)) {
                $this->registerExtension($db, 'com_clubleaddir', 'component', $manifest);
            }
        }
        
        // Check and register module
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($table)
            ->where($db->quoteName('element') . ' = ' . $db->quote('mod_clubleaddir'));
        $db->setQuery($query);
        
        if (!$db->loadResult()) {
            $manifest = JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml';
            if (is_file($manifest)) {
                $this->registerExtension($db, 'mod_clubleaddir', 'module', $manifest);
            }
        }
        
        // Check and register package
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($table)
            ->where($db->quoteName('element') . ' = ' . $db->quote('pkg_clubleaddir'));
        $db->setQuery($query);
        
        if (!$db->loadResult()) {
            $manifest = JPATH_ADMINISTRATOR . '/manifests/packages/pkg_clubleaddir.xml';
            if (is_file($manifest)) {
                $this->registerExtension($db, 'pkg_clubleaddir', 'package', $manifest);
            }
        }
    }

    /**
     * Register an extension from its manifest.
     */
    private function registerExtension($db, $element, $type, $manifestPath)
    {
        $xml = simplexml_load_file($manifestPath);
        
        $name = (string) ($xml->name ?? $element);
        $version = (string) ($xml->version ?? '1.0.0');
        $author = (string) ($xml->author ?? '');
        
        $columns = [
            $db->quoteName('name') . ' = ' . $db->quote($name),
            $db->quoteName('type') . ' = ' . $db->quote($type),
            $db->quoteName('element') . ' = ' . $db->quote($element),
            $db->quoteName('client_id') . ' = ' . (($type === 'component' || $type === 'package') ? '0' : '1'),
            $db->quoteName('manifest_cache') . ' = ' . $db->quote(json_encode([
                'name' => $name,
                'version' => $version,
                'author' => $author,
            ])),
            $db->quoteName('enabled') . ' = 1',
            $db->quoteName('protected') . ' = 0',
            $db->quoteName('access') . ' = 1',
        ];
        
        if ($type === 'package') {
            $columns[] = $db->quoteName('version') . ' = ' . $db->quote($version);
        }
        
        $query = $db->getQuery(true)
            ->insert($table)
            ->columns(array_map(function($c) use ($db) { 
                return preg_replace('/^' . $db->quoteName('') . '/(.*) =/', $db->quoteName('$1') . ' =', $c); 
            }, $columns))
            ->columns(['name', 'type', 'element', 'client_id', 'manifest_cache', 'enabled', 'protected', 'access'])
            ->values('(' . implode(',', array_map(function($c) { return preg_replace('/^[^=]+ = /', '', $c); }, $columns)) . ')');
        
        // Simpler approach: build query properly
        $query = "INSERT INTO #__extensions (name, type, element, client_id, manifest_cache, enabled, protected, access";
        if ($type === 'package') $query .= ", version";
        $query .= ") VALUES ("
             . ", " . $db->quote($name) . ", " . $db->quote($type) . ", " . $db->quote($element) . ", "
             . ($type === 'component' || $type === 'package' ? '0' : '1') . ", "
             . $db->quote(json_encode(['name'=>$name,'version'=>$version,'author'=>$author])) . ", 1, 0, 1";
        if ($type === 'package') $query .= ", " . $db->quote($version);
        $query .= ")";
        
        $db->setQuery($query);
        $db->execute();
    }
}