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

    public function uninstall($parent)
    {
        // Leave the data file in place on uninstall so board history is not
        // destroyed by accident. Site admin can delete the folder manually.
        return true;
    }
}
