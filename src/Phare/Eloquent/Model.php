<?php

namespace Phare\Eloquent;

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Model as PhModel;
use Phalcon\Mvc\Model\ResultsetInterface;
use Phalcon\Mvc\ModelInterface;
use Phare\Collections\Collection;
use Phare\Collections\Str;

#[\AllowDynamicProperties]
class Model extends PhModel implements \ArrayAccess
{
    /**
     * @var string|null The connection name for the model.
     */
    protected ?string $connection = null;

    /**
     * @var string|null The table associated with the model.
     */
    protected ?string $table = null;

    /**
     * @var string The primary key column.
     */
    protected string $primaryKey = 'id';

    /**
     * @var array The attributes that are mass assignable.
     */
    protected array $fillable = [];

    /**
     * @var array The attributes that will be hidden for arrays.
     */
    protected array $hidden = [];

    /**
     * @var array The attributes that should be encrypted.
     */
    protected array $passwordAttributes = [];

    /**
     * @var array The attributes that should be cast.
     */
    protected array $casts = [];

    /**
     * @return array The attributes that should be appended to arrays.
     */
    protected array $appends = [];

    protected function initialize(): void
    {
        if ($this->table === null) {
            $this->table = Str::tableize(class_basename(get_class($this)));
        }

        $this->setupConnectionService();

        $this->setSource($this->table);

        $this->skipAttributesOnUpdate([$this->primaryKey]);

        $this->useDynamicUpdate(true);

        if (defined('static::CREATED_AT')
            || defined('static::UPDATED_AT')) {
            $this->initializeTimestampable();
        }
    }

    private function setupConnectionService(): void
    {
        /** @var \Phare\Database\MySql\DatabaseManager $dbManager */
        $dbManager = $this->getDI()->getShared('dbManager');

        if ($this->connection === null) {
            $fragments = explode('\\', get_class($this));
            $serviceName = strtolower($fragments[count($fragments) - 2]);

            if ($dbManager->hasConnectionService($serviceName)) {
                $this->connection = $serviceName;
            } elseif ($dbManager->hasConnectionService('db')) {
                $this->connection = 'db';
            }
        }

        $name = $dbManager->getConnectionService($this->connection);
        $this->setConnectionService($name);
    }

    public function create(?array $attributes = null): bool
    {
        if ($attributes !== null) {
            $this->fill($attributes);
        }

        return parent::create();
    }

    public function update(?array $attributes = null): bool
    {
        if ($attributes !== null) {
            $this->fill($attributes);
        }

        return parent::update();
    }

    public function save(?array $attributes = null): bool
    {
        if ($attributes !== null) {
            $this->fill($attributes);
        }

        return parent::save();
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return mixed
     *
     * @todo: Implement
     */
    protected function cast($attribute, $value)
    {
        if (!isset($this->casts[$attribute])) {
            return $value;
        }

        return match ($this->casts[$attribute]) {
            'int', 'integer' => (int)$value,
            'real', 'float', 'double' => (float)$value,
            'string' => (string)$value,
            'bool', 'boolean' => (bool)$value,
            'object' => unserialize($value, ['allowed_classes' => true]),
            'array', 'json' => (array)$value,
            'collection' => new Collection($value),
            'date', 'datetime', 'timestamp' => new \DateTime($value),
            default => $value,
        };
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool|float|int|string
     *
     * @todo: Implement
     */
    protected function decast($attribute, $value)
    {
        if (!isset($this->casts[$attribute])) {
            return $value;
        }

        return match ($this->casts[$attribute]) {
            'int', 'integer' => (int)$value,
            'real', 'float', 'double' => (float)$value,
            'string' => (string)$value,
            'bool', 'boolean' => (bool)$value, // tinyint(1)
            'object' => is_object($value) ? serialize($value) : $value, // serializable \Closure
            'array', 'json' => is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $value,
            'collection' => $value instanceof Collection ? serialize($value) : $value,
            'date', => $value instanceof \DateTime ? $value->format('Y-m-d') : $value,
            'datetime', 'timestamp' => $value instanceof \DateTime ? $value->format('Y-m-d H:i:s') : $value,
            default => $value,
        };
    }

    public function fill(array $data): static
    {
        return $this->assign($data, $this->fillable);
    }

    /**
     * @param array|null $fillable
     * @param array|null $dataColumnMap
     */
    public function assign(array $data, $fillable = null, $dataColumnMap = null): ModelInterface
    {
        foreach ($this->passwordAttributes as $attribute) {
            if (isset($data[$attribute])) {
                $data[$attribute] = password_hash($data[$attribute], PASSWORD_DEFAULT);
            }
        }

        return parent::assign($data, $fillable, $dataColumnMap);
    }

    public static function all(array $columns = ['*']): ResultsetInterface
    {
        return parent::find(['columns' => implode(',', $columns)]);
    }

    public static function find($parameters = null): ResultsetInterface
    {
        if (!defined('static::DELETED_AT')) {
            return parent::find($parameters);
        }

        if (is_array($parameters)) {
            if (!empty($parameters['conditions'])) {
                $parameters['conditions'] = "({$parameters['conditions']}) AND " . constant('static::DELETED_AT') . ' IS NULL';
            } else {
                $parameters['conditions'] = constant('static::DELETED_AT') . ' IS NULL';
            }
        } else {
            $parameters = [
                'conditions' => constant('static::DELETED_AT') . ' IS NULL',
            ];
        }

        return parent::find($parameters);
    }

    public static function findFirst($parameters = null)
    {
        if (!defined('static::DELETED_AT')) {
            return parent::findFirst($parameters);
        }

        if (is_array($parameters)) {
            if (!empty($parameters['conditions'])) {
                $parameters['conditions'] = "({$parameters['conditions']}) AND " . constant('static::DELETED_AT') . ' IS NULL';
            } else {
                $parameters['conditions'] = constant('static::DELETED_AT') . ' IS NULL';
            }
        } else {
            $parameters = [
                'conditions' => constant('static::DELETED_AT') . ' IS NULL',
            ];
        }

        return parent::findFirst($parameters);
    }

    public static function first($id, array $columns = ['*'])
    {
        return self::findFirst([$id, 'columns' => implode(',', $columns)]);
    }

    public static function firstOrFail($id, $columns = ['*'])
    {
        $result = self::findFirst([$id, 'columns' => implode(',', $columns)]);

        if ($result === null) {
            throw new PhModel\Exception('No query results for model [' . static::class . '] ' . $id);
        }

        return $result;
    }

    public function __get(string $property)
    {
        if (in_array($property, $this->fillable, true)) {
            return $this->cast($property, parent::__get($property));
        }

        if (in_array($property, $this->appends, true)) {
            $method = 'get' . Str::studly($property) . 'Attribute';
            if (!method_exists($this, $method)) {
                throw new \RuntimeException('The attribute "' . $property . '" does not have a getter method.');
            }

            return $this->$method();
        }

        return parent::__get($property);
    }

    public function __set(string $property, $value)
    {
        if (in_array($property, $this->fillable, true)) {
            $value = $this->decast($property, $value);
        }

        parent::__set($property, $value);
    }

    public function toArray($columns = null, $useGetter = true): array
    {
        $data = parent::toArray($columns, $useGetter);

        foreach ($this->passwordAttributes as $passwordAttribute) {
            unset($data[$passwordAttribute]);
        }

        foreach ($this->hidden as $hidden) {
            unset($data[$hidden]);
        }

        return $data;
    }

    public static function where(string $field, $operator = null, $value = null)
    {
        return self::query()->where($field, $operator, $value);
    }

    public static function query(?DiInterface $container = null): BuilderInterface
    {
        return (new Builder($container))->setModelName(static::class);
    }

    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->{$offset};
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->{$offset} = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->{$offset});
    }
}
