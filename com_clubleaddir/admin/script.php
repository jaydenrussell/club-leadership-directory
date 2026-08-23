<?php
/**
 * Install / update script.
 *
 * Creates the isolated data directory under /media/com_clubleaddir/data
 * and locks it down so the SQLite/JSON file can never be fetched over HTTP.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;

class ComClubleaddirInstallerScript
{
    private function initDataDir()
    {
        $dir = JPATH_ROOT . '/media/com_clubleaddir/data';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Apache: deny direct web access to the data file, using syntax that
        // works on both Apache 2.2 and 2.4.
        $ht = $dir . '/.htaccess';
        if (is_dir($dir) && !is_file($ht)) {
            @file_put_contents($ht,
                "<Files *>\n"
              . "    Require all denied\n"
              . "</Files>\n"
              . "# Apache 2.2 fallback\n"
              . "<IfModule !mod_authz_core.c>\n"
              . "    Deny from all\n"
              . "</IfModule>\n"
            );
        }

        // Any other server: empty index to prevent directory listing.
        $idx = $dir . '/index.html';
        if (is_dir($dir) && !is_file($idx)) {
            @file_put_contents($idx, '');
        }

        $this->initUploadDir();
    }

    /**
     * Create the public photo upload directory and lock down script execution
     * inside it as defense-in-depth (uploads are MIME-checked, so no RCE is
     * possible, but we never want a user-supplied file executed as code).
     */
    private function initUploadDir()
    {
        $dir = JPATH_ROOT . '/images/clubleaddir/photos';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (is_dir($dir)) {
            $ht = $dir . '/.htaccess';
            if (!is_file($ht)) {
                @file_put_contents($ht,
                    "<IfModule mod_php.c>\n"
                  . "    php_flag engine off\n"
                  . "</IfModule>\n"
                  . "<IfModule mod_negotiation.c>\n"
                  . "    Options -MultiViews\n"
                  . "</IfModule>\n"
                  . "AddType text/plain .php .phtml .php3 .php4 .php5 .php7 .pht .phps .cgi .pl .py .asp .aspx .jsp .shtml\n"
                  . "<FilesMatch \"\\.(php|phtml|pht|phps|cgi|pl|py|asp|aspx|jsp|shtml)$\">\n"
                  . "    Require all denied\n"
                  . "</FilesMatch>\n"
                );
            }

            $idx = $dir . '/index.html';
            if (!is_file($idx)) {
                @file_put_contents($idx, '');
            }
        }
    }

    public function install($parent)
    {
        $this->initDataDir();
        return true;
    }

    public function update($parent)
    {
        $this->initDataDir();
        return true;
    }

    public function discover_install($parent)
    {
        $this->initDataDir();
        return true;
    }

    /**
     * Force the component to enabled state after install/update.
     *
     * A prior broken install can leave a disabled (enabled = 0) extension row
     * in #__extensions. Because this package uses method="upgrade", Joomla
     * reuses that row and inherits the disabled flag, which makes every admin
     * request fail with "404 Component not found" even though the files are
     * present. Re-enabling here makes the package self-healing.
     */
    public function postflight($type, $parent)
    {
        try {
            $db = \Joomla\CMS\Factory::getDbo();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_clubleaddir'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
            $db->setQuery($query)->execute();
        } catch (\Throwable $e) {
            // Non-fatal: a clean install already enables the component.
        }

        $this->syncManifests();
        $this->cleanupLegacyPackage();
        $this->fixUpdateSite();
        $this->createStealthContactMenu();

        return true;
    }

    /**
     * Guarantee the component AND module manifests exist at the canonical Joomla
     * locations (administrator/manifests/components|modules/<element>.xml).
     *
     * Joomla's package uninstall reads these to remove each child; if they are
     * missing (e.g. a prior broken install left a zombie row), child uninstall
     * fails with "Missing manifest file" and the whole package uninstall aborts.
     */
    private function syncManifests()
    {
        // Component manifest: admin/components/com_clubleaddir/com_clubleaddir.xml
        //   -> administrator/manifests/components/com_clubleaddir.xml
        $srcComp = JPATH_ADMINISTRATOR . '/components/com_clubleaddir/com_clubleaddir.xml';
        $dstComp = JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml';
        if (is_file($srcComp)) {
            if (!is_dir(dirname($dstComp))) {
                @mkdir(dirname($dstComp), 0755, true);
            }
            if (!is_file($dstComp) || md5_file($srcComp) !== md5_file($dstComp)) {
                @copy($srcComp, $dstComp);
            }
        }

        // Module manifest: modules/mod_clubleaddir/mod_clubleaddir.xml
        //   -> administrator/manifests/modules/mod_clubleaddir.xml
        $srcMod = JPATH_ROOT . '/modules/mod_clubleaddir/mod_clubleaddir.xml';
        $dstMod = JPATH_ADMINISTRATOR . '/manifests/modules/mod_clubleaddir.xml';
        if (is_file($srcMod)) {
            if (!is_dir(dirname($dstMod))) {
                @mkdir(dirname($dstMod), 0755, true);
            }
            if (!is_file($dstMod) || md5_file($srcMod) !== md5_file($dstMod)) {
                @copy($srcMod, $dstMod);
            }
        }
    }

    /**
     * Remove the legacy broken package row left by older releases.
     *
     * Older versions shipped <packagename>pkg_clubleaddir</packagename>, which
     * Joomla turned into the element "pkg_pkg_clubleaddir". That element never
     * matched the manifest filename (pkg_clubleaddir.xml), so the package could
     * not be uninstalled ("Missing manifest file") and lingered as a zombie.
     * This deletes that orphan row so the site is clean.
     */
    private function cleanupLegacyPackage()
    {
        try {
            $db = \Joomla\CMS\Factory::getDbo();
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('package'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('pkg_pkg_clubleaddir'));
            $db->setQuery($query)->execute();
        } catch (\Throwable $e) {
            // Non-fatal.
        }
    }

    /**
     * Create a HIDDEN menu ("hiddenmenu") plus a single Contact menu item
     * (alias "inquire") pointing at the Info Joomla contact (id 7).
     *
     * The leadership directory's vacant/contact links then resolve to a clean
     * SEF alias (/inquire) instead of the raw component route
     * (index.php?option=com_contact&view=contact&id=7...), so the underlying
     * com_contact internals are not exposed in the URL.
     *
     * The menu is hidden (no module is ever created for it) so it never appears
     * in site navigation, but it is still routable.
     */
    private function createStealthContactMenu()
    {
        $log = array('START stealth menu');
        try {
            $db       = \Joomla\CMS\JFactory::getDbo();
            $menuType = 'hiddenmenu';
            $contactId = 7; // "Info" Joomla contact configured in the module

            $log[] = 'DB_OK';

            // 1. Hidden menu type.
            $exists = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__menu_types'))
                    ->where($db->quoteName('menutype') . ' = ' . $db->quote($menuType))
            )->loadResult();
            $log[] = 'menuTypeExists=' . $exists;

            if ($exists === 0) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->insert($db->quoteName('#__menu_types'))
                        ->columns(array($db->quoteName('menutype'), $db->quoteName('title'), $db->quoteName('description')))
                        ->values($db->quote($menuType) . ', ' . $db->quote('Hidden Menu') . ', ' . $db->quote('Stealth contact aliases'))
                )->execute();
                $log[] = 'menuTypeCreated';
            }

            // 2. Contact menu item for the Info contact.
            $itemExists = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__menu'))
                    ->where($db->quoteName('menutype') . ' = ' . $db->quote($menuType))
                    ->where($db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_contact&view=contact&id=' . $contactId))
            )->loadResult();
            $log[] = 'itemExists=' . $itemExists;

            if ($itemExists === 0) {
                $componentId = (int) $db->setQuery(
                    $db->getQuery(true)
                        ->select('extension_id')
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote('com_contact'))
                        ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                )->loadResult();
                $log[] = 'componentId=' . $componentId;

                $params = json_encode(array('contact_id' => (string) $contactId, 'show_page_heading' => 0));

                $item = (object) array(
                    'menutype'     => $menuType,
                    'title'        => 'Inquire',
                    'alias'        => 'inquire',
                    'link'         => 'index.php?option=com_contact&view=contact&id=' . $contactId,
                    'type'         => 'component',
                    'published'    => 1,
                    'parent_id'    => 1,
                    'level'        => 1,
                    'component_id' => $componentId,
                    'ordering'     => 0,
                    'checked_out'  => 0,
                    'browserNav'   => 0,
                    'access'       => 1,
                    'img'          => '',
                    'client_id'    => 0,
                    'home'         => 0,
                    'params'       => $params,
                    'language'     => '*',
                );
                $db->insertObject('#__menu', $item);
                $id = (int) $db->insertid();
                $log[] = 'insertedId=' . $id;

                if ($id > 0 && class_exists('JTable')) {
                    $table = \JTable::getInstance('Menu', 'JTable');
                    if ($table && $table->load($id)) {
                        $table->setLocation(1, 'last-child');
                        $table->store();
                        $table->rebuildPath($id);
                        $log[] = 'nestedSetFixed';
                    } else {
                        $log[] = 'tableLoadFailed';
                    }
                }
            }
            $log[] = 'DONE';
        } catch (\Throwable $e) {
            $log[] = 'EXCEPTION: ' . $e->getMessage();
        }

        try {
            $file = JPATH_ROOT . '/inquire_debug.log';
            file_put_contents($file, date('c') . ' ' . implode(' | ', $log) . "\n", FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Ensure exactly one ENABLED update site pointing at the github.com-hosted
     * update.xml. raw.githubusercontent.com is blocked on some hosting, so we
     * serve the XML from releases/latest/download (github.com, reachable).
     * Joomla otherwise registers package <updateservers> as disabled, which
     * silently breaks update detection.
     */
    private function fixUpdateSite()
    {
        $log = array('START');
        try {
            $db   = \Joomla\CMS\Factory::getDbo();
            $log[] = 'DB_OK';
            $name = 'Club Leadership Directory Update';
            $url  = 'https://jaydenrussell.github.io/club-leadership-directory/update.xml';

            $q = $db->getQuery(true)
                ->delete($db->quoteName('#__update_sites'))
                ->where($db->quoteName('name') . ' = ' . $db->quote($name))
                ->where($db->quoteName('location') . ' LIKE ' . $db->quote('%raw.githubusercontent.com%'));
            $db->setQuery($q)->execute();
            $log[] = 'DELETE_RAW_AFFECTED=' . $db->getAffectedRows();

            $q = $db->getQuery(true)
                ->update($db->quoteName('#__update_sites'))
                ->set($db->quoteName('location') . ' = ' . $db->quote($url))
                ->set($db->quoteName('type') . ' = ' . $db->quote('collection'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('name') . ' = ' . $db->quote($name));
            $db->setQuery($q)->execute();
            $log[] = 'UPDATE_AFFECTED=' . $db->getAffectedRows();

            $q = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__update_sites'))
                ->where($db->quoteName('name') . ' = ' . $db->quote($name))
                ->order($db->quoteName('id') . ' DESC');
            $db->setQuery($q);
            $siteId = (int) $db->loadResult();
            $log[] = 'SITE_ID=' . $siteId;

            if ($siteId > 0) {
                // Packages are detected via the #__update_sites_extensions link,
                // which a plain upgrade does NOT write (only a clean install does).
                // Ensure the link exists so "Check for Updates" finds this
                // collection site for pkg_clubleaddir.
                $pkgId = (int) $db->setQuery(
                    $db->getQuery(true)
                        ->select('extension_id')
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote('pkg_clubleaddir'))
                        ->where($db->quoteName('type') . ' = ' . $db->quote('package'))
                )->loadResult();
                $log[] = 'PKG_ID=' . $pkgId;

                if ($pkgId > 0) {
                    $exists = (int) $db->setQuery(
                        $db->getQuery(true)
                            ->select('COUNT(*)')
                            ->from($db->quoteName('#__update_sites_extensions'))
                            ->where($db->quoteName('update_site_id') . ' = ' . $siteId)
                            ->where($db->quoteName('extension_id') . ' = ' . $pkgId)
                    )->loadResult();
                    if ($exists === 0) {
                        $db->setQuery(
                            $db->getQuery(true)
                                ->insert($db->quoteName('#__update_sites_extensions'))
                                ->columns(array($db->quoteName('update_site_id'), $db->quoteName('extension_id')))
                                ->values($siteId . ', ' . $pkgId)
                        )->execute();
                        $log[] = 'LINKED_TO_PKG';
                    } else {
                        $log[] = 'ALREADY_LINKED';
                    }
                }
            }
        } catch (\Throwable $e) {
            $log[] = 'EXCEPTION: ' . $e->getMessage();
        }

        // Log to administrator/cache (writable, not SEF-intercepted).
        try {
            $file = defined('JPATH_ADMINISTRATOR') ? JPATH_ADMINISTRATOR . '/cache/_clble_update.log' : __DIR__ . '/_clble_update.log';
            file_put_contents($file, date('c') . ' ' . implode(' | ', $log) . "\n", FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function uninstall($parent)
    {
        // Leave the data file in place on uninstall so board history is not
        // destroyed by accident. Site admin can delete the folder manually.
        return true;
    }
}
