<?php
/**
 * Package install/uninstall script.
 *
 * Joomla 3's PackageAdapter uninstalls each child extension by reading the
 * child's manifest from administrator/manifests/components|modules/<element>.xml.
 * If that file is missing (e.g. a prior broken install left a zombie row), the
 * child uninstall fails with "Missing manifest file" and the whole package
 * uninstall aborts.
 *
 * This script guarantees those canonical manifest files exist after EVERY
 * install/update by copying them from the deployed extension folders.
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
     * Ensure the component and module manifests exist at their canonical
     * Joomla locations so that uninstall (and Discover) can find them.
     */
    private function syncManifests()
    {
        $done = array();

        // component: administrator/components/com_clubleaddir/clubleaddir.xml
        //   -> administrator/manifests/components/com_clubleaddir.xml
        $srcComp = JPATH_ADMINISTRATOR . '/components/com_clubleaddir/clubleaddir.xml';
        $dstComp = JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml';
        if (is_file($srcComp) && (!is_file($dstComp) || md5_file($srcComp) !== md5_file($dstComp))) {
            try {
                JFile::copy($srcComp, $dstComp);
                $done[] = 'component';
            } catch (\Throwable $e) {
                // Try plain copy as fallback
                @copy($srcComp, $dstComp);
            }
        }

        // module: modules/mod_clubleaddir/mod_clubleaddir.xml
        //   -> administrator/manifests/modules/mod_clubleaddir.xml
        $srcMod = JPATH_ROOT . '/modules/mod_clubleaddir/mod_clubleaddir.xml';
        $dstMod = JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml';
        if (is_file($srcMod) && (!is_file($dstMod) || md5_file($srcMod) !== md5_file($dstMod))) {
            try {
                JFile::copy($srcMod, $dstMod);
                $done[] = 'module';
            } catch (\Throwable $e) {
                @copy($srcMod, $dstMod);
            }
        }

        return $done;
    }

    public function install($parent)
    {
        $this->syncManifests();
        return true;
    }

    public function update($parent)
    {
        $this->syncManifests();
        return true;
    }

    public function postflight($type, $parent)
    {
        $this->syncManifests();
        return true;
    }

    public function uninstall($parent)
    {
        return true;
    }
}
