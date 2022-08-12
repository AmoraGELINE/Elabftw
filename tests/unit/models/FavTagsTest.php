<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Models;

use Elabftw\Elabftw\TagParam;

class FavTagsTest extends \PHPUnit\Framework\TestCase
{
    private FavTags $FavTags;

    protected function setUp(): void
    {
        $this->FavTags = new FavTags(new Users(1, 1));
    }

    public function testCreate(): void
    {
        $Tags = new Tags(new Experiments(new Users(1, 1), 1));
        $param = new TagParam('test-tag');
        $Tags->create($param);
        $this->assertEquals(1, $this->FavTags->create($param));
        $this->assertEquals(0, $this->FavTags->create($param));
    }

    public function testRead(): void
    {
        $this->assertIsArray($this->FavTags->readAll());
    }

    public function testDestroy(): void
    {
        $this->FavTags->setId(1);
        $this->assertTrue($this->FavTags->destroy());
    }
}
