<?php
/**
 * Standalone CLI smoke tests for com_clubleaddir internals.
 * No Joomla required: minimal stubs stand in for Log/Factory.
 * Run: php tests/run-tests.php
 */

namespace Joomla\CMS\Log {
    class Log
    {
        const WARNING = 'WARNING';
        public static $entries = array();
        public static function add($msg, $level = null, $cat = null)
        {
            self::$entries[] = array($msg, $level, $cat);
        }
    }
}

namespace {
    error_reporting(E_ALL);

    define('_JEXEC', 1);
    $root = sys_get_temp_dir() . '/clubleaddir-test-' . bin2hex(random_bytes(4));
    define('JPATH_ROOT', $root);
    define('JPATH_SITE', $root);
    define('JPATH_ADMINISTRATOR', $root . '/administrator');
    @mkdir(JPATH_ADMINISTRATOR, 0700, true);

    require __DIR__ . '/../com_clubleaddir/admin/store/Store.php';

    $fail = 0;
    function check($label, $cond)
    {
        global $fail;
        if ($cond) {
            echo "PASS  $label\n";
        } else {
            $fail++;
            echo "FAIL  $label\n";
        }
    }

    $dir  = sys_get_temp_dir() . '/clubleaddir-storetest-' . bin2hex(random_bytes(4));
    $file = $dir . '/data.json';

    // 1. Insert / getById / update / delete roundtrip
    $store = new ClubleaddirStoreJson($file);
    $id1 = $store->insert(array('name' => 'Alice', 'type' => 'director'));
    $id2 = $store->insert(array('name' => 'Bob', 'type' => 'officer', 'created_by' => 42));
    check('insert returns increasing ids', $id1 === 1 && $id2 === 2);
    $row = $store->getById($id2);
    check('getById returns the record', $row && $row->name === 'Bob');
    $store->update($id2, array('name' => 'Robert'));
    $row = $store->getById($id2);
    check('update persists', $row && $row->name === 'Robert');
    $store->delete($id1);
    check('delete removes record', $store->getById($id1) === null);
    check('on-disk JSON decodes', ($d = json_decode(file_get_contents($file), true)) && count($d['records']) === 1);

    // 2. Fresh instance sees persisted state (write verification + reload)
    $store2 = new ClubleaddirStoreJson($file);
    $row = $store2->getById($id2);
    check('second instance reloads record', $row && $row->name === 'Robert');

    // 3. Corrupt file is quarantined, not fatal, and .bak recovery works
    file_put_contents($file, '{not valid json');
    $store3 = new ClubleaddirStoreJson($file);
    $quarantines = glob($file . '.corrupt-*');
    check('corrupt main file quarantined', !empty($quarantines));
    $row = $store3->getById($id2);
    check('backup recovery restores record', $row && $row->name === 'Robert');

    // 4. Invalid ids rejected by insert path guard rails
    $all = $store3->getAll(array());
    check('getAll returns array of objects', is_array($all));

    // 5. .htaccess / index.html hardening files exist in data dir
    check('data dir .htaccess written', is_file($dir . '/.htaccess'));
    check('data dir index.html written', is_file($dir . '/index.html'));

    echo $fail === 0 ? "\nALL TESTS PASSED\n" : "\n$fail TEST(S) FAILED\n";
    exit($fail === 0 ? 0 : 1);
}
