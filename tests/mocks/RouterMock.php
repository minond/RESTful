<?php

namespace Efficio\Tests\Mocks;

use Efficio\Http\RESTful\Router;

class RouterMock extends Router
{
    public $handle_list_models_or_model_called = false;
    public $handle_update_model_called = false;
    public $handle_create_model_called = false;
    public $handle_delete_model_called = false;

    protected function handleListModelsOrModel($model, $id)
    {
        $this->handle_list_models_or_model_called = true;
    }

    protected function handleUpdateModel($model, $id)
    {
        $this->handle_update_model_called = true;
    }

    protected function handleCreateModel($model)
    {
        $this->handle_create_model_called = true;
    }

    protected function handleDeleteModel($model, $id)
    {
        $this->handle_delete_model_called = true;
    }

    public static function callPluralizeModel($name)
    {
        return self::pluralizeModel($name);
    }
}
