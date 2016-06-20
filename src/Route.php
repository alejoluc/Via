<?php

namespace Via;

class Route
{
    public $route_id;
    public $method;
    public $pattern;
    public $destination;
    public $is_dynamic;

    public $filters = [];

    public function filter($paramName, $filter) {
        $this->filters[$paramName] = $filter;
        return $this;
    }

    public function generateCaptureGroups($pattern)
    {
        $generatedRegex = preg_replace_callback('/\{:([A-z0-9-_.]+)\}/', [$this, 'replaceCustomFilters'], $pattern);
        return $generatedRegex;
    }

    public function replaceCustomFilters($matches) {
        $paramName = $matches[1];
        if (isset($this->filters[$paramName])) {
            return '(' . $this->filters[$paramName] . ')';
        } else {
            return '(' . '[A-z0-9-_.]+)';
        }
    }

    public function isStatic()
    {
        // benchmarks against strpos() showed preg_match was consistently faster across php versions
        return preg_match('/\{/', $this->pattern) !== 1;
    }
}