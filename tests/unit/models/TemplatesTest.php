<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Models;

use Elabftw\Elabftw\CreateTemplate;
use Elabftw\Elabftw\EntityParams;

class TemplatesTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $this->Templates= new Templates(new Users(1, 1));
    }

    public function testCreate()
    {
        $this->Templates->create(new CreateTemplate('Test tpl', 'pwet'));
    }

    public function testRead()
    {
        $this->Templates->setId(1);
        $this->assertTrue(is_array($this->Templates->read()));
    }

    public function testGetWriteableTemplatesList()
    {
        $this->assertTrue(is_array($this->Templates->getWriteableTemplatesList()));
    }

    public function testDuplicate()
    {
        $this->Templates->setId(1);
        $this->assertIsInt($this->Templates->duplicate());
    }

    public function testReadForUser()
    {
        $this->assertTrue(is_array($this->Templates->readForUser()));
    }

    public function testUpdate()
    {
        $this->Templates->setId(1);
        $this->Templates->update(new EntityParams('Database item 1', 'title'));
        $this->Templates->update(new EntityParams('20160729', 'date'));
        $this->Templates->update(new EntityParams('pwet', 'body'));
    }

    public function testDestroy()
    {
        $this->Templates->setId(1);
        $this->Templates->destroy();
    }
}
