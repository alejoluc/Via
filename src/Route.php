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
        // Do not duplicate filters
        $exists = array_search($filter, $this->filters, true);
        if ($exists === false) {
            $this->filters[] = $filter;
        }
        return $this;
    }

    public function removeFilter($filter) {
        $pos = array_search($filter, $this->filters, true);
        if ($pos !== false && $pos > -1) {
            array_splice($this->filters, $pos, 1);
        }
        return $this;
    }

    public function generateCaptureGroups($pattern)
    {
        $generatedRegex = preg_replace_callback('/\{([A-z0-9-_.]+)\}/', [$this, 'replaceCustomConstraints'], $pattern);
        return $generatedRegex;
    }

    public function replaceCustomConstraints($matches) {
        $paramName = $matches[1];
        if (isset($this->constraints[$paramName])) {
            return "(?P<$paramName>" . $this->constraints[$paramName] . ')';
        } else {
            return "(?P<$paramName>" . Router::ALLOW_DEFAULT . ')';
        }
    }

    public function hasParameters()
    {
        // benchmarks against strpos() showed preg_match was consistently faster across php versions
        return preg_match('/\{/', $this->pattern) === 1;
    }
}