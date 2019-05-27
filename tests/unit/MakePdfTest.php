<?php declare(strict_types=1);

namespace Elabftw\Models;

use Elabftw\Services\MakePdf;

class MakePdfTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $this->Users = new Users(1);
        $this->Entity = new Experiments($this->Users);
        $this->MakePdf = new MakePdf($this->Entity);
    }

    public function testOutput()
    {
        // TODO use https://github.com/mikey179/vfsStream/wiki/Example
        // see https://phpunit.de/manual/current/en/test-doubles.html#test-doubles.mocking-the-filesystem
        //$this->MakePdf->output(true, true);
        //$this->assertFileExists($this->MakePdf->filePath);
    }
}
