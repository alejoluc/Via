<?php

namespace alejoluc\Via;

class Router
{
    const METHOD_ALL = 'VIA_ALL';

    // All letters, all numbers, underscore, hyphen, dot, comma, semicolon and colon. No spaces.
    const ALLOW_DEFAULT      = '[A-z0-9-_.,;:]+';
    const ALLOW_NUMERIC      = '\d+';
    const ALLOW_ONLYLETTERS  = '[A-z]+';
    const ALLOW_ALPHANUMERIC = '\w+'; // Includes the underscore character, but not the hyphen or the dot

    private $routes         = [];
    private $routes_static  = [];
    private $idCounter      = 0;

    private $requestString;
    private $requestMethod;

    /** @var callable $routeMatchHandler */
    private $routeMatchHandler;

    private $filters = [];

    private $options = [
        'case_insensitive' => true
    ];


    private $prefixes = [];
    private $prefixesFilters = [];

    public function add($pattern, $destination, $method = self::METHOD_ALL)
    {
        $route = new Route;
        $route->route_id = $this->idCounter++;
        $route->method = $method;
        $route->destination = $destination;

        // Check if the route has a prefix
        if (count($this->prefixes) > 0) {
            $pattern_prefix = implode('/', $this->prefixes);
            if (strpos($pattern, '/') !== 0) {
                // If the pattern doesn't start with /, add a slash between prefix and pattern
                $pattern_prefix .= '/';
            }
            $pattern = $pattern_prefix . $pattern;
        }

        $route->pattern = $this->prepareRouteString($pattern);

        // Assign group filters, if there are any
        if (count($this->prefixesFilters) > 0) {
            foreach ($this->prefixesFilters as $prefixFilters) {
                foreach ($prefixFilters as $filter) {
                    if (!in_array($filter, $route->filters, true)) {
                        $route->filter($filter);
                    }
                }
            }
        }

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

    public function group($prefix, $callback, $filters = array()) {
        if (strpos($prefix, '/') === 0) { $prefix = substr($prefix, 1); }
        if (substr($prefix, -1) === '/') { $prefix = substr($prefix, 0, -1); }


        $this->prefixes[] =  $prefix;
        $this->prefixesFilters[] = $filters;
        $callback($this);
        array_pop($this->prefixes);
        array_pop($this->prefixesFilters);
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

    public function setRouteMatchHandler(callable $handler) {
        $this->routeMatchHandler = $handler;
    }

    public function getRouteMatchHandler() {
        return $this->routeMatchHandler;
    }

    /**
     * @return mixed|Match If no handler is given, returns a Match object. If a handler is given, it returns the result
     * of the call, after passing it the Match
     * @throws NoRequestStringSpecifiedException
     */
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

        $routeMatch = new Match();

        /** @var Route $route */
        foreach  ($allRoutes as $route) {
            $route->pattern = $route->generateCaptureGroups($route->pattern);
            $pattern = "@^" . $route->pattern . "$@{$flagsString}";
            if (preg_match($pattern, $this->requestString, $parameterMatches)) {
                if ($route->method === $this::METHOD_ALL || $route->method === $this->requestMethod) {
                    array_shift($parameterMatches); // Drop the first item, it contains the whole match
                    $this->keepOnlyNumericKeys($parameterMatches);
                    $routeMatch->setResult($route->destination);
                    $routeMatch->setMatchFound(true);
                    $routeMatch->setParameters($parameterMatches);
                    $routeMatch->setFilters($route->filters);
                    break;
                }
            }
        }

        if (!$routeMatch->isMatch()) { // No match, we can return early
            if (is_callable($this->getRouteMatchHandler())) {
                return call_user_func($this->getRouteMatchHandler(), $routeMatch);
            } else {
                return $routeMatch;
            }
        }

        // There is a match, lets see if the filters pass
        foreach ($routeMatch->getFilters() as $filter) {
            $filterResult = false;
            if (is_callable($filter)) {
                $filterResult = call_user_func_array($filter, $routeMatch->getParameters());
            } elseif (is_string($filter)) {
                // Its a string, this means the filter is supposed to be registered in the Router
                $filterCallback = $this->filters[$filter];
                $filterResult = call_user_func_array($filterCallback, $routeMatch->getParameters());
            }
            if ($filterResult !== true) {
                $routeMatch->setFilterError($filterResult);
                break;
            }
        }

        if (is_callable($this->getRouteMatchHandler())) {
            return call_user_func($this->getRouteMatchHandler(), $routeMatch);
        } else {
            return $routeMatch;
        }
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