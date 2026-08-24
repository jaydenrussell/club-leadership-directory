<?php
/**
 * Package install/uninstall script for Club Leadership Directory.
 * Self-contained: only touches this package's own entries/files.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Extension\Manager;

class pkg_clubleaddirInstallerScript
{
    public function preflight($stage, $parent)
    {
        // Do not delete core files or DB rows here.
    }

    public function postflight($stage, $parent)
    {
        if ($stage !== 'install') {
            return;
        }

        $db = Factory::getDbo();

        foreach (['com_clubleaddir', 'mod_clubleaddir', 'pkg_clubleaddir'] as $element) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($element));
            $db->setQuery($query);
            $count = (int) $db->loadResult();

            if (!$count) {
                $this->createExtensionEntry($db, $element);
            }
        }
    }

    public function install($parent)
    {
        // Let Joomla's native installer copy files and register children.
    }

    public function uninstall($parent)
    {
        $this->removePackageFiles();
    }

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

        $data = [
            'name' => $name,
            'type' => $type,
            'element' => $element,
            'client_id' => $clientId,
            'manifest_cache' => json_encode(['name' => $name, 'version' => $version]),
            'enabled' => 1,
            'protected' => 0,
            'access' => 1,
        ];

        if ($type === 'package') {
            $data['version'] = $version;
        }

        $columns = array_keys($data);
        $values = array_values($data);

        $query = 'INSERT INTO #__extensions (' . implode(',', array_map([$db, 'quoteName'], $columns)) . ') VALUES (';
        $params = [];
        foreach ($values as $v) {
            $params[] = is_int($v) ? (string) $v : $db->quote($v);
        }
        $query .= implode(',', $params) . ')';

        try {
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            // Silent fail; install can still complete.
        }
    }

    private function removePackageFiles()
    {
        $paths = [
            JPATH_ADMINISTRATOR . '/components/com_clubleaddir',
            JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml',
            JPATH_SITE . '/modules/mod_clubleaddir',
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
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
