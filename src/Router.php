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
        'pattern.caseInsensitive' => true,
        'filters.stopOnFirstFail' => true
    ];


    private $prefixes = [];
    private $prefixesFilters = [];

    /**
     * Add a route to the router
     * @param string $pattern
     * @param mixed $destination
     * @param string $method
     * @return Route
     */
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

    /**
     * Shorthand method to call add() with GET request method
     * @param string $pattern
     * @param mixed $destination
     * @return Route
     */
    public function get($pattern, $destination) {
        return $this->add($pattern, $destination, 'GET');
    }

    /**
     * Shorthand method to call add() with POST request method
     * @param string $pattern
     * @param mixed $destination
     * @return Route
     */
    public function post($pattern, $destination) {
        return $this->add($pattern, $destination, 'POST');
    }

    /**
     * Shorthand method to call add() with PUT request method
     * @param string $pattern
     * @param mixed $destination
     * @return Route
     */
    public function put($pattern, $destination) {
        return $this->add($pattern, $destination, 'PUT');
    }

    /**
     * Shorthand method to call add() with DELETE request method
     * @param string $pattern
     * @param mixed $destination
     * @return Route
     */
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

    /**
     * Register a filter in the router. The filter name can then be applied to routes by it's name
     * @param $name
     * @param callable $callback
     * @return $this
     */
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

    /**
     * Get all registered routes
     * @return array
     */
    public function getRoutes()
    {
        return array_merge($this->routes_static, $this->routes);
    }

    /**
     * Set the request string. If this is not called, the Router will try to fall back to common server variables
     * that usually contain the request string.
     * @param $requestString
     */
    public function setRequestString($requestString)
    {
        $this->requestString = $this->prepareRouteString($requestString);
    }

    /**
     * Set the request method. If this is not called, the Router will try to assign it the value of
     * $_SERVER['REQUEST_METHOD'], and fall back to GET if it's not set
     * @param $requestMethod
     */
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
     * @throws \InvalidArgumentException
     */
    public function dispatch() {

        // Automatically set the request string and method if they were not manually set before
        $this->resolveRequestString();
        $this->resolveRequestMethod();

        $this->sortDynamicRoutesByPatternLength();

        // Flags to be used in the regex
        $regexFlags = '';
        if ($this->options['pattern.caseInsensitive']) {
            $regexFlags .= 'i';
        }

        $routeMatch = new Match();
        $request    = new Request();

        $allRoutes = array_merge($this->routes_static, $this->routes);
        /** @var Route $route */
        foreach  ($allRoutes as $route) {
            $route->pattern = $route->generateCaptureGroups($route->pattern);
            $pattern = "@^" . $route->pattern . "$@{$regexFlags}";
            $parameterMatches = [];
            if (preg_match($pattern, $this->requestString, $parameterMatches)) {
                if ($route->method === $this::METHOD_ALL || $route->method === $this->requestMethod) {
                    array_shift($parameterMatches); // Drop the first item, it contains the whole match
                    $this->keepOnlyNamedKeys($parameterMatches);

                    $request->setParameters($parameterMatches);
                    $request->setRequestString($this->requestString);
                    $routeMatch->setRequest($request);

                    $routeMatch->setResult($route->destination);
                    $routeMatch->setMatchFound(true);
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
                $filterResult = call_user_func($filter, $request);
            } elseif (is_string($filter)) {
                // Its a string, this means the filter is supposed to be registered in the Router
                $filterCallback = $this->filters[$filter];
                $filterResult   = call_user_func($filterCallback, $request);
            }

            if ($filterResult === null) {
                throw new \InvalidArgumentException('Filter cannot return null, or not have a return statement');
            }

            if ($filterResult !== true) {
                if (is_string($filterResult)) {
                    // The filter returned only an error message
                    $filterFailure = new FilterFailure($filterResult);
                    $routeMatch->addFilterError($filterFailure);
                } elseif (is_array($filterResult)) {
                    // The filter may have returned more than just an error message
                    $filterErrorMessage = isset($filterResult['error_message']) ? $filterResult['error_message'] : null;
                    $filterErrorCode = isset($filterResult['error_code']) ? $filterResult['error_code'] : null;
                    $filterErrorPayload = isset($filterResult['payload']) ? $filterResult['payload'] : [];

                    $filterFailure = new FilterFailure($filterErrorMessage, $filterErrorCode, $filterErrorPayload);
                    $routeMatch->addFilterError($filterFailure);
                } elseif ($filterResult instanceof FilterFailure) {
                    $routeMatch->addFilterError($filterResult);
                }else {
                    $routeMatch->addFilterError($filterResult);
                }

                if ($this->options['filters.stopOnFirstFail'] === true) {
                    break;
                }
            }
        }

        // A match was found, all filters pass. Time to call the handler and pass along the match
        if (is_callable($this->getRouteMatchHandler())) {
            return call_user_func($this->getRouteMatchHandler(), $routeMatch);
        } else {
            return $routeMatch;
        }
    }

    /**
     * If no request string is manually specified, try to fall back to $_SERVER['REQUEST_URI'] or
     * $_SERVER['PATH_INFO']
     * @throws NoRequestStringSpecifiedException
     */
    private function resolveRequestString() {
        if ($this->requestString === null) {
            if (isset($_SERVER['REQUEST_URI'])) {
                $this->setRequestString($_SERVER['REQUEST_URI']);
            } elseif (isset($_SERVER['PATH_INFO'])) {
                $this->setRequestString($_SERVER['PATH_INFO']);
            } else {
                throw new NoRequestStringSpecifiedException();
            }
        }
    }

    /**
     * Defaults to GET
     */
    private function resolveRequestMethod() {
        if ($this->requestMethod === null) {
            $this->requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        }
    }

    private function keepOnlyNamedKeys(&$matches) {
        array_walk($matches, function($val, $index) use (&$matches){
            if (is_int($index)) {
                unset($matches[$index]);
            }
        });
    }

    private function sortDynamicRoutesByPatternLength()
    {
        usort($this->routes, function ($a, $b) {
            return strlen($a->pattern) < strlen($b->pattern);
        });
    }

    /**
     * Trim any leading or trailing white spaces, and ensure it starts and ends with a slash
     * @param string $string
     * @return string
     */
    private function prepareRouteString($string)
    {
        $string = trim($string);
        $string = $this->ensurePreAndPostSlashes($string);
        $string = strtolower($string);
        return $string;
    }

    /**
     * Ensure a given string starts and ends with a slash
     * @param string $string
     * @return string
     */
    private function ensurePreAndPostSlashes($string = '')
    {
        if ($string === '') {
            return '/';
        }

        // TODO: If routes can contain UTF-8 characters, and they shouldn't, I should use mb_*
        if ($string[0] !== '/') {
            $string = "/{$string}";
        }
        if ($string[(strlen($string) - 1)] !== '/') {
            $string = "{$string}/";
        }
        return $string;
    }
}