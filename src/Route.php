<?php

namespace alejoluc\Via;

class Route
{
    public $route_id;
    public $method;
    public $pattern;
    public $destination;
    public $is_dynamic;

    public $constraints = [];

    public $filters     = [];
    public $filters_err = [];

    public function where($paramName, $regex) {
        $this->constraints[$paramName] = $regex;
        return $this;
    }

    public function filter($filter) {
        $this->filters[] = $filter;
        return $this;
    }

    public function generateCaptureGroups($pattern)
    {
        $generatedRegex = preg_replace_callback('/\{:([A-z0-9-_.]+)\}/', [$this, 'replaceCustomConstraints'], $pattern);
        return $generatedRegex;
    }

    public function replaceCustomConstraints($matches) {
        $paramName = $matches[1];
        if (isset($this->constraints[$paramName])) {
            return '(' . $this->constraints[$paramName] . ')';
        } else {
            return '(' . Router::FILTER_ALPHANUMERIC . '+)';
        }
    }

    public function isStatic()
    {
        // benchmarks against strpos() showed preg_match was consistently faster across php versions
        return preg_match('/\{/', $this->pattern) !== 1;
    }
}