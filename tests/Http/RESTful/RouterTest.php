<?php

namespace Efficio\Tests\Http\RESTful;

use Efficio\Http\RESTful\Router;
use PHPUnit_Framework_TestCase;

class RouterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Router
     */
    public $router;

    public function setUp()
    {
        $this->router = new Router;
    }

    public function testAddingModelsUsingAssocArraysUsesKeysAsModelAlias()
    {
        $map = $this->router->serve([
            'user' => 'User',
            'role' => 'Role',
        ]);

        $this->assertEquals([
            'user' => 'User',
            'role' => 'Role',
        ], $map);
    }

    public function testAssingModelsUsingRegularArrayUsesClassNameAsModelAlias()
    {
        $map = $this->router->serve([
            'name\spa\ce\User',
            'name\spa\ce\Role',
        ]);

        $this->assertEquals([
            'user' => 'name\spa\ce\User',
            'role' => 'name\spa\ce\Role',
        ], $map);
    }

    /**
     * @expectedException Exception
     */
    public function testAssingModelWithUsedAliasTriggersException()
    {
        $this->router->serve([ 'user' => 'User' ]);
        $this->router->serve([ 'user' => 'Role' ]);
    }
}
