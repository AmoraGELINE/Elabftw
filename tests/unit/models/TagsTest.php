<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Models;

use Elabftw\Enums\Action;
use Elabftw\Exceptions\ImproperActionException;

class TagsTest extends \PHPUnit\Framework\TestCase
{
    private Users $Users;

    private Experiments $Experiments;

    protected function setUp(): void
    {
        $this->Users = new Users(1, 1);
        $this->Experiments = new Experiments($this->Users, 1);
    }

    public function testGetPage(): void
    {
        $this->assertEquals('api/v2/experiments/', $this->Experiments->Tags->getPage());
    }

    public function testCreate(): void
    {
        $this->Experiments->Tags->postAction(Action::Create, array('tag' => 'my tag'));
        $id = $this->Experiments->Tags->postAction(Action::Create, array('tag' => 'new tag'));
        $this->assertIsInt($id);

        // no admin user
        $Users = new Users(2, 1);
        $Items = new Items($Users, 1);
        $Tags = new Tags($Items);
        $id = $Tags->postAction(Action::Create, array('tag' => 'tag2222'));
        $this->assertIsInt($id);
        // now with no rights
        $Teams = new Teams($this->Users, (int) $this->Users->userData['team']);
        $Teams->patch(Action::Update, array('user_create_tag' => 0));
        $this->expectException(ImproperActionException::class);
        $Tags->postAction(Action::Create, array('tag' => 'tag2i222'));
        // bring back config
        $Teams->patch(Action::Update, array('user_create_tag' => 1));
    }

    public function testReadAll(): void
    {
        $this->assertIsArray($this->Experiments->Tags->readAll());
        $this->Experiments->Tags->setId(1);
        $this->assertIsArray($this->Experiments->Tags->readOne());
        /* TODO test with query
        $res = $this->Experiments->Tags->readAll('my');
        $this->assertEquals('my tag', $res[0]['tag']);
         */

        $Items = new Items($this->Users, 1);
        $Tags = new Tags($Items);
        $this->assertIsArray($Tags->readAll());
    }

    public function testGetIdFromTags(): void
    {
        $this->assertContains(1, $this->Experiments->Tags->getIdFromTags(array('my tag')));
        $this->assertEmpty($this->Experiments->Tags->getIdFromTags(array('oOoOoOoOoO')));
    }

    public function testCopyTags(): void
    {
        $this->Experiments->Tags->copyTags(2, true);
    }

    public function testUnreference(): void
    {
        $id = $this->Experiments->Tags->postAction(Action::Create, array('tag' => 'blahblahblah'));
        $Tags = new Tags($this->Experiments, $id);
        $Tags->patch(Action::Unreference, array());
        $this->expectException(ImproperActionException::class);
        $Tags->patch(Action::Timestamp, array());
    }

    public function testDestroy(): void
    {
        $this->Experiments->Tags->destroy();
    }
}
