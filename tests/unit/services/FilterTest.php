<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Services;

use Elabftw\Exceptions\ImproperActionException;
use function str_repeat;

class FilterTest extends \PHPUnit\Framework\TestCase
{
    public function testKdate(): void
    {
        $this->assertEquals('1969-07-21', Filter::kdate('1969-07-21'));
        $this->assertEquals(date('Y-m-d'), Filter::kdate('3902348923'));
        $this->assertEquals(date('Y-m-d'), Filter::kdate('Sun is shining'));
        $this->assertEquals(date('Y-m-d'), Filter::kdate("\n"));
    }

    public function testSanitize(): void
    {
        $this->assertEquals('', Filter::sanitize('<img></img>'));
    }

    public function testTitle(): void
    {
        $this->assertEquals('My super title', Filter::title('My super title'));
        $this->assertEquals('Yep ', Filter::title("Yep\n"));
        $this->assertEquals('Untitled', Filter::title(''));
    }

    public function testBlankValueOnDuplicate(): void
    {
        $json = '{"extra_fields":{"To blank":{"type":"text","value":"some value","position":1,"blank_value_on_duplicate":true}}}';
        $blankedJson = '{"extra_fields":{"To blank":{"type":"text","value":"","position":1,"blank_value_on_duplicate":true}}}';
        $this->assertEquals($blankedJson, Filter::blankExtraFieldsValueOnDuplicate($json));

        $json = '{"extra_fields":{"To blank":{"type":"text","value":"some value","position":1}}}';
        $blankedJson = '{"extra_fields":{"To blank":{"type":"text","value":"some value","position":1}}}';
        $this->assertEquals($blankedJson, Filter::blankExtraFieldsValueOnDuplicate($json));
    }

    public function testBody(): void
    {
        $this->assertEquals('my body', Filter::body('my body'));
        $this->assertEquals('my body', Filter::body('my body<script></script>'));
        $this->expectException(ImproperActionException::class);
        $body = str_repeat('a', 4120001);
        Filter::body($body);
    }

    public function testForFilesystem(): void
    {
        $this->assertEquals('blah', Filter::forFilesystem('=blah/'));
    }
}
