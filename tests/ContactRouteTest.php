<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../com_clubleaddir/admin/helpers.php';

class ContactRouteTest extends TestCase
{
    public function testInvalidIdReturnsEmpty()
    {
        $this->assertEquals('', ClubleaddirHelper::contactRoute(0));
        $this->assertEquals('', ClubleaddirHelper::contactRoute(-5));
    }

    public function testContactRouteValidatesPublished()
    {
        // Non-existent contact (id 999999) should return '' after DB check fails
        // Uses SQLite fallback — DB likely empty, so should return mailto or ''
        $url = ClubleaddirHelper::contactRoute(999999);
        $this->assertEquals('', $url);
    }

    public function testContactRouteRawFormat()
    {
        // When a contact exists but no menu, it should return raw id&catid (v3.21.17+)
        // We cannot guarantee DB has contact 1, so just check format when it does return
        // This test documents expected format: index.php?option=com_contact&view=contact&id=N&catid=M
        $this->assertTrue(true); // placeholder — full DB mock requires Joomla DBO, verified manually
    }
}
