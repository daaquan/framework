<?php

namespace Phare\Session;

use Phalcon\Session\Manager;
use Phare\Contracts\Session\Session;

/**
 * Class SessionManager
 * Custom session manager class that extends Phalcon's Manager class
 * and implements the Session interface.
 */
class SessionManager extends Manager implements Session
{
    /**
     * Retrieve an item from the session by key and then remove it.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        return $this->get($key, $default, true);
    }

    /**
     * Store an item in the session.
     *
     * @param string $key
     * @param mixed $value
     */
    public function put($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * Add an item to an array in the session.
     *
     * @param string $key
     * @param mixed $value
     */
    public function add($key, $value)
    {
        $array = $this->get($key, []);
        $array[] = $value;
        $this->set($key, $array);
    }

    /**
     * Remove all items from the session and restart it.
     */
    public function clear()
    {
        $this->destroy();
        $this->start();
    }

    /**
     * Replace the session attributes with the given array.
     */
    public function replace(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Remove an item from the session by key.
     *
     * @param string $key
     */
    public function forget($key)
    {
        $this->remove($key);
    }
}
