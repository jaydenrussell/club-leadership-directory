<?php
use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function testManifestsExistAndVersionConsistent()
    {
        $com = simplexml_load_file(__DIR__ . '/../com_clubleaddir/com_clubleaddir.xml');
        $mod = simplexml_load_file(__DIR__ . '/../mod_clubleaddir/mod_clubleaddir.xml');
        $pkg = simplexml_load_file(__DIR__ . '/../pkg/pkg_clubleaddir.xml');
        $this->assertNotEmpty((string)$com->version);
        $this->assertEquals((string)$com->version, (string)$mod->version);
        $this->assertEquals((string)$com->version, (string)$pkg->version);
    }

    public function testSiteLanguageHasDayLadies()
    {
        $ini = parse_ini_file(__DIR__ . '/../com_clubleaddir/site/language/en-GB/en-GB.com_clubleaddir.ini');
        $this->assertArrayHasKey('COM_CLUBLEADDIR_LEAGUE_DAY_LADIES', $ini);
    }

    public function testAdminListHasRoleColumn()
    {
        $html = file_get_contents(__DIR__ . '/../com_clubleaddir/admin/views/leaderships/tmpl/default.php');
        $this->assertStringContainsString('COM_CLUBLEADDIR_HEADING_ROLE', $html);
        $this->assertStringContainsString('colspan="9"', $html);
    }

    public function testBuildExcludesDeadFiles()
    {
        $build = file_get_contents(__DIR__ . '/../build-pkgs.py');
        $this->assertStringContainsString('EXCLUDE_COMP', $build);
        $this->assertStringNotContainsString('mod_clubleaddir/helper.php', $build); // not excluded globally
    }

    public function testNoPatInReleaseScript()
    {
        $rel = file_get_contents(__DIR__ . '/../release-v312.py');
        $this->assertStringNotContainsString('gho_', $rel);
        $this->assertStringContainsString('GH_TOKEN', $rel);
    }
}
