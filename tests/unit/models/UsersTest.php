<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Models;

use Elabftw\Elabftw\ContentParams;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Exceptions\ResourceNotFoundException;
use Elabftw\Maps\UserPreferences;

class UsersTest extends \PHPUnit\Framework\TestCase
{
    private Users $Users;

    protected function setUp(): void
    {
        $this->Users= new Users(1, 1);
    }

    public function testPopulate(): void
    {
        $this->assertTrue(is_array($this->Users->userData));
        $this->expectException(ResourceNotFoundException::class);
        new Users(1337);
    }

    public function testAllowUntrustedLogin(): void
    {
        $this->assertFalse($this->Users->allowUntrustedLogin());
        $this->assertTrue((new Users(2, 1))->allowUntrustedLogin());
    }

    public function testRead(): void
    {
        $res = $this->Users->read(new ContentParams('php'));
        $this->assertEquals('1 - Phpunit TestUser - phpunit@example.com', $res[0]);
    }

    public function testReadAllFromTeam(): void
    {
        $this->assertIsArray($this->Users->readAllFromTeam());
    }

    public function testUpdateAccount(): void
    {
        $params = array(
            'email' => 'tata@yopmail.com',
            'firstname' => 'Tata',
            'lastname' => 'Yep',
            'orcid' => '0000-0002-7494-5555',
            'password' => 'new super password',
        );
        $this->assertIsArray((new Users(4))->patch($params));
    }

    public function testUpdatePreferences(): void
    {
        $prefsArr = array(
            'limit_nb' => 12,
            'sc_create' => 'c',
            'sc_edit' => 'e',
            'sc_submit' => 's',
            'sc_todo' => 't',
            'show_team' => 'on',
            'lang' => 'en_GB',
            'pdf_format' => 'A4',
            'default_vis' => 'organization',
            'display_size' => 'lg',
            'display_mode' => 'it',
            'sort' => 'date',
            'orderby' => 'desc',
        );
        $Prefs = new UserPreferences((int) $this->Users->userData['userid']);
        $Prefs->hydrate($prefsArr);
        $Prefs->save();

        // reload from db
        $u = new Users(1, 1);
        $this->assertEquals($u->userData['limit_nb'], '12');
    }

    public function testGetLockedUsersCount(): void
    {
        $this->assertIsInt($this->Users->getLockedUsersCount());
    }

    public function testUpdateTooShortPassword(): void
    {
        $Users = new Users(4);
        $this->expectException(ImproperActionException::class);
        $Users->patch(array('password' => 'short'));
    }

    public function testInvalidateToken(): void
    {
        $this->assertTrue($this->Users->invalidateToken());
    }

    public function testValidate(): void
    {
        // current user is already validated but that's ok
        $this->assertIsArray($this->Users->validate());
    }

    public function testToggleArchive(): void
    {
        $Users = new Users(4);
        $this->assertTrue($Users->toggleArchive());
    }

    public function testUnArchiveButAnotherUserExists(): void
    {
        // this user is archived already
        $Users = new Users(4);
        // create another active user with the same email
        $NewUser = ExistingUser::fromScratch($Users->userData['email'], array('Alpha'), 'f', 'l', 4, false, false);
        // try to unarchive
        $this->expectException(ImproperActionException::class);
        $Users->toggleArchive();
    }

    public function testLockExperiments(): void
    {
        $Users = new Users(4);
        $this->assertTrue($this->Users->lockExperiments());
    }

    public function testDestroy(): void
    {
        $Users = ExistingUser::fromScratch('osef@example.com', array('Alpha'), 'f', 'l', 4, false, false);
        $this->assertTrue($Users->destroy());
    }

    public function testDestroyWithExperiments(): void
    {
        $this->expectException(ImproperActionException::class);
        $this->Users->destroy();
    }
}
