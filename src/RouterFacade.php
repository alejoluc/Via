<?php

namespace alejoluc\Via;

class RouterFacade {
    
    protected static $router = null;

    public static function getInstance() {
        return static::$router;
    }

    public static function setInstance($router) {
        static::$router = $router;
    }

    public static function __callStatic($name, $arguments) {
        if (static::getInstance() === null) {
            static::setInstance(new Router());
        }
        return call_user_func_array([static::getInstance(), $name], $arguments);
    }

}