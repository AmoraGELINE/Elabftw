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
use Elabftw\Elabftw\CreateComment;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Services\Email;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

class CommentsTest extends \PHPUnit\Framework\TestCase
{
    private Experiments $Entity;

    private Email $mockEmail;

    private Comments $Comments;

    protected function setUp(): void
    {
        $this->Entity = new Experiments(new Users(1, 1), 1);

        // create mock object for Email because we don't want to actually send emails
        $this->mockEmail = $this->getMockBuilder(\Elabftw\Services\Email::class)
             ->disableOriginalConstructor()
             ->setMethods(array('send'))
             ->getMock();

        $this->mockEmail->expects($this->any())
             ->method('send')
             ->will($this->returnValue(true));

        $this->Comments = new Comments($this->Entity);
    }

    public function testCreate(): void
    {
        $logger = new Logger('elabftw');
        $logger->pushHandler(new ErrorLogHandler());
        $Email = new Email(Config::getConfig(), $logger);
        $this->assertIsInt($this->Comments->create(new CreateComment('Ohai', '', $Email)));
    }

    public function testRead(): void
    {
        $this->assertIsArray($this->Comments->read(new ContentParams()));
    }

    public function testUpdate(): void
    {
        $this->Comments->setId(1);
        $this->Comments->Update(new ContentParams('Updated'));
        // too short comment
        $this->expectException(ImproperActionException::class);
        $this->Comments->Update(new ContentParams(''));
    }

    public function testDestroy(): void
    {
        $this->Comments->setId(1);
        $this->Comments->destroy();
    }

    public function testSetWrongId(): void
    {
        $this->expectException(IllegalActionException::class);
        $this->Comments->setId(0);
    }
}
