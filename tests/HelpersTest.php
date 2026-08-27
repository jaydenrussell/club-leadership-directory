<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../com_clubleaddir/admin/helpers.php';

class HelpersTest extends TestCase
{
    public function testSortForDisplayOfficerRank()
    {
        $items = [
            (object)['type'=>'officer','role'=>'Treasurer','ordering'=>1,'name'=>'A'],
            (object)['type'=>'officer','role'=>'President','ordering'=>1,'name'=>'B'],
            (object)['type'=>'officer','role'=>'Secretary','ordering'=>1,'name'=>'C'],
        ];
        $sorted = ClubleaddirHelper::sortForDisplay($items);
        $this->assertEquals('President', $sorted[0]->role);
        $this->assertEquals('Secretary', $sorted[1]->role);
        $this->assertEquals('Treasurer', $sorted[2]->role);
    }

    public function testSortForDisplayDirectorsOrdering()
    {
        $items = [
            (object)['type'=>'director','role'=>'Z','ordering'=>2,'name'=>'Z'],
            (object)['type'=>'director','role'=>'A','ordering'=>1,'name'=>'A'],
        ];
        $sorted = ClubleaddirHelper::sortForDisplay($items);
        $this->assertEquals(1, $sorted[0]->ordering);
        $this->assertEquals(2, $sorted[1]->ordering);
    }

    public function testLeagueNameLabel()
    {
        $this->assertStringContainsString('Day', ClubleaddirHelper::leagueNameLabel('day_ladies'));
        $this->assertEquals('unknown', ClubleaddirHelper::leagueNameLabel('unknown'));
    }
}
