<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Models;

use Elabftw\Elabftw\PermissionsDefaults;
use Elabftw\Enums\Action;

class ItemsTypesTest extends \PHPUnit\Framework\TestCase
{
    private ItemsTypes $ItemsTypes;

    protected function setUp(): void
    {
        $this->ItemsTypes= new ItemsTypes(new Users(1, 1));
    }

    public function testCreateUpdateDestroy(): void
    {
        $extra = array(
            'color' => '#faaccc',
            'body' => 'body',
            'canread' => PermissionsDefaults::MY_TEAMS,
            'canwrite' => PermissionsDefaults::MY_TEAMS,
            'bookable' => '0',
        );
        $this->ItemsTypes->setId($this->ItemsTypes->create('new'));
        $this->ItemsTypes->patch(Action::Update, $extra);
        $this->assertTrue($this->ItemsTypes->destroy());
    }
}
