<?php

namespace Efficio\Http\RESTful;

use Exception;
use Efficio\Http\Verb;
use Efficio\Http\Request;

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
     * @var Request
     */
    private $request;

    /**
     * uri patter/regular expression to get model and id information
     * @param string
     */
    private $pattern = '/\/(?P<model>[A-Za-z]+)(\/?)(?P<id>[A-Za-z0-9]+)?/';

    /**
     * associative array holding model's human name and their class name
     * @var array
     */
    private $models = [];

    /**
     * name of the model we're working with
     * @var string
     */
    private $modelname = false;

    /**
     * id of the model we're working with
     * @var string
     */
    private $modelid = false;

    /**
     * this is a meta request
     * @var boolean
     */
    private $meta = false;

    /**
     * maps which an http verb to a handler function
     * @var array
     */
    protected static $verb_method_map = [
        Verb::GET => 'handleListModelsOrModel',
        Verb::PUT => 'handleCreateOrUpdateModel',
        Verb::POST => 'handleCreateModel',
        Verb::DEL => 'handleDeleteModel',
    ];

    /**
     * request setter
     * @param Request $req
     */
    public function setRequest(Request $req)
    {
        $this->request = $req;
    }

    /**
     * request getter
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * set uri pattern string
     * @param string $patt
     */
    public function setPattern($patt)
    {
        $this->pattern = $patt;
    }

    /**
     * return uri pattern string
     * @return string
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * meta flag setter
     * @param boolean $meta
     */
    public function setMeta($meta)
    {
        $this->meta = $meta;
    }

    /**
     * meta flag getter
     * @return boolean
     */
    public function getMeta()
    {
        return $this->meta;
    }

    /**
     * models setter. takes indexed and associative arrays
     * @param array $models
     */
    public function setModels(array $models)
    {
        foreach ($models as $name => $klass) {
            // index or associative?
            if (is_numeric($name)) {
                $parts = explode('\\', $klass);
                $name = array_pop($parts);
                $name = self::pluralizeModel($name);
            }

            // cannot overwrite model
            if (isset($this->models[ $name ])) {
                throw new Exception(sprintf(
                    'Cannot overwrite model %s(%s)', $name, $klass));
            }

            $this->models[ $name ] = $klass;
        }
    }

    /**
     * models getter
     * @return array
     */
    public function getModels()
    {
        return $this->models;
    }

    /**
     * model name setter
     * @param string $name
     */
    public function setModelName($name)
    {
        $this->modelname = $name;
    }

    /**
     * parse the model from the uri
     * @return string
     */
    public function getModelName()
    {
        if ($this->modelname === false) {
            preg_match($this->pattern, $this->request->getUri(), $matches);
            $this->modelname = isset($matches['model']) ? $matches['model'] : null;
        }

        return $this->modelname;
    }

    /**
     * model id setter
     * @param string $id
     */
    public function setModelId($id)
    {
        $this->modelid = $id;
    }

    /**
     * parse the model id from the uri
     * @return string
     */
    public function getModelId()
    {
        if ($this->modelid === false) {
                preg_match($this->pattern, $this->request->getUri(), $matches);
                $this->modelid = isset($matches['id']) ? $matches['id'] : null;
        }

        return $this->modelid;
    }

    /**
     * model class getter
     * @param string $model
     * @return string
     */
    public function getModelClass($model)
    {
        return isset($this->models[ $model ]) ? $this->models[ $model ] : null;
    }

    /**
     * checks if requested model is known
     * @return boolean
     */
    public function canHandleRequest()
    {
        return isset($this->models[ $this->getModelName() ]);
    }

    /**
     * parses request input
     * @return array
     */
    public function getRequestData()
    {
        $data = $this->request->getInput();

        if (is_null($data) || !strlen($data)) {
            $data = [];
        }

        return is_string($data) ? json_decode($data, true) : $data;
    }

    /**
     * handle the request
     * @return mixed
     */
    public function handle()
    {
        $method = $this->getMeta() ? 'handleMeta' :
            static::$verb_method_map[ $this->request->getMethod() ];

        return $this->{ $method }($this->getModelClass($this->getModelName()),
            $this->getModelId());
    }

    /**
     * request handler
     *
     * meta data getter
     *
     * @param string $model
     * @return array
     */
    protected function handleMeta($model)
    {
        $entity = json_decode(json_encode($model::create([])), true);
        $meta = [
            'class' => $model,
            'fields' => [],
        ];

        foreach ($entity as $field => $value) {
            $meta['fields'][] = [
                'field' => $field,
                'type' => gettype($value),
            ];
        }

        return $meta;
    }

    /**
     * request handler
     *
     * /api/users: List the URIs and perhaps other details of the collection's
     * members
     * /api/users/4: Retrieve a representation of the addressed member of the
     * collection, expressed in an appropriate Internet media type.
     *
     * @see Verb::GET
     * @param string $name
     * @param string $id
     */
    protected function handleListModelsOrModel($model, $id)
    {
        return $id ? $model::findOneById($id) :
            $model::findBy($this->getRequestData());
    }

    /**
     * request handler
     *
     * /api/users: Replace the entire collection with another collection.
     * [NOT HANDLED]
     * /api/users/4: Replace the addressed member of the collection, or if it
     * doesn't exist, create it.
     *
     * @see Verb::PUT
     * @param string $name
     * @param string $id
     */
    protected function handleCreateOrUpdateModel($model, $id)
    {
        return $id ? $this->handleUpdateModel($model, $id) :
            $this->handleCreateModel($model);
    }

    /**
     * request handler
     *
     * /api/users: Replace the entire collection with another collection.
     * [NOT HANDLED]
     * /api/users/4: Replace the addressed member of the collection, or if it
     * doesn't exist, create it.
     *
     * @see Verb::PUT
     * @param string $name
     * @param string $id
     */
    protected function handleUpdateModel($model, $id)
    {
        $id = null;
        $entity = $this->handleListModelsOrModel($model, $id);

        if ($entity) {
            foreach ($this->getRequestData() as $prop => $val) {
                $entity->{ $prop } = $val;
            }

            $entity->save();
            $id = $entity->getId();
        }

        return $id;
    }

    /**
     * request handler
     *
     * /api/users: Create a new entry in the collection. The new entry's URI is
     * assigned automatically and is usually returned by the operation.
     * /api/users/4: Not generally used. Treat the addressed member as a
     * collection in its own right and create a new entry in it. [NOT HANDLED]
     *
     * @see Verb::POST
     * @param string $name
     */
    protected function handleCreateModel($model)
    {
        $id = null;
        $entity = $model::create($this->getRequestData());

        if ($entity) {
            $entity->save();
            $id = $entity->getId();
        }

        return $id;
    }

    /**
     * request handler
     *
     * /api/users: Delete the entire collection. [NOT HANDLED]
     * /api/users/4: Delete the addressed member of the collection.
     *
     * @see Verb::DELETE
     * @param string $name
     * @param string $id
     */
    protected function handleDeleteModel($model, $id)
    {
        $ok = false;
        $entity = $this->handleListModelsOrModel($model, $id);

        if ($entity) {
            $ok = $entity->delete();
        }

        return $ok;
    }

    /**
     * convert a model name into its plural form. ie: user > uses
     * @param string $name
     * @return string
     */
    protected static function pluralizeModel($name)
    {
        // yup
        return strtolower($name) . 's';
    }
}
