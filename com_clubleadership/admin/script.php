<?php
/**
 * Install / update script.
 *
 * Creates the isolated data directory under /media/com_clubleadership/data
 * and locks it down so the SQLite/JSON file can never be fetched over HTTP.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_clubleadership
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;

class ComClubLeadershipInstallerScript
{
    private function initDataDir()
    {
        $dir = JPATH_ROOT . '/media/com_clubleadership/data';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Apache: deny direct web access to the data file.
        $ht = $dir . '/.htaccess';
        if (is_dir($dir) && !is_file($ht)) {
            @file_put_contents($ht, "Deny from all\n");
        }

        // Any other server: empty index to prevent directory listing.
        $idx = $dir . '/index.html';
        if (is_dir($dir) && !is_file($idx)) {
            @file_put_contents($idx, '');
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
