<?php

namespace alejoluc\Via;

class Request {
    private $parameters = [];
    private $requestString = '';

    /**
     * @param array $parameters
     */
    public function setParameters(array $parameters) {
        $this->parameters = $parameters;
    }

    /**
     * @return array
     */
    public function getParameters() {
        return $this->parameters;
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getParameter($name, $default = null) {
        if (array_key_exists($name, $this->parameters)) {
            return $this->parameters[$name];
        }
        return $default;
    }

    /**
     * @param string $requestString
     */
    public function setRequestString($requestString) {
        $this->requestString = $requestString;
    }

    /**
     * @return string
     */
    public function getRequestString() {
        return $this->requestString;
    }

    public function all() {
        return array_merge($_POST, $this->getParameters(), $_GET);
    }

    public function __get($name) {
        $mix = $this->all();
        return $mix[$name] ?? null;
    }
}