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
     * parse the model from the uri
     * @return string
     */
    public function getModelName()
    {
        preg_match($this->pattern, $this->request->getUri(), $matches);
        return isset($matches['model']) ? $matches['model'] : null;
    }

    /**
     * parse the model id from the uri
     * @return string
     */
    public function getModelId()
    {
        preg_match($this->pattern, $this->request->getUri(), $matches);
        return isset($matches['id']) ? $matches['id'] : null;
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
        return is_string($data) ? json_decode($data, true) : $data;
    }

    /**
     * handle the request
     * @return mixed
     */
    public function handle()
    {
        $method = '';
        $model = $this->getModelName();
        $id = $this->getModelId();

        switch ($this->request->getMethod()) {
            case Verb::GET:
                $method = 'handleListModelsOrModel';
                break;

            case Verb::PUT:
                $method = 'handleCreateOrUpdateModel';
                break;

            case Verb::POST:
                $method = 'handleCreateModel';
                break;

            case Verb::DEL:
                $method = 'handleDeleteModel';
                break;
        }

        return $this->{ $method }($this->getModelClass($model), $id);
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
