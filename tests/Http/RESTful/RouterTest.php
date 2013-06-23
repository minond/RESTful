<?php

namespace Efficio\Tests\Http\RESTful;

use Efficio\Http\Verb;
use Efficio\Http\Request;
use Efficio\Http\RESTful\Router;
use Efficio\Tests\Mocks\RouterMock;
use PHPUnit_Framework_TestCase;

require_once 'tests/mocks/RouterMock.php';

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

    public function testRequesetGeterAndSetter()
    {
        $req = new Request;
        $this->router->setRequest($req);
        $this->assertEquals($req, $this->router->getRequest());
    }

    public function testPatternGeterAndSetter()
    {
        $pattern = 'testing';
        $this->router->setPattern($pattern);
        $this->assertEquals($pattern, $this->router->getPattern());
    }

    public function testMetaFlagIsOffByDefault()
    {
        $this->assertFalse($this->router->getMeta());
    }

    public function testMetaFlagGeterAndSetter()
    {
        $this->router->setMeta(true);
        $this->assertTrue($this->router->getMeta());
    }

    public function testModelsSetterUsingAnAssociativeArray()
    {
        $models = [
            'users' => 'someClass',
            'roles' => 'someOtherClass',
        ];

        $this->router->setModels($models);
        $this->assertEquals($models, $this->router->getModels());
    }

    public function testModelClassGetter()
    {
        $models = [
            'users' => 'someClass',
            'roles' => 'someOtherClass',
        ];

        $this->router->setModels($models);
        $this->assertEquals('someClass', $this->router->getModelClass('users'));
    }

    public function testModelsSetterUsingARegularArray()
    {
        $expected = [
            'users' => 'someClass\User',
            'roles' => 'someOtherClassr\Role',
        ];

        $models = [
            'someClass\User',
            'someOtherClassr\Role',
        ];

        $this->router->setModels($models);
        $this->assertEquals($expected, $this->router->getModels());
    }

    /**
     * @expectedException Exception
     */
    public function testModelsSetterDoesNotDuplicateModels()
    {
        $models = [
            'users' => 'someClass',
        ];

        $this->router->setModels($models);
        $this->router->setModels($models);
    }

    public function testModelNameCanBeParsedUsingEmptyUri()
    {
        $req = new Request;
        $this->router->setRequest($req);

        $req->setUri('/');
        $this->assertNull($this->router->getModelName(), '/');
    }

    public function testModelNameCanBeParsedUsingJustAModelName()
    {
        $req = new Request;
        $this->router->setRequest($req);

        $req->setUri('/users');
        $this->assertEquals('users', $this->router->getModelName(), '/users');
    }

    public function testModelNameCanBeParsedUsingJustAModelNameAndASlashAtTheEnd()
    {
        $req = new Request;
        $this->router->setRequest($req);

        $req->setUri('/users/');
        $this->assertEquals('users', $this->router->getModelName(), '/users/');
    }

    public function testModelNameCanBeParsedUsingJustAModelNameAndAModelId()
    {
        $req = new Request;
        $this->router->setRequest($req);

        $req->setUri('/users/123');
        $this->assertEquals('users', $this->router->getModelName(), '/users/123');
    }

    public function testModelNameCanBeParsedUsingJustAModelNameAndAModelIdWithASlashAtTheEnd()
    {
        $req = new Request;
        $this->router->setRequest($req);

        $req->setUri('/users/123/');
        $this->assertEquals('users', $this->router->getModelName(), '/users/123/');
    }

    public function testModelNameCanBeSet()
    {
        $req = new Request;
        $this->router->setRequest($req);

        $req->setUri('/invalid/123');
        $this->router->setModelName('users');
        $this->assertEquals('users', $this->router->getModelName());
    }

    public function testModelIdCanBeParsedUsingAnEmptyUri()
    {
        $req = new Request;
        $this->router->setRequest($req);

        $req->setUri('/');
        $this->assertNull($this->router->getModelId(), '/');
    }

    public function testModelIdCanBeParsedUsingJustAModelName()
    {
        $req = new Request;
        $this->router->setRequest($req);

        $req->setUri('/users');
        $this->assertNull($this->router->getModelId(), '/users');
    }

    public function testModelIdCanBeParsedUsingJustAModelNameAndASlashAtTheEnd()
    {
        $req = new Request;
        $this->router->setRequest($req);

        $req->setUri('/users/');
        $this->assertNull($this->router->getModelId(), '/users/');
    }

    public function testModelIdCanBeParsedUsingJustAModelNameAndAModelId()
    {
        $req = new Request;
        $this->router->setRequest($req);

        $req->setUri('/users/123');
        $this->assertEquals('123', $this->router->getModelId(), '/users/123');
    }

    public function testModelIdCanBeParsedUsingJustAModelNameAndAModelIdAndExtraContentAtTheEnd()
    {
        $req = new Request;
        $this->router->setRequest($req);

        $req->setUri('/users/123/more');
        $this->assertEquals('123', $this->router->getModelId(), '/users/123/more');
    }

    public function testModelIdsCanBeSet()
    {
        $req = new Request;
        $this->router->setRequest($req);

        $req->setUri('/users/invalid');
        $this->router->setModelId('123');
        $this->assertEquals('123', $this->router->getModelId());
    }

    public function testHandleChecker()
    {
        $req = new Request;
        $req->setUri('/users');
        $models = [ 'users' => 'someClass' ];
        $this->router->setModels($models);
        $this->router->setRequest($req);
        $this->assertTrue($this->router->canHandleRequest());
    }

    public function testHandleCheckerWithInvalidRequest()
    {
        $req = new Request;
        $req->setUri('/use');
        $models = [ 'users' => 'someClass' ];
        $this->router->setModels($models);
        $this->router->setRequest($req);
        $this->assertFalse($this->router->canHandleRequest());
    }

    public function testInputIsParsed()
    {
        $expected = [
            'first_name' => 'Marcos',
            'last_name' => 'Minond',
        ];

        $req = new Request;
        $req->setInput('{ "first_name": "Marcos", "last_name": "Minond" }');

        $this->router->setRequest($req);
        $this->assertEquals($expected, $this->router->getRequestData());
    }

    public function testEmptyInputsAreTreatedAsEmptyArrays()
    {
        $req = new Request;
        $req->setInput('');

        $this->router->setRequest($req);
        $this->assertEquals([], $this->router->getRequestData());
    }

    public function testPluralizeFunction()
    {
        $this->assertEquals('users', RouterMock::callPluralizeModel('user'));
        $this->assertEquals('users', RouterMock::callPluralizeModel('User'));
    }

    public function testGetForSearchMethodIsRoutedCorrectly()
    {
        $req = new Request;
        $rou = new RouterMock;
        $rou->setRequest($req);
        $req->setMethod(Verb::GET);
        $rou->handle();
        $this->assertTrue($rou->handle_list_models_or_model_called);
    }

    public function testPostForSearchMethodIsRoutedCorrectly()
    {
        $req = new Request;
        $rou = new RouterMock;
        $rou->setRequest($req);
        $req->setMethod(Verb::POST);
        $rou->handle();
        $this->assertTrue($rou->handle_create_model_called);
    }

    public function testDeleteForSearchMethodIsRoutedCorrectly()
    {
        $req = new Request;
        $rou = new RouterMock;
        $rou->setRequest($req);
        $req->setMethod(Verb::DEL);
        $rou->handle();
        $this->assertTrue($rou->handle_delete_model_called);
    }

    public function testMetaRequestIsRoutedCorrectly()
    {
        $req = new Request;
        $rou = new RouterMock;
        $rou->setRequest($req);
        $req->setMethod(Verb::GET);
        $rou->setMeta(true);
        $rou->handle();
        $this->assertTrue($rou->handle_meta_called);
    }

    public function testPutMethodWithAnIdTriggersUpdateHandler()
    {
        $req = new Request;
        $rou = new RouterMock;
        $req->setUri('/users/123');
        $rou->setRequest($req);
        $req->setMethod(Verb::PUT);
        $rou->handle();
        $this->assertTrue($rou->handle_update_model_called);
    }

    public function testPutMethodWithNoIdTriggersCreateHandler()
    {
        $req = new Request;
        $rou = new RouterMock;
        $req->setUri('/users/');
        $rou->setRequest($req);
        $req->setMethod(Verb::PUT);
        $rou->handle();
        $this->assertTrue($rou->handle_create_model_called);
    }
}
