<?php
/**
 * Club Leadership Directory — minimal streaming zip read/write.
 *
 * Zero-dependency: no ext-zip is required, so behaviour and memory use are
 * identical on every host (including cheap shared hosting). Entries are
 * deflated/inflated in 64 KiB chunks through PHP's streaming zlib API, so
 * peak memory stays near-constant regardless of archive size or file count;
 * whole archives are NEVER buffered. Every entry is verified on read
 * (length + CRC-32) and all sizes are validated against hard caps, so a
 * crafted archive cannot trigger a zip bomb or an absurd central directory.
 *
 * The on-disk layout is ordinary PKZIP 2.0 (deflate method 8, no data
 * descriptors, UTF-8 filename flag), readable and writable by any zip tool.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_clubleaddir
 * @copyright   Copyright (C) 2026 Jayden Russell. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

class ClubleaddirZip
{
    const MAX_ENTRY   = 16777216;  // 16 MiB per uncompressed entry; keeps the worst single inflate block comfortably inside a 128 MiB limit
    const MAX_TOTAL   = 268435456; // 256 MiB total uncompressed
    const MAX_ENTRIES = 5000;      // entries tolerated in one archive
    const CHUNK       = 65536;
    const MAX_CENTRAL = 16777216; // 16 MiB cap for the central directory blob

    /**
     * 32-bit-safe CRC helpers. hexdec() on 32-bit PHP overflows values >= 2^31
     * to float, and (int)/dechex() then misbehave on that platform, so all
     * numeric CRC handling is expressed as byte-wise hex manipulation that is
     * bit-identical on any PHP build.
     */
    private static function crcLEBytes($hex8)
    {
        $hex8 = strtolower((string) $hex8);
        $hex8 = substr($hex8 . '00000000', 0, 8);
        return pack('H*', implode('', array_reverse(str_split($hex8, 2))));
    }

    private static function crcHex($crc)
    {
        $v = (float) $crc;
        $v = fmod($v, 4294967296.0);
        if ($v < 0) {
            $v += 4294967296.0;
        }
        $hex = '';
        for ($i = 3; $i >= 0; $i--) {
            $pow = 1.0;
            for ($k = 0; $k < $i; $k++) {
                $pow *= 256.0;
            }
            $hex .= sprintf('%02x', (int) floor(fmod($v / $pow, 256.0)));
        }
        return $hex;
    }

    /**
     * BC write: $files as a name => content map. Small entries only.
     */
    public static function write($path, array $files)
    {
        $entries = array();
        foreach ($files as $name => $data) {
            $entries[] = array('name' => (string) $name, 'data' => (string) $data);
        }
        return self::writeStream($path, $entries);
    }

    /**
     * Stream-write an archive. Each $entry is array('name' => ..., 'data' => ...)
     * or array('name' => ..., 'path' => ...); 'path' entries are streamed from
     * disk in chunks so memory stays flat. Returns false on any failure and
     * removes the partial output.
     */
    public static function writeStream($path, $entries)
    {
        $out = @fopen($path, 'wb');
        if (!$out) {
            return false;
        }

        $central      = '';
        $count        = 0;

        foreach ($entries as $entry) {
            if (!is_array($entry) || $count >= self::MAX_ENTRIES) {
                fclose($out);
                @unlink($path);
                return false;
            }

            $name = (string) ($entry['name'] ?? '');
            if ($name === '' || strpos($name, "\0") !== false) {
                fclose($out);
                @unlink($path);
                return false;
            }

            $localOffset = (int) ftell($out);

            if (!self::wr($out, "PK\x03\x04"
                . pack('v', 20)
                . pack('v', 0x0800)
                . pack('v', 8)
                . pack('v', 0)
                . pack('v', 0)
                . pack('V', 0)
                . pack('V', 0)
                . pack('V', 0)
                . pack('v', strlen($name))
                . pack('v', 0)
                . $name)) {
                fclose($out);
                @unlink($path);
                return false;
            }

            $ctx  = deflate_init(ZLIB_ENCODING_RAW);
            $hash = hash_init('crc32b');
            $usize = 0;
            $csize = 0;

            if ($ctx === false) {
                fclose($out);
                @unlink($path);
                return false;
            }

            if (isset($entry['path'])) {
                $src = @fopen($entry['path'], 'rb');
                if (!$src) {
                    fclose($out);
                    @unlink($path);
                    return false;
                }
                while (!feof($src)) {
                    $chunk = fread($src, self::CHUNK);
                    if ($chunk === false) {
                        fclose($src);
                        fclose($out);
                        @unlink($path);
                        return false;
                    }
                    if ($chunk === '') {
                        break;
                    }
                    $usize += strlen($chunk);
                    hash_update($hash, $chunk);
                    $outChunk = deflate_add($ctx, $chunk, ZLIB_NO_FLUSH);
                    if ($outChunk === false) {
                        fclose($src);
                        fclose($out);
                        @unlink($path);
                        return false;
                    }
                    if ($outChunk !== '') {
                        if (!self::wr($out, $outChunk)) {
                            fclose($src);
                            fclose($out);
                            @unlink($path);
                            return false;
                        }
                        $csize += strlen($outChunk);
                    }
                }
                fclose($src);
            } else {
                $data = (string) ($entry['data'] ?? '');
                $usize += strlen($data);
                hash_update($hash, $data);
                $outChunk = deflate_add($ctx, $data, ZLIB_NO_FLUSH);
                if ($outChunk === false) {
                    fclose($out);
                    @unlink($path);
                    return false;
                }
                if ($outChunk !== '') {
                    if (!self::wr($out, $outChunk)) {
                        fclose($out);
                        @unlink($path);
                        return false;
                    }
                    $csize += strlen($outChunk);
                }
            }

            $fin = deflate_add($ctx, '', ZLIB_FINISH);
            if ($fin === false) {
                fclose($out);
                @unlink($path);
                return false;
            }
            if ($fin !== '') {
                if (!self::wr($out, $fin)) {
                    fclose($out);
                    @unlink($path);
                    return false;
                }
                $csize += strlen($fin);
            }

            $crcHex = hash_final($hash);

            fseek($out, $localOffset + 14, SEEK_SET);
            if (!self::wr($out, self::crcLEBytes($crcHex) . pack('V', $csize) . pack('V', $usize))) {
                fclose($out);
                @unlink($path);
                return false;
            }
            fseek($out, 0, SEEK_END);

            $central .= "PK\x01\x02"
                . pack('v', 20) . pack('v', 20)
                . pack('v', 0x0800)
                . pack('v', 8)
                . pack('v', 0) . pack('v', 0)
                . self::crcLEBytes($crcHex)
                . pack('V', $csize)
                . pack('V', $usize)
                . pack('v', strlen($name))
                . pack('v', 0) . pack('v', 0) . pack('v', 0) . pack('v', 0)
                . pack('V', 0)
                . pack('V', $localOffset)
                . $name;

            $count++;
        }

        $eocd = "PK\x05\x06"
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', $count)
            . pack('v', $count)
            . pack('V', strlen($central))
            . pack('V', (int) ftell($out))
            . pack('v', 0);

        if (!self::wr($out, $central . $eocd)) {
            fclose($out);
            @unlink($path);
            return false;
        }
        fclose($out);
        return true;
    }

    private static function wr($fh, $bytes)
    {
        $len = strlen($bytes);
        return @fwrite($fh, $bytes) === $len;
    }

    /**
     * Buffer the whole archive as name => content. Fine for small archives
     * and tests; the production import path uses iterate() instead.
     */
    public static function readAll($path)
    {
        $out = array();
        $ok  = self::iterate($path, function ($name, $tmp, $meta) use (&$out) {
            $data = @file_get_contents($tmp);
            @unlink($tmp);
            if ($data === false) {
                return false;
            }
            $out[$name] = $data;
            return true;
        });
        return $ok ? $out : false;
    }

    /**
     * Validate a zip and stream each file entry to $sink ($name, $tmpFile,
     * $meta). Entries are inflated in chunks into a temp file, verified, and
     * then handed to the sink; $sink must read or move the temp file (it is
     * deleted afterwards if still present). Return false from $sink to abort.
     * Returns false if the archive is invalid or exceeds the caps.
     */
    public static function iterate($path, $sink)
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return false;
        }

        $entries = self::readCentral($fh);
        if ($entries === false) {
            fclose($fh);
            return false;
        }

        $total = 0;
        foreach ($entries as $e) {
            if ($e['name'] === '' || substr($e['name'], -1) === '/') {
                continue;
            }
            if ($e['usize'] > self::MAX_ENTRY || $total + $e['usize'] > self::MAX_TOTAL) {
                fclose($fh);
                return false;
            }

            if ($e['method'] !== 8 && $e['method'] !== 0) {
                fclose($fh);
                return false;
            }

            fseek($fh, $e['localOff']);
            if (fread($fh, 4) !== "PK\x03\x04") {
                fclose($fh);
                return false;
            }
            $lh = fread($fh, 26);
            if (strlen($lh) !== 26) {
                fclose($fh);
                return false;
            }
            $lNameLen  = (int) unpack('v', substr($lh, 22, 2))[1];
            $lExtraLen = (int) unpack('v', substr($lh, 24, 2))[1];
            $dataStart = $e['localOff'] + 30 + $lNameLen + $lExtraLen;
            fseek($fh, $dataStart);

            $ctx = null;
            if ($e['method'] === 8) {
                $ctx = inflate_init(ZLIB_ENCODING_RAW);
                if ($ctx === false) {
                    fclose($fh);
                    return false;
                }
            }

            $tmp = tempnam(sys_get_temp_dir(), 'clublead');
            if ($tmp === false) {
                fclose($fh);
                return false;
            }
            $outf = @fopen($tmp, 'wb');
            if (!$outf) {
                @unlink($tmp);
                fclose($fh);
                return false;
            }

            $hash     = hash_init('crc32b');
            $remaining = $e['csize'];
            $produced = 0;
            $okFlag   = true;

            while ($remaining > 0) {
                $chunk = fread($fh, min(self::CHUNK, $remaining));
                if ($chunk === false || $chunk === '') {
                    $okFlag = false;
                    break;
                }
                $remaining -= strlen($chunk);
                if ($e['method'] === 8) {
                    $plain = @inflate_add($ctx, $chunk, ($remaining > 0) ? ZLIB_NO_FLUSH : ZLIB_FINISH);
                    if ($plain === false) {
                        $okFlag = false;
                        break;
                    }
                } else {
                    $plain = $chunk;
                }
                if ($plain !== '') {
                    hash_update($hash, $plain);
                    fwrite($outf, $plain);
                    $produced += strlen($plain);
                    if ($produced > self::MAX_ENTRY) {
                        $okFlag = false;
                        break;
                    }
                }
            }

            fclose($outf);

            if (!$okFlag || $produced !== $e['usize']) {
                @unlink($tmp);
                fclose($fh);
                return false;
            }
            if (self::crcHex($e['crc']) !== hash_final($hash)) {
                @unlink($tmp);
                fclose($fh);
                return false;
            }

            $total += $produced;

            $keep = $sink(
                $e['name'],
                $tmp,
                array(
                    'method' => $e['method'],
                    'crc'    => $e['crc'],
                    'csize'  => $e['csize'],
                    'usize'  => $e['usize'],
                )
            );
            if (is_file($tmp)) {
                @unlink($tmp);
            }
            if ($keep === false) {
                fclose($fh);
                return false;
            }
        }

        fclose($fh);
        return true;
    }

    /**
     * Parse the end-of-central-directory record and the central directory
     * itself into a lightweight entry table. Bounded: only the (small)
     * central directory is read into memory, never the file data.
     */
    private static function readCentral($fh)
    {
        $fs = @filesize(@stream_get_meta_data($fh)['uri'] ?? '');
        if ($fs === false || $fs < 22) {
            return false;
        }

        $scanLen = (int) min($fs, 22 + 65535);
        fseek($fh, $fs - $scanLen);
        $tail = fread($fh, $scanLen);
        if (strlen($tail) !== $scanLen) {
            return false;
        }
        $eocd = strrpos($tail, "PK\x05\x06");
        if ($eocd === false) {
            return false;
        }
        $eabs = ($fs - $scanLen) + $eocd;

        fseek($fh, $eabs + 10);
        $cb = fread($fh, 10);
        if (strlen($cb) !== 10) {
            return false;
        }
        $count       = (int) unpack('v', substr($cb, 0, 2))[1];
        $centralSize = (int) unpack('V', substr($cb, 2, 4))[1];
        $centralPos  = (int) unpack('V', substr($cb, 6, 4))[1];

        if ($count <= 0 || $count > self::MAX_ENTRIES) {
            return false;
        }
        if ($centralSize <= 0 || $centralSize > self::MAX_CENTRAL) {
            return false;
        }
        if ($centralPos < 0 || $centralPos + $centralSize > $fs) {
            return false;
        }

        fseek($fh, $centralPos);
        $buf = fread($fh, $centralSize);
        if (strlen($buf) !== $centralSize) {
            return false;
        }

        $entries = array();
        $pos = 0;
        $blen = strlen($buf);

        for ($i = 0; $i < $count; $i++) {
            if (substr($buf, $pos, 4) !== "PK\x01\x02") {
                return false;
            }
            $method   = (int) unpack('v', substr($buf, $pos + 10, 2))[1];
            $crc      = unpack('V', substr($buf, $pos + 16, 4))[1]; // float on 32-bit PHP; crcHex() normalises both
            $csize    = (int) unpack('V', substr($buf, $pos + 20, 4))[1];
            $usize    = (int) unpack('V', substr($buf, $pos + 24, 4))[1];
            $nlen     = (int) unpack('v', substr($buf, $pos + 28, 2))[1];
            $elen     = (int) unpack('v', substr($buf, $pos + 30, 2))[1];
            $clen     = (int) unpack('v', substr($buf, $pos + 32, 2))[1];
            $localOff = (int) unpack('V', substr($buf, $pos + 42, 4))[1];
            $name     = substr($buf, $pos + 46, $nlen);

            $pos += 46 + $nlen + $elen + $clen;
            if ($pos > $blen) {
                return false;
            }
            if ($localOff < 0 || $localOff >= $fs) {
                return false;
            }
            // 32-bit PHP: unpack('V') wraps values >= 2^31 to negative ints;
            // any negative size is never legitimate and would evade caps.
            if ($csize < 0 || $usize < 0) {
                return false;
            }

            $entries[] = array(
                'name'     => $name,
                'method'   => $method,
                'crc'      => $crc,
                'csize'    => $csize,
                'usize'    => $usize,
                'localOff' => $localOff,
            );
        }

        return $entries;
    }
}