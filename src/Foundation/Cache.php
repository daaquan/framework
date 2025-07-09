<?php

namespace Phare\Foundation;

use Phalcon\Cache\Adapter\AdapterInterface;
use Phalcon\Cache\Cache as PhCache;

/**
 * @method bool clear()
 * @method bool delete(string $key)
 * @method bool deleteMultiple($keys)
 * @method mixed get(string $key, $defaultValue = null)
 * @method AdapterInterface getAdapter()
 * @method array getOptions()
 * @method bool has(string $key)
 * @method bool set(string $key, $value, $ttl = null)
 * @method bool setMultiple($values, $ttl = null)
 * @method bool setOptions(array $options)
 * @method string getExceptionClass()
 */
class Cache extends PhCache
{
    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function put($key, $value)
    {
        $this->set($key, $value);
    }

    public function add($key, $value, $ttl = null)
    {
        if ($this->has($key)) {
            return false;
        }
        $this->set($key, $value, $ttl);

        return true;
    }

    public function forget($key)
    {
        $this->delete($key);
    }

    public function remember($key, $value, $ttl = null)
    {
        if ($this->has($key)) {
            return $this->get($key);
        }
        $this->set($key, $value, $ttl);

        return $value;
    }
}
