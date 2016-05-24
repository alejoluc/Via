<?php

namespace Via;

class Via
{
    const METHOD_ALL = 'VIA_ALL';

    private $routes         = [];
    private $routes_static  = [];
    private $idCounter      = 0;

    private $requestString;
    private $requestMethod;

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

    public function dispatch()
    {
        if ($this->requestString === null) {
            throw new NoRequestStringSpecifiedException();
        }
        if ($this->requestMethod === null) {
            $this->requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        }

        // static?
        foreach ($this->routes_static as $static_route) {
            if ($this->requestString === $static_route->pattern && $this->requestMethod === $static_route->method) {
                return $static_route->destination;
            }
        }

        // no static match. lets try dynamic
        $this->sortRoutesByPatternLength();

        $flagsString = '';
        if ($this->options['case_insensitive']) {
            $flagsString .= 'i';
        }

        $matches = [];

        foreach ($this->routes as $route) {
            $route->pattern = $route->generateCaptureGroups($route->pattern);
            $pattern = "@^" . $route->pattern . "$@{$flagsString}";

            if (preg_match($pattern, $this->requestString, $matches)) {
                if ($route->method === $this::METHOD_ALL || $route->method === $this->requestMethod) {
                    array_shift($matches); // Drop the first item, it contains the whole match
                    $this->keepOnlyNamedKeys($matches);
                    return $route->destination;
                }
            }
        }

        throw new NoSuchRouteException("The route $this->requestString does not exist");
    }

    /*
     * When preg_match captures a named group, it puts in the resulting array the result of that capture both in
     * the named index, but also in a numeric index. I have chosen to remove them. The performance hit, however,
     * is probably not worth it.
     *
     * ATTENTION!! Note that in the dispatch function, when there is a match, I "shift" the first element of the
     * match. That holds the full string it was compared against. It has an index of 0. If I want to recover that
     * I have to do it before calling this function. But that is not necessary because the result would be the
     * same as the request made string.
     */
    private function keepOnlyNamedKeys(&$matches)
    {
        array_walk($matches, function ($val, $index) use (&$matches) {
            if (is_int($index)) {
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