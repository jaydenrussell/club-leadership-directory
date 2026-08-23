<?php
/**
 * ONE-TIME MANIFEST REPAIR SCRIPT
 * Delete this file after it completes automatically!
 */
error_reporting(E_ALL);

$base = defined('JPATH_BASE') ? JPATH_BASE : realpath(__DIR__ . '/../');

if (!defined('JPATH_ADMINISTRATOR')) {
    require_once $base . '/includes/defines.php';
    require_once $base . '/includes/framework.php';
}

// Copy component manifest
$src = JPATH_ADMINISTRATOR . '/components/com_clubleaddir/script.php';
$dst = JPATH_ADMINISTRATOR . '/manifests/components/com_clubleaddir.xml';

// Ensure target dir exists
if (!is_dir(dirname($dst))) {
    mkdir(dirname($dst), 0755, true);
}

// Component: the manifest is at components/com_clubleaddir/script.php (source file!)
// But wait - the actual manifest XML is clubleaddir.xml not script.php!
// Let's find the ACTUAL manifest file...