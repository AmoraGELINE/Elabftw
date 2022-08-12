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
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Services\Check;

class ExperimentsTest extends \PHPUnit\Framework\TestCase
{
    private Users $Users;

    private Experiments $Experiments;

    protected function setUp(): void
    {
        $this->Users = new Users(1, 1);
        $this->Experiments = new Experiments($this->Users);
    }

    public function testCreateAndDestroy(): void
    {
        $new = $this->Experiments->create(0);
        $this->assertTrue((bool) Check::id($new));
        $this->Experiments->setId($new);
        $this->Experiments->canOrExplode('write');
        $this->Experiments->toggleLock();
        $this->Experiments->destroy();
        $Templates = new Templates($this->Users);
        $Templates->create('my template');
        $new = $this->Experiments->create(1);
        $this->assertTrue((bool) Check::id($new));
        $this->Experiments = new Experiments($this->Users, $new);
        $this->Experiments->destroy();
    }

    public function testSetId(): void
    {
        $this->expectException(IllegalActionException::class);
        $this->Experiments->setId(0);
    }

    public function testRead(): void
    {
        $new = $this->Experiments->create(0);
        $this->Experiments->setId($new);
        $this->Experiments->canOrExplode('read');
        $experiment = $this->Experiments->readOne();
        $this->assertTrue(is_array($experiment));
        $this->assertEquals('Untitled', $experiment['title']);
        //$this->assertEquals('20160729', $experiment['date']);
    }

    public function testUpdate(): void
    {
        $new = $this->Experiments->create(0);
        $this->Experiments->setId($new);
        $this->assertEquals($new, $this->Experiments->id);
        $this->assertEquals(1, $this->Experiments->Users->userData['userid']);
        $entityData = $this->Experiments->patch(array('title' => 'Untitled', 'date' => '20160729', 'body' => '<p>Body</p>'));
        $this->assertEquals('Untitled', $entityData['title']);
        $this->assertEquals('2016-07-29', $entityData['date']);
        $this->assertEquals('<p>Body</p>', $entityData['body']);
    }

    public function testUpdateVisibility(): void
    {
        $this->Experiments->setId(1);
        $this->assertIsArray($this->Experiments->patch(array('canread' => 'public')));
        $this->assertIsArray($this->Experiments->patch(array('canread' => 'organization')));
        $this->assertIsArray($this->Experiments->patch(array('canwrite' => 'team')));
        $this->assertIsArray($this->Experiments->patch(array('canwrite' => 'public')));
    }

    public function testUpdateCategory(): void
    {
        $this->Experiments->setId(1);
        $this->assertIsArray($this->Experiments->patch(array('category' => '3')));
    }

    public function testDuplicate(): void
    {
        $this->Experiments->setId(1);
        $this->Experiments->canOrExplode('read');
        // add some steps and links in there, too
        $this->Experiments->Steps->postAction(Action::Create, array('body' => 'some step'));
        $this->Experiments->Links->create(3);
        $this->assertIsInt($this->Experiments->duplicate());
    }

    public function testInsertTags(): void
    {
        $this->Experiments->create(0, array('tag-bbbtbtbt', 'tag-auristearuiset'));
    }

    public function testGetTags(): void
    {
        $res = $this->Experiments->getTags(array(array('id' => 0)));
        $this->assertEmpty($res);
        $res = $this->Experiments->getTags(array(array('id' => 1), array('id' => 2)));
        $this->assertIsArray($res);
    }

    public function testAddMetadataFilter(): void
    {
        $this->Experiments->addMetadataFilter('key', 'value');
    }

    public function testGetTimestampThisMonth(): void
    {
        $this->assertEquals(0, $this->Experiments->getTimestampLastMonth());
    }
}
