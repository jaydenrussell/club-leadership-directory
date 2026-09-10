<?php
/**
 * Club Leadership Directory — minimal zip read/write.
 *
 * Preferred path uses PHP's ZipArchive extension when it is loaded (fast,
 * and able to read zips produced by any tool). A tiny pure-PHP fallback
 * (deflate via zlib, universal) covers cheap hosts where ext-zip is absent.
 * All sizes are validated against caps to avoid zip-bomb decompression.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

class ClubleaddirZip
{
    const MAX_ENTRY = 10485760;  // 10 MiB per uncompressed entry
    const MAX_TOTAL = 67108864;  // 64 MiB total uncompressed

    /**
     * Write $files (name => binary content) to a zip at $path.
     */
    public static function write($path, array $files)
    {
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($path, \ZipArchive::CREATE) !== true) {
                return false;
            }
            foreach ($files as $name => $data) {
                if ($zip->addFromString($name, (string) $data) === false) {
                    $zip->close();
                    @unlink($path);
                    return false;
                }
            }
            if (!$zip->close()) {
                @unlink($path);
                return false;
            }
            return true;
        }

        return self::writeRaw($path, $files);
    }

    /**
     * Read every entry of a zip into memory as name => binary content.
     * Returns false if the archive cannot be parsed or exceeds the caps.
     */
    public static function readAll($path)
    {
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                return false;
            }
            $out = array();
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = (string) $zip->getNameIndex($i);
                if (!$stat || $name === '' || substr($name, -1) === '/') {
                    continue;
                }
                if ((int) ($stat['size'] ?? 0) > self::MAX_ENTRY) {
                    $zip->close();
                    return false;
                }
                $data = $zip->getFromIndex($i);
                if ($data === false || strlen($data) > self::MAX_ENTRY) {
                    $zip->close();
                    return false;
                }
                $out[$name] = $data;
                if (strlen(serialize($out)) > self::MAX_TOTAL) {
                    $zip->close();
                    return false;
                }
            }
            $zip->close();
            return $out;
        }

        return self::readRaw($path);
    }

    protected static function writeRaw($path, array $files)
    {
        $body    = '';
        $central = '';
        $offset  = 0;

        foreach ($files as $name => $data) {
            $data   = (string) $data;
            $crc    = crc32($data) & 0xFFFFFFFF;
            $comp   = gzdeflate($data, 6);
            $csize  = strlen($comp);
            $usize  = strlen($data);
            $nlen   = strlen($name);

            $local = "PK\x03\x04"
                . pack('v', 20)
                . pack('v', 0x0800)
                . pack('v', 8)
                . pack('v', 0)
                . pack('v', 0)
                . pack('V', $crc)
                . pack('V', $csize)
                . pack('V', $usize)
                . pack('v', $nlen)
                . pack('v', 0)
                . $name
                . $comp;

            $central .= "PK\x01\x02"
                . pack('v', 20) . pack('v', 20)
                . pack('v', 0x0800)
                . pack('v', 8)
                . pack('v', 0) . pack('v', 0)
                . pack('V', $crc)
                . pack('V', $csize)
                . pack('V', $usize)
                . pack('v', $nlen)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('V', 0)
                . pack('V', $offset)
                . $name;

            $body   .= $local;
            $offset += strlen($local);
        }

        $eocd = "PK\x05\x06"
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', count($files))
            . pack('v', count($files))
            . pack('V', strlen($central))
            . pack('V', $offset)
            . pack('v', 0);

        return file_put_contents($path, $body . $central . $eocd) !== false;
    }

    protected static function readRaw($path)
    {
        $data = is_file($path) ? @file_get_contents($path) : false;
        if ($data === false) {
            return false;
        }
        $len = strlen($data);
        $eocd = strrpos($data, "PK\x05\x06");
        if ($eocd === false || $eocd > $len) {
            return false;
        }

        $count       = (int) unpack('v', substr($data, $eocd + 10, 2))[1];
        $centralSize = (int) unpack('V', substr($data, $eocd + 12, 4))[1];
        $centralPos  = (int) unpack('V', substr($data, $eocd + 16, 4))[1];
        if ($count <= 0 || $count > 100000 || $centralPos < 0 || $centralPos + $centralSize > $len) {
            return false;
        }

        $out   = array();
        $p     = $centralPos;
        $total = 0;

        for ($i = 0; $i < $count; $i++) {
            if (substr($data, $p, 4) !== "PK\x01\x02") {
                return false;
            }
            $method  = (int) unpack('v', substr($data, $p + 10, 2))[1];
            $crc     = (int) unpack('V', substr($data, $p + 16, 4))[1];
            $csize   = (int) unpack('V', substr($data, $p + 20, 4))[1];
            $usize   = (int) unpack('V', substr($data, $p + 24, 4))[1];
            $nlen    = (int) unpack('v', substr($data, $p + 28, 2))[1];
            $elen    = (int) unpack('v', substr($data, $p + 30, 2))[1];
            $clen    = (int) unpack('v', substr($data, $p + 32, 2))[1];
            $localOff = (int) unpack('V', substr($data, $p + 42, 4))[1];
            $name    = substr($data, $p + 46, $nlen);

            if ($name === '' || substr($name, -1) === '/') {
                $p += 46 + $nlen + $elen + $clen;
                continue;
            }
            if ($usize > self::MAX_ENTRY || $total + $usize > self::MAX_TOTAL) {
                return false;
            }

            if (substr($data, $localOff, 4) !== "PK\x03\x04") {
                return false;
            }
            $lNameLen  = (int) unpack('v', substr($data, $localOff + 26, 2))[1];
            $lExtraLen = (int) unpack('v', substr($data, $localOff + 28, 2))[1];
            $dataStart = $localOff + 30 + $lNameLen + $lExtraLen;
            if ($dataStart + $csize > $len) {
                return false;
            }
            $raw = substr($data, $dataStart, $csize);

            if ($method === 8) {
                $content = @gzinflate($raw);
                if ($content === false) {
                    return false;
                }
            } elseif ($method === 0) {
                $content = $raw;
            } else {
                return false;
            }
            if (strlen($content) !== $usize || crc32($content) !== $crc) {
                return false;
            }

            $out[$name] = $content;
            $total     += strlen($content);
            $p += 46 + $nlen + $elen + $clen;
        }

        return $out;
    }
}