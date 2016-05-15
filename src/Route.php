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
    }

    public function generateCaptureGroups($pattern)
    {
        $generatedRegex = preg_replace_callback('/\{:([A-z]*)\}/', [$this, 'replaceCustomFilters'], $pattern);
        return $generatedRegex;
    }

    public function replaceCustomFilters($matches) {
        $paramName = $matches[1];
        if (isset($this->filters[$paramName])) {
            return '(?P<' . $paramName . '>' . $this->filters[$paramName] . ')';
        } else {
            return '(?P<' . $paramName . '>[A-z0-9-_.]+)';
        }
    }
}