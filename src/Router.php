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

    private $namedRoutes    = [];

    private $requestString;
    private $requestMethod;

    /** @var callable $routeMatchHandler */
    private $routeMatchHandler;
    /** @var  callable $filtersErrorHandler */
    private $filtersErrorHandler = null;
    /** @var  callable $noMatchHandler */
    private $noMatchFoundHandler = null;

    private $filters = [];

    private $options = [
        'pattern.caseInsensitive' => true,
        'filters.stopOnFirstFail' => true
    ];


    private $prefixes = [];
    private $prefixesFilters = [];

    /**
     * Add a route to the router
     * @param string      $pattern
     * @param mixed       $destination
     * @param string      $method
     * @param string|null $name  Give a name for the route, usually to be used with the getPath() method afterwards
     * @return Route
     */
    public function add($pattern, $destination, $method = self::METHOD_ALL, $name = null)
    {
        $route = new Route;
        $route->route_id = $this->idCounter++;
        $route->method = $method;
        $route->destination = $destination;

        if ($name!== null && is_string($name)) {
            $this->namedRoutes[$name] = $route;
        }

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

        if ($route->hasParameters()) {
            $route->is_dynamic = true;
            $this->routes[] = $route;
        } else {
            $route->is_dynamic = false;
            $this->routes_static[] = $route;
        }
        return $route;
    }

    /**
     * Shorthand method to call add() with GET request method
     * @param string $pattern
     * @param mixed $destination
     * @return Route
     */
    public function get($pattern, $destination, $name = null) {
        return $this->add($pattern, $destination, 'GET', $name);
    }

    /**
     * Shorthand method to call add() with POST request method
     * @param string $pattern
     * @param mixed $destination
     * @return Route
     */
    public function post($pattern, $destination, $name = null) {
        return $this->add($pattern, $destination, 'POST', $name);
    }

    /**
     * Shorthand method to call add() with PUT request method
     * @param string $pattern
     * @param mixed $destination
     * @return Route
     */
    public function put($pattern, $destination, $name = null) {
        return $this->add($pattern, $destination, 'PUT', $name);
    }

    /**
     * Shorthand method to call add() with DELETE request method
     * @param string $pattern
     * @param mixed $destination
     * @return Route
     */
    public function delete($pattern, $destination, $name = null) {
        return $this->add($pattern, $destination, 'DELETE', $name);
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
     * Get the path (link) to a named route with the given parameters, if any
     * @param string $routeName
     * @param array $parameters
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getPath($routeName, $parameters = []) {
        if (!array_key_exists($routeName, $this->namedRoutes)) {
            throw new \InvalidArgumentException('No route named "' . $routeName . '" found');
        }

        $route      = $this->namedRoutes[$routeName];
        $route_path = $route->pattern;

        foreach ($parameters as $paramName => $paramValue) {
            $route_path = str_replace('{' . $paramName . '}', $paramValue, $route_path);
        }

        return $route_path;
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

    public function setMatchHandler(callable $handler) {
        $this->routeMatchHandler = $handler;
    }

    public function onFiltersError(callable $handler) {
        $this->filtersErrorHandler = $handler;
    }

    public function onNoMatchFound(callable $handler) {
        $this->noMatchFoundHandler = $handler;
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

                    $routeMatch->setDestination($route->destination);
                    $routeMatch->setMatchFound(true);
                    $routeMatch->setFilters($route->filters);
                    break;
                }
            }
        }

        if (!$routeMatch->isMatch()) { // No match, we can return early
            if (is_callable($this->noMatchFoundHandler)) {
                // If onNoMatchFound() was setup, call it, and pass it the request string
                return call_user_func($this->noMatchFoundHandler, $this->requestString);
            } elseif (is_callable($this->routeMatchHandler)) {
                // If not, fallback to the match handler, and pass it the Match object
                return call_user_func($this->routeMatchHandler, $routeMatch);
            } else {
                // If a match handler has not been set, return the Match object
                return $routeMatch;
            }
        }

        // There is a match, lets see if the filters pass
        $filterError = false;
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
                $filterError = true;
                if (is_string($filterResult)) {
                    // The filter returned only an error message
                    $filterFailure = new FilterFailure($filterResult);
                    $routeMatch->addFilterError($filterFailure);
                } elseif (is_array($filterResult)) {
                    // The filter may have returned more than just an error message
                    $filterErrorMessage = isset($filterResult['error_message']) ? $filterResult['error_message'] : null;
                    $filterErrorCode    = isset($filterResult['error_code']) ? $filterResult['error_code'] : null;
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

        if ($filterError === true) { // At least one filter failed, call the appropriate handler
            if (is_callable($this->filtersErrorHandler)) {
                // If onFiltersError() was set up, call it, and pass it only the errors
                return call_user_func($this->filtersErrorHandler, $routeMatch->getFilterErrors());
            } elseif (is_callable($this->routeMatchHandler)) {
                // Otherwise, fall back to the match handler, and pass it the Match object
                return call_user_func($this->routeMatchHandler, $routeMatch);
            } else {
                // If no match handler was set up, return the Match object
                return $routeMatch;
            }
        }

        // A match was found, all filters pass. Time to call the handler and pass along the match
        if (is_callable($this->routeMatchHandler)) {
            return call_user_func($this->routeMatchHandler, $routeMatch);
        } else {
            // If no match handler was set up, return the Match object
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
                $this->setRequestString($this->stripFilenameAndQueryString($_SERVER['REQUEST_URI']));
            } elseif (isset($_SERVER['PATH_INFO'])) {
                $this->setRequestString($this->stripFilenameAndQueryString($_SERVER['PATH_INFO']));
            } else {
                throw new NoRequestStringSpecifiedException();
            }
        }
    }

    /**
     * If the request string starts with the script file name, remove it from the beginning.
     * If the request string contains ?, &, or both, end the request string in their first occurrence
     * @param $requestString
     * @return string
     */
    private function stripFilenameAndQueryString($requestString) {
        $fileName = $_SERVER['SCRIPT_NAME'];
        // If SCRIPT_NAME and/or the Request String don't start with a slash, add it, for comparison
        // purposes
        if (strpos($fileName, '/') !== 0) { $fileName = "/$fileName"; }
        if (strpos($requestString, '/') !== 0) { $requestString = "/$requestString"; }
        // Check if the file name is the first thing in the request string, and remove it if true
        $posFileNameInRequest = strpos($requestString, $fileName) === 0;
        if ($posFileNameInRequest !== false) {
            $requestString = substr($requestString, strlen($fileName));
        }

        // End the Request String when ? or & fist appear
        $posQuestionMark = strpos($requestString, '?');
        $posAmpersand    = strpos($requestString, '&');
        if ($posQuestionMark !== false && $posAmpersand !== false) {
            $posFirst = min($posQuestionMark, $posAmpersand);
            $requestString = substr($requestString, 0, $posFirst);
        } elseif ($posQuestionMark !== false) {
            $pos = $posQuestionMark;
            $requestString = substr($requestString, 0, $pos);
        } elseif ($posAmpersand !== false) {
            $pos = $posAmpersand;
            $requestString = substr($requestString, 0, $pos);
        }
        return $requestString;
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