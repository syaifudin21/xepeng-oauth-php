<?php

namespace Xepeng\OAuth\Storage;

class SessionStorage implements StorageInterface
{
    private $prefix;

    public function __construct($prefix = 'xepeng_oauth_')
    {
        $this->prefix = $prefix;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function set($key, $value)
    {
        $_SESSION[$this->prefix . $key] = $value;
    }

    public function get($key)
    {
        return $_SESSION[$this->prefix . $key] ?? null;
    }

    public function remove($key)
    {
        unset($_SESSION[$this->prefix . $key]);
    }

    public function clear()
    {
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, $this->prefix) === 0) {
                unset($_SESSION[$key]);
            }
        }
    }

    public function has($key)
    {
        return isset($_SESSION[$this->prefix . $key]);
    }
}
