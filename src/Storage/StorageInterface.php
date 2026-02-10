<?php

namespace Xepeng\OAuth\Storage;

interface StorageInterface
{
    /**
     * Store a value by key.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set($key, $value);

    /**
     * Retrieve a value by key.
     *
     * @param string $key
     * @return mixed|null
     */
    public function get($key);

    /**
     * Remove a value by key.
     *
     * @param string $key
     * @return void
     */
    public function remove($key);

    /**
     * Clear all stored values related to this client.
     *
     * @return void
     */
    public function clear();

    /**
     * Check if a key exists.
     *
     * @param string $key
     * @return bool
     */
    public function has($key);
}
