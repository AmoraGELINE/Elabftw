<?php
namespace Elabftw\Elabftw;

use PDO;

class TodolistTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
        $Users = new Users(1);
        $this->Todolist = new Todolist($Users);
    }

    public function testCreate()
    {
        $body = 'write more tests';
        $this->assertTrue((bool) Tools::checkId($this->Todolist->create($body)));
    }

    public function testReadAll()
    {
        $this->assertTrue(is_array($this->Todolist->readAll()));
    }

    public function testUpdate()
    {
        $this->Todolist->update(1, "write more unit tests");
    }

    public function testUpdateOrdering()
    {
        $body = 'write more tests';
        $this->Todolist->create($body);
        $this->Todolist->create($body);
        $post = array(
            'ordering' => array('todoItem_3', 'todoItem_2', 'todoItem_4'),
            'table' => 'todolist'
        );
        $this->Todolist->updateOrdering($post);
    }

    public function testDestroy()
    {
        $this->Todolist->destroy(1);
    }

    public function testDestroyAll()
    {
        $this->Todolist->destroyAll();
    }
}
