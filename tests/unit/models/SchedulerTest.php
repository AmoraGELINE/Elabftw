<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Models;

class SchedulerTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $Users = new Users(1);
        $Database = new Database($Users, 1);
        $this->Scheduler = new Scheduler($Database);
        $this->id = 1;
    }

    public function testCreate()
    {
        $this->id = $this->Scheduler->create('2016-07-22T19:42:00', '2016-07-23T19:42:00', 'Yep');
    }

    public function testUpdateStart()
    {
        $this->Scheduler->setId($this->id);
        $this->Scheduler->updateStart('2016-07-22T19:40:00', '2016-07-22T20:40:00');
    }

    public function testUpdateEnd()
    {
        $this->Scheduler->setId($this->id);
        $this->Scheduler->updateEnd('2016-07-22T20:45:00');
    }

    public function testDestroy()
    {
        $id = $this->Scheduler->create('2016-07-22T19:42:00', '2016-07-23T19:42:00', 'Yep');
        $this->Scheduler->setId($id);
        $this->Scheduler->destroy();
    }
}
