<?php

namespace Specialtactics\L5Api\Models;

use Exception;
use Ramsey\Uuid\Uuid;
use Webpatser\Uuid\Uuid as UuidValidator;
use Illuminate\Database\Eloquent\Model;
use App\Transformers\BaseTransformer;
use Specialtactics\L5Api\Transformers\RestfulTransformer;

class RestfulModel extends Model
{

    use Features\UuidMethods;

    /**
     * Every model should generally have an incrementing primary integer key.
     * An exception may be pivot tables
     *
     * @var int Auto increments integer key
     */
    public $primaryKey = 'user_id';

    /**
     * Every model should have a UUID key, which will be returned to API consumers.
     * The only exception to this may be entities with very vast amounts of records, which never require referencing
     * for the purposes of updating or deleting by API consumers. In that case, make this null.
     *
     * @var string UUID key
     */
    public $uuidKey = 'user_uuid';

    /**
     * These attributes (in addition to primary & uuid keys) are not allowed to be updated explicitly through
     *  API routes of update and put. They can still be updated internally by Laravel, and your own code.
     *
     * @var array Attributes to disallow updating through an API update or put
     */
    public $immutableAttributes = ['created_at', 'deleted_at'];

    /**
     * Acts like $with (eager loads relations), however only for immediate controller requests for that object
     * This is useful if you want to use "with" for immediate resource routes, however don't want these relations
     *  always loaded in various service functions, for performance reasons
     *
     * @var array Relations to load implicitly by Restful controllers
     */
    public static $localWith = [];

    /**
     * You can define a custom transformer for a model, if you wish to override the functionality of the Base transformer
     *
     * @var null|RestfulTransformer The transformer to use for this model, if overriding the default
     */
    public static $transformer = null;

    /**
     * Return the validation rules for this model
     *
     * @return array Validation rules to be used for the model when creating it
     */
    public function validationRules()
    {
        return [];
    }

    /**
     * Return the validation rules for this model's update operations
     * In most cases, they will be the same as for the create operations
     *
     * @return array Validation roles to use for updating model
     */
    public function validationRulesUpdating()
    {
        return $this->validationRules();
    }

    /**
     * Return any custom validation rule messages to be used
     *
     * @return array
     */
    public function validationMessages()
    {
        return [];
    }

    /**
     * Boot the model
     */
    public static function boot()
    {
        parent::boot();

        // If the PK(s) are missing, generate them
        static::creating(function(RestfulModel $model) {
            $uuidKeyName = $model->getUuidKeyName();

            if (!array_key_exists($uuidKeyName, $model->getAttributes())) {
                $model->$uuidKeyName = Uuid::uuid4()->toString();
            }
        });
    }

    /**
     * Return this model's transformer, or a generic one if a specific one is not defined for the model
     *
     * @return BaseTransformer
     */
    public static function getTransformer()
    {
        return is_null(static::$transformer) ? new BaseTransformer : new static::$transformer;
    }

    /**
     * When Laravel creates a new model, it will add any new attributes (such as UUID) at the end. When a create
     * operation such as a POST returns the new resource, the UUID will thus be at the end, which doesn't look nice.
     * For purely aesthetic reasons, we have this function to conduct a simple reorder operation to move the UUID
     * attribute to the head of the attributes array
     *
     * This will be used at the end of create-related controller functions
     *
     * @return void
     */
    public function orderAttributesUuidFirst()
    {
        if ($this->getUuidKeyName()) {
            $UuidValue = $this->getUuidKey();
            unset($this->attributes[$this->getUuidKeyName()]);
            $this->attributes = [$this->getUuidKeyName() => $UuidValue] + $this->attributes;
        }
    }

    /************************************************************
     * Extending Laravel Functions Below
     ***********************************************************/

    /**
     * We're extending the existing Laravel Builder
     *
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /************************************************************
     * Wrappers for eloquent functions
     *
     * These will check if the ID is a UUID, and redirect
     * the function as appropriate
     *
     * Note: PCRE compiles regexp to bytecode using PHP's JIT,
     * so it is very fast
     ***********************************************************/

    /**
     * Wrapper to allow both IDs and UUIDs to be used
     *
     * @param  array|int  $ids
     * @return int
     */
    public static function destroy($ids)
    {
        if (UuidValidator::validate($ids)) {
            return static::destroyByUuid($ids);
        } else {
            return parent::destroy($ids);
        }
    }
}
