<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Services;

class TransformTest extends \PHPUnit\Framework\TestCase
{
    public function testPermission(): void
    {
        $this->assertEquals('Public', Transform::permission('public'));
        $this->assertEquals('Organization', Transform::permission('organization'));
        $this->assertEquals('Team', Transform::permission('team'));
        $this->assertEquals('Owner + Admin(s)', Transform::permission('user'));
        $this->assertEquals('Owner only', Transform::permission('useronly'));
        $this->assertEquals('An error occurred!', Transform::permission('user2'));
    }

    public function testCsrf(): void
    {
        $token = 'fake-token';
        $input = Transform::csrf($token);
        $this->assertEquals("<input type='hidden' name='csrf' value='$token' />", $input);
    }

    public function testNotif(): void
    {
        $expected = '<span class="clickable" data-action="ack-notif" data-id="1" data-href="experiment.php?mode=view&id=2">';
        $expected .= 'Tex rendering failed during PDF generation. Carefully check the generated PDF.';
        $expected .= '</span><br><span class="relative-moment" title="test"></span>';
        $actual = Transform::notif(array(
            'category' => 6,
            'id' => '1',
            'created_at' => 'test',
            'body' => array(
                'entity_page' => 'experiment',
                'entity_id' => '2',
            ),
        ));
        $this->assertEquals($expected, $actual);
    }
}
