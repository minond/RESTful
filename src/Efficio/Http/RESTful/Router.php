<?php

namespace Efficio\Http\RESTful;

use Efficio\Http\Verb;

/**
 * @link http://en.wikipedia.org/wiki/Representational_state_transfer
 *
 * -------+---------------------------------+----------------------------------
 *        | /api/users                      | /api/users/4
 * -------+---------------------------------+----------------------------------
 *    GET | List the URIs and perhaps other | Retrieve a representation of
 *        | details of the collection's     | the addressed member of the
 *        | members.                        | collection, expressed in an
 *        |                                 | appropriate Internet media type.
 * -------+---------------------------------+----------------------------------
 *    PUT | Replace the entire collection   | Replace the addressed member of
 *        | with another collection.        | the collection, or if it doesn't
 *        |                                 | exist, create it.
 * -------+---------------------------------+----------------------------------
 *   POST | Create a new entry in the       | Not generally used. Treat the
 *        | collection. The new entry's URI | addressed member as a collection
 *        | is assigned automatically and   | in its own right and create a
 *        | is usually returned by the      | new entry in it.
 *        | operation.                      |
 * -------+---------------------------------+----------------------------------
 * DELETE | Delete the entire collection.   | Delete the addressed member of
 *        |                                 | the collection.
 * -------+---------------------------------+----------------------------------
 */
class Router
{
    /**
     * model functions
     */
    const FIND_ONE_BY_ID = 'findOneById';
    const FIND_BY = 'findBy';
    const CREATE_ONE = 'create';
    const SAVE_ONE = 'save';
    const GET_ID = 'getId';

    /**
     * base api url
     * ie. /api/users/4
     * @var string
     */
    private $baseurl;

    /**
     * requested url
     * ie. /api/users/4
     * @var string
     */
    private $url;

    /**
     * http method
     * @var string
     */
    private $method;

    /**
     * raw request data
     * @var array
     */
    private $data;

    /**
     * request parameters
     * @var array
     */
    private $args;

    /**
     * plural model name
     * @var string
     */
    private $model;

    /**
     * sigular model name
     * @var string
     */
    private $smodel;

    /**
     * model id
     * @var string
     */
    private $id;

    /**
     * restful models
     * @string[]
     */
    private $models = [];

    /**
     * internal function routing
     * @param array
     */
    private static $internals = [
        Verb::GET => [
            true  => 'handlerFindOneById',
            false => 'handlerFindBy',
        ],

        Verb::PUT => [
            true  => 'handlerUpdate',
            false => 'handlerCreate',
        ],

        Verb::POST => [
            false => 'handlerCreate',
        ],

        Verb::DEL => [
            true  => 'handlerDeleteOneById',
            false => 'handlerDeleteBy',
        ],
    ];

    /**
     * default model functions
     * @var array
     */
    private $modeldefaults = [
        self::FIND_ONE_BY_ID => 'findOneById',
        self::FIND_BY => 'findBy',
        self::CREATE_ONE => 'create',
        self::SAVE_ONE => 'save',
        self::SAVE_ONE => 'getId',
    ];

    /**
     * update default model functions
     * @param array $update
     */
    public function modelFunction(array $update)
    {
        $this->modeldefaults = array_merge($this->modeldefaults, $update);
    }

    /**
     * @param array $models
     * @return array
     */
    public function serve(array $models)
    {
        foreach ($models as $key => $klass) {
            // defined api name
            if (!is_numeric($key)) {
                $model = $key;
            } else {
                $nss = explode('\\', $klass);
                $model = array_pop($nss);
            }

            if (isset($this->models[ $model ])) {
                throw new \Exception(sprintf('Cannot redeclare api model %s',
                    $model));
            }

            $model = strtolower($model);
            $this->models[ $model ] = $klass;
        }

        return $this->models;
    }

    /**
     * @throws Exception
     * @return mixed
     */
    public function route()
    {
        $this->valid(true);
        $hasid = strlen($this->id) !== 0;
        $func = self::$internals[ $this->method ][ $hasid ];
        return call_user_func([ $this, $func ]);
    }

    /**
     * @param boolean $throws
     * @throws Exception
     * @return boolean
     */
    public function valid($throws)
    {
        $valid = true;
        $hasid = strlen($this->id) !== 0;

        if (!isset($this->models[ $this->smodel ])) {
            $valid = false;
            if ($throws)
                throw new \Exception(sprintf(
                    'Invalid model: %s', $this->smodel));
        } else if (!isset(self::$internals[ $this->method ][ $hasid ])) {
            $valid = false;
            if ($throws)
                throw new \Exception(sprintf(
                    'Invalid request: unknown internal route. Method: %s',
                    $this->method));
        }

        return $valid;
    }

    /**
     * @param string $url
     * @param string $baseurl
     * @throws Exception
     */
    public function url($url, $baseurl = '')
    {
        $parts = explode('/', preg_replace('/^\//', '',
            str_replace($baseurl, '', $url)));

        switch (count($parts)) {
            case 2:
                list($model, $id) = $parts;
                break;

            case 1:
                $model = $parts[0];
                $id = '';
                break;

            default:
                throw new \Exception(sprintf('Invalid url: %s', $url));
        }

        $this->id = $id;
        $this->model = $model;
        $this->smodel = $this->getModelSingularName($model);
        $this->url = $url;
        $this->baseurl = $baseurl;
    }

    /**
     * @param string $method
     * @throws Exception
     */
    public function method($method)
    {
        $methods = [ Verb::POST, Verb::GET, Verb::PUT, Verb::DEL ];

        if (!in_array($method, $methods)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid method: %s, supported methods: %s',
                $method, implode(', ', $methods)
            ));
        }

        $this->method = $method;
    }

    /**
     * @param array|string $data
     */
    public function data($data)
    {
        $this->data = is_string($data) ?
                json_decode($data, true) : $data;
    }

    /**
     * @param array $args
     */
    public function args(array $args)
    {
        $this->args = $args;
    }

    /**
     * find one model by its id handler
     * ie. /api/users/4 => User[id: 4]
     * @throws Exception
     * @return mixed
     */
    private function handlerFindBy()
    {
        return call_user_func(
            $this->getCallable($this->smodel, self::FIND_BY),
            $this->data ?: []);
    }

    /**
     * find one model by its id handler
     * ie. /api/users/4 => User[id: 4]
     * @throws Exception
     * @return mixed
     */
    private function handlerFindOneById()
    {
        return call_user_func(
            $this->getCallable($this->smodel, self::FIND_ONE_BY_ID), $this->id);
    }

    /**
     * create a new model. returns the model's id.
     * @throws Exception
     * @return string
     */
    private function handlerCreate()
    {
        $model = call_user_func(
            $this->getCallable($this->smodel, self::CREATE_ONE), $this->data);

        if (call_user_func($this->getCallable($model, self::SAVE_ONE))) {
            return call_user_func($this->getCallable($model, self::GET_ID));
        }
    }

    /**
     * update an existing model. returns the model
     * @throws Exception
     * @return string
     */
    private function handlerUpdate()
    {
        $model = call_user_func(
            $this->getCallable($this->smodel, self::FIND_ONE_BY_ID), $this->id);

        foreach ($this->data as $field => $value) {
            $model->{ $field } = $value;
        }

        if (call_user_func($this->getCallable($model, self::SAVE_ONE))) {
            return $model;
        }
    }

    /**
     * users => user
     * @param string $model
     */
    private function getModelSingularName($model)
    {
        return preg_replace('/s$/', '', $model);
    }

    /**
     * @return array|string
     */
    private function getCallable($model, $func)
    {
        if (is_string($model)) {
            $info = $this->models[ $model ];

            if (is_array($info)) {
                $funcs = array_merge($this->modeldefaults, $info[1]);
                $model = $info[0];
                $func = $funcs[ $func ];
            } else {
                $model = $info;
                $func = $this->modeldefaults[ $func ];
            }
        }

        return [ $model, $func ];
    }
}
