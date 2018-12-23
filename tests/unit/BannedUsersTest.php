<?php
namespace Elabftw\Elabftw;

class BannedUsersTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
        $this->BannedUsers= new BannedUsers(new Config);
    }

    public function testCreate()
    {
        $fingerprint = md5('yep');
        $this->assertTrue($this->BannedUsers->create($fingerprint));
    }

    public function testReadAll()
    {
        $this->assertTrue(is_array($this->BannedUsers->readAll()));
    }
}
