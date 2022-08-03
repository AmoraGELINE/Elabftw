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

class LinksTest extends \PHPUnit\Framework\TestCase
{
    private Experiments $Experiments;

    protected function setUp(): void
    {
        $this->Experiments = new Experiments(new Users(1, 1), 3);
    }

    public function testCreateReadDestroy(): void
    {
        $this->Experiments->Links->create(new ContentParams('1', extra: array('targetEntity' => 'items')));
        $this->Experiments->Links->create(new ContentParams('2', extra: array('targetEntity' => 'experiments')));
        $this->assertIsArray($this->Experiments->Links->readAll());
        $this->assertIsArray($this->Experiments->Links->readOne());
        $this->Experiments->Links->setId(1);
        $this->Experiments->Links->destroy(new ContentParams(extra: array('targetEntity' => 'items')));
        $this->Experiments->Links->setId(2);
        $this->Experiments->Links->destroy(new ContentParams(extra: array('targetEntity' => 'experiments')));
    }

    public function testUpdate(): void
    {
        $this->assertFalse($this->Experiments->Links->update(new ContentParams('blah')));
    }

    public function testImport(): void
    {
        // create a link in a db item
        $Items = new Items(new Users(1, 1), 1);
        $Items->Links->create(new ContentParams('1', extra: array('targetEntity' => 'items')));
        // now import this in our experiment like if we click the import links button
        $Links = new Links($this->Experiments, $Items->id);
        $this->assertTrue($Links->import('items'));
    }

    public function testReadRelated(): void
    {
        $this->Experiments->Links->create(new ContentParams('1', extra: array('targetEntity' => 'items')));
        $this->Experiments->Links->create(new ContentParams('2', extra: array('targetEntity' => 'experiments')));
        (new Experiments(new Users(1, 1), 2))->Links->readRelated();
        (new Items(new Users(1, 1), 1))->Links->readRelated();
    }
}
