<?php

namespace Romma;

class Romma {
    const METHOD_ALL = 'ROMMA_ALL';
    const REQUEST_STRING_DEFAULT = '/';

    private $routes = [];
    private $id_counter = 0;

    private $request_string = null;

    private $options = [
        'case_insensitive' => true,
        'match_empty_sections' => false
    ];

    public function add($method = METHOD_ALL, $pattern, $destination) {
        $route = new Route;
        $route->id = $this->id_counter++;
        $route->method = $method;
        $route->pattern = $this->prepareRouteString($pattern);
        $route->destination = $destination;

        $this->routes[] = $route;
        return $route;
    }

    public function getRoutes() {
        return $this->routes;
    }

    public function setRequestString($request_string) {
        $this->request_string = $this->prepareRouteString($request_string);
    }

    public function setOptions($options) {
        $this->options = array_merge($this->options, $options);
    }

    public function dispatch(){
        if ($this->request_string === null) $this->request_string = $this::REQUEST_STRING_DEFAULT;

        $this->sortRoutesByPatternLength();

        $flags_string = '';
        if ($this->options['case_insensitive']) $flags_string .= 'i';

        $matches = [];

        foreach ($this->routes as $route) {
            $route->pattern = $this->generateCaptureGroups($route->pattern);
            $pattern = "@^" . $route->pattern . "$@{$flags_string}";

            echo "\nPattern: $pattern\n";

            if (preg_match($pattern, $this->request_string, $matches)) {
                array_shift($matches); // Drop the first item, it contains the whole match
                var_dump($matches);
                var_dump(array_keys($matches));
                return $route->destination;
            }
        }

        throw new NoSuchRouteException("The route $this->request_string does not exist");
    }

    private function sortRoutesByPatternLength() {
        // Sort routes by length
        // The longest ones should be compared to the request first
        // Otherwise a shorter request that starts the same way may handle it
        usort($this->routes, function($a, $b){
            return strlen($a->pattern) < strlen($b->pattern);
        });
    }

    private function prepareRouteString($string) {
        $string = trim($string);
        $string = $this->ensurePreAndPostSlashes($string);
        return $string;
    }

    private function ensurePreAndPostSlashes($string = '') {
        if (strlen($string) === 0) return '/';

        // If routes can contain UTF-8 characters, and they shouldn't, I should use mb_*
        if ($string[0] !== '/')                     $string = "/{$string}";
        if ($string[(strlen($string)-1)] !== '/')   $string = "{$string}/";
        return $string;
    }

    private function generateCaptureGroups($pattern) {
        $quantityModifier = ($this->options['match_empty_sections']) ? '*' : '+';

        return preg_replace('/\{:([A-z]*)\}/', "(?P<$1>[A-z0-9-_.]${quantityModifier})", $pattern);
    }
}

class Route {
    public $id;
    public $method;
    public $pattern;
    public $destination;
}

class NoSuchRouteException extends \Exception {}