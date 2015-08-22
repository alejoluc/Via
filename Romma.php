<?php

namespace Romma;

class NoSuchRouteException extends \Exception
{
}

class Route
{
    public $route_id;
    public $method;
    public $pattern;
    public $destination;
}

class Romma
{
    const METHOD_ALL = 'ROMMA_ALL';
    const REQUEST_STRING_DEFAULT = '/';

    private $routes = [];
    private $idCounter = 0;

    private $requestString = null;

    private $options = [
        'case_insensitive' => true,
        'match_empty_sections' => false
    ];

    public function add($pattern, $destination, $method = METHOD_ALL)
    {
        $route = new Route;
        $route->route_id = $this->idCounter++;
        $route->method = $method;
        $route->pattern = $this->prepareRouteString($pattern);
        $route->destination = $destination;

        $this->routes[] = $route;
        return $route;
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function setRequestString($requestString)
    {
        $this->requestString = $this->prepareRouteString($requestString);
    }

    public function setOptions($options)
    {
        $this->options = array_merge($this->options, $options);
    }

    public function dispatch()
    {
        if ($this->requestString === null) {
            $this->requestString = $this::REQUEST_STRING_DEFAULT;
        }

        $this->sortRoutesByPatternLength();

        $flagsString = '';
        if ($this->options['case_insensitive']) {
            $flagsString .= 'i';
        }

        $matches = [];

        foreach ($this->routes as $route) {
            // TODO: Check exact string matching first. Probably by returning whether the pattern has
            // capture groups or not, which is as easy as checking for "(" or by returning an object
            // instead of a string, an object that contains the pattern and whether it has capture groups
            $route->pattern = $this->generateCaptureGroups($route->pattern);
            $pattern = "@^" . $route->pattern . "$@{$flagsString}";

            echo "\nPattern: $pattern\n";

            if (preg_match($pattern, $this->requestString, $matches)) {
                array_shift($matches); // Drop the first item, it contains the whole match
                $this->keepOnlyNamedKeys($matches);
                var_dump($matches);
                return $route->destination;
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
        // Otherwise a shorter pattern that starts the same way may handle it
        usort($this->routes, function ($a, $b) {
            return strlen($a->pattern) < strlen($b->pattern);
        });
    }

    private function prepareRouteString($string)
    {
        $string = trim($string);
        $string = $this->ensurePreAndPostSlashes($string);
        return $string;
    }

    private function ensurePreAndPostSlashes($string = '')
    {
        if (strlen($string) === 0) {
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

    private function generateCaptureGroups($pattern)
    {
        $quantityModifier = ($this->options['match_empty_sections']) ? '*' : '+';

        return preg_replace('/\{:([A-z]*)\}/', "(?P<$1>[A-z0-9-_.]${quantityModifier})", $pattern);
    }
}