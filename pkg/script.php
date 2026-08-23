<?php
/**
 * Package install/uninstall script for Club Leadership Directory.
 * v2.0.140+ - Fixed SQL syntax for extension registration
 */

defined('_JEXEC') or die;

class pkg_clubleaddirInstallerScript
{
    public function preflight($stage, $parent)
    {
        $this->removeOrphanedFiles();
    }

    public function postflight($stage, $parent)
    {
        $this->ensureManifestDirectories();
        $this->copyManifestsRobust();
        $this->forceRegisterExtensions();
    }

    public function uninstall($parent)
    {
        $this->removeOrphanedFiles();
    }

    private function removeOrphanedFiles()
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $table = $db->quoteName('#__extensions');

        $elements = ['pkg_clubleaddir', 'pkg_pkg_clubleaddir', 'com_clubleaddir', 'mod_clubleaddir'];
        foreach ($elements as $element) {
            try {
                $query = $db->getQuery(true)
                    ->delete($table)
                    ->where($db->quoteName('element') . ' = ' . $db->quote($element));
                $db->setQuery($query);
                $db->execute();
            } catch (\Exception $e) {}
        }

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

    private function forceRegisterExtensions()
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $table = $db->quoteName('#__extensions');

        // Register component if missing
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($table)
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_clubleaddir'));
        $db->setQuery($query);

        if (!$db->loadResult()) {
            $manifest = JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml';
            if (is_file($manifest)) {
                $this->insertExtensionRow($db, 'com_clubleaddir', 'component', $manifest);
            }
        }

        // Register module if missing
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($table)
            ->where($db->quoteName('element') . ' = ' . $db->quote('mod_clubleaddir'));
        $db->setQuery($query);

        if (!$db->loadResult()) {
            $manifest = JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml';
            if (is_file($manifest)) {
                $this->insertExtensionRow($db, 'mod_clubleaddir', 'module', $manifest);
            }
        }

        // Register package if missing
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($table)
            ->where($db->quoteName('element') . ' = ' . $db->quote('pkg_clubleaddir'));
        $db->setQuery($query);

        if (!$db->loadResult()) {
            $manifest = JPATH_ADMINISTRATOR . '/manifests/packages/pkg_clubleaddir.xml';
            if (is_file($manifest)) {
                $this->insertExtensionRow($db, 'pkg_clubleaddir', 'package', $manifest);
            }
        }
    }

    private function insertExtensionRow($db, $element, $type, $manifestPath)
    {
        $xml = simplexml_load_file($manifestPath);

        $name = (string) ($xml->name ?? $element);
        $version = (string) ($xml->version ?? '1.0.0');
        $author = (string) ($xml->author ?? '');
        $manifestCache = json_encode([
            'name' => $name,
            'version' => $version,
            'author' => $author,
        ]);

        $client_id = ($type === 'component' || $type === 'package') ? 0 : 1;

        // Use Joomla's query builder properly
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__extensions'))
            ->columns([
                $db->quoteName('name'),
                $db->quoteName('type'),
                $db->quoteName('element'),
                $db->quoteName('client_id'),
                $db->quoteName('manifest_cache'),
                $db->quoteName('enabled'),
                $db->quoteName('protected'),
                $db->quoteName('access'),
            ])
            ->values(implode(',', [
                $db->quote($name),
                $db->quote($type),
                $db->quote($element),
                $db->val($client_id),
                $db->quote($manifestCache),
                '1',
                '0',
                '1',
            ]));

        if ($type === 'package') {
            $query->columns($db->quoteName('version'))
                  ->values($db->quote($version));
        }

        $db->setQuery($query);
        $db->execute();
    }
}