<?php

namespace Via;

class Via
{
    const METHOD_ALL = 'VIA_ALL';

    const FILTER_NUMERIC      = '\d+';
    const FILTER_ONLYLETTERS  = '[A-z]+';
    const FILTER_ALPHANUMERIC = '\w+'; // Includes the underscore character!

    private $routes         = [];
    private $routes_static  = [];
    private $idCounter      = 0;

    private $requestString;
    private $requestMethod;

    private $filters = [];

    private $options = [
        'case_insensitive' => true
    ];

    public function add($pattern, $destination, $method = self::METHOD_ALL)
    {
        $route = new Route;
        $route->route_id = $this->idCounter++;
        $route->method = $method;
        $route->pattern = $this->prepareRouteString($pattern);
        $route->destination = $destination;

        if ($route->isStatic()) {
            $route->is_dynamic = false;
            $this->routes_static[] = $route;
        } else {
            $route->is_dynamic = true;
            $this->routes[] = $route;
        }
        return $route;
    }

    public function get($pattern, $destination) {
        return $this->add($pattern, $destination, 'GET');
    }

    public function post($pattern, $destination) {
        return $this->add($pattern, $destination, 'POST');
    }

    public function put($pattern, $destination) {
        return $this->add($pattern, $destination, 'PUT');
    }

    public function delete($pattern, $destination) {
        return $this->add($pattern, $destination, 'DELETE');
    }

    public function registerFilter($name, callable $callback) {
        $this->filters[$name] = $callback;
        return $this;
    }

    public function getStaticRoutes()
    {
        return $this->routes_static;
    }

    public function getDynamicRoutes()
    {
        return $this->routes;
    }


    public function getRoutes()
    {
        return array_merge($this->routes_static, $this->routes);
    }

    public function setRequestString($requestString)
    {
        $this->requestString = $this->prepareRouteString($requestString);
    }

    public function setRequestMethod($requestMethod)
    {
        $this->requestMethod = strtoupper($requestMethod);
    }

    public function setOptions($options)
    {
        $this->options = array_merge($this->options, $options);
    }

    private function createBareMatch() {
        $match = new Match();
        return $match;
    }

    public function dispatch() {
        if ($this->requestString === null) {
            throw new NoRequestStringSpecifiedException();
        }
        if ($this->requestMethod === null) {
            $this->requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        }

        $this->sortRoutesByPatternLength(); // This will sort the dynamic routes
        $allRoutes = array_merge($this->routes_static, $this->routes);

        $flagsString = '';
        if ($this->options['case_insensitive']) {
            $flagsString .= 'i';
        }
        $parameterMatches = [];

        $match = $this->createBareMatch();

        foreach  ($allRoutes as $route) {
            $route->pattern = $route->generateCaptureGroups($route->pattern);
            $pattern = "@^" . $route->pattern . "$@{$flagsString}";
            if (preg_match($pattern, $this->requestString, $parameterMatches)) {
                if ($route->method === $this::METHOD_ALL || $route->method === $this->requestMethod) {
                    array_shift($parameterMatches); // Drop the first item, it contains the whole match
                    $this->keepOnlyNumericKeys($parameterMatches);
                    $match->setResult($route->destination);
                    $match->setMatchFound(true);
                    $match->setParameters($parameterMatches);
                    $match->setFilters($route->filters);
                    break;
                }
            }
        }

        if (!$match->getMatchFound()) { // No match, we can return early
            return $match;
        }

        // There is a match, lets see if the filters pass
        foreach ($match->getFilters() as $filter) {
            $filterResult = false;
            if (is_callable($filter)) {
                $filterResult = call_user_func($filter);
            } elseif (is_string($filter)) {
                // Its a string (check that!) so its supposed to be registered in the Router
                $filterCallback = $this->filters[$filter];
                $filterResult = call_user_func($filterCallback);
            }
            if ($filterResult !== true) {
                $match->setFilterError($filterResult);
                break;
            }
        }

        return $match;
    }

    private function keepOnlyNumericKeys(&$matches) {
        array_walk($matches, function($val, $index) use (&$matches){
            if (!is_int($index)) {
                unset($matches[$index]);
            }
        });
    }

    private function sortRoutesByPatternLength()
    {
        // Sort routes by length
        // The longest ones should be compared to the request first
        // Otherwise a shorter pattern that starts the same way *may* handle it (TODO: you sure?)
        usort($this->routes, function ($a, $b) {
            return strlen($a->pattern) < strlen($b->pattern);
        });
    }

    private function prepareRouteString($string)
    {
        $string = trim($string);
        $string = $this->ensurePreAndPostSlashes($string);
        $string = strtolower($string);
        return $string;
    }

    private function ensurePreAndPostSlashes($string = '')
    {
        if ($string === '') {
            return '/';
        }

        // If routes can contain UTF-8 characters, and they shouldn't, I should use mb_*
        if ($string[0] !== '/') {
            $string = "/{$string}";
        }
        if ($string[(strlen($string) - 1)] !== '/') {
            $string = "{$string}/";
        }
        return $string;
    }
}