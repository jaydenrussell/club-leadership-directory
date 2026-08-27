<?php
use PHPUnit\Framework\TestCase;

class StoreTest extends TestCase
{
    private $tmp;
    private $store;

    protected function setUp(): void
    {
        $this->tmp = tempnam(sys_get_temp_dir(), 'clble_store_');
        @unlink($this->tmp);
        $this->tmp .= '.json';
        require_once __DIR__ . '/../com_clubleaddir/admin/store/Store.php';
        // Force JSON backend by using non-sqlite path via direct instantiation
        $this->store = new ClubleaddirStoreJson($this->tmp);
    }
    protected function tearDown(): void { @unlink($this->tmp); @unlink($this->tmp . '.corrupt-' . date('Ymd-His')); }

    public function testInsertAndGetAllOrdering()
    {
        $id1 = $this->store->insert(['name'=>'Zoe','type'=>'officer','role'=>'Treasurer','ordering'=>2,'published'=>1,'status'=>'active']);
        $id2 = $this->store->insert(['name'=>'Ann','type'=>'officer','role'=>'President','ordering'=>1,'published'=>1,'status'=>'active']);
        $id3 = $this->store->insert(['name'=>'Bob','type'=>'director','ordering'=>1,'published'=>1,'status'=>'active']);
        $all = $this->store->getAll([]);
        $this->assertCount(3, $all);
        // ORDER BY type,ordering,name — director before officer (string cmp)
        $this->assertEquals('director', $all[0]->type);
        $this->assertEquals('officer', $all[1]->type);
        $this->assertEquals('Ann', $all[1]->name); // President ordering 1 before Treasurer 2
    }

    public function testPublishedFilter()
    {
        $this->store->insert(['name'=>'A','type'=>'director','ordering'=>1,'published'=>1,'status'=>'active']);
        $this->store->insert(['name'=>'B','type'=>'director','ordering'=>2,'published'=>0,'status'=>'active']);
        $pub = $this->store->getAll(['published'=>1]);
        $this->assertCount(1, $pub);
        $this->assertEquals('A', $pub[0]->name);
    }

    public function testWithDefaultsExpands()
    {
        $id = $this->store->insert(['name'=>'X','type'=>'staff','ordering'=>5]);
        $row = $this->store->getById($id);
        $this->assertEquals('', $row->bio);
        $this->assertEquals(0, $row->vacant);
        $this->assertEquals('active', $row->status);
    }
}
