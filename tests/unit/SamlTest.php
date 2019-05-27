<?php declare(strict_types=1);

namespace Elabftw\Elabftw;

use Elabftw\Models\Config;
use Elabftw\Models\Idps;

class SamlTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $this->Saml = new Saml(new Config(), new Idps());
    }

    public function testgetSettings()
    {
        $this->assertTrue(is_array($this->Saml->getSettings(1)));
    }
}
