<?php

/**
 * This is an example Match Handler that will do three jobs: check if a match was found -and if not, show
 * a simple 404 message-, check if all the filters pass -and if not, show their errors-, and finally, if all
 * is well, return a proper result according of the Match destination.
 */

namespace alejoluc\Via\SampleHandlers;

use alejoluc\Via\Match;

class SampleFullMatchHandler {

    public function handle(Match $match) {
        if (!$match->isMatch()) {
            return '<h1>404 NOT FOUND</h1>';
        } elseif (!$match->filtersPass()) {
            $errors = $match->getFilterErrors();
            $ret = '<h1>Some filters reported an error:</h1>';
            $ret .= '<ul>';
            foreach ($errors as $error) {
                if ($error instanceof FilterFailure) {
                    $ret .= '<li>' . $error->getErrorMessage() . '</li>';
                } else {
                    $ret .= '<li>' . $error . '</li>';
                }
            }
            $ret .= '</ul>';
            return $ret;
        } else {
            $destination = $match->getDestination();

            if (is_string($destination)) {
                return $destination;
            } elseif (is_array($destination)) {
                if (count($destination) < 2) {
                    throw new \InvalidArgumentException('Route must return an array of at least two elements:
                    controller and method names');
                }

                list ($controller, $method) = $destination;

                if (!class_exists($controller)) {
                    throw new \Exception('Class ' . $controller . ' could not be found');
                }
                if (!method_exists($controller, $method)) {
                    throw new \Exception('Class ' . $controller . ' does not have a ' . $method . '() method');
                }

                $instance = new $controller();
                return call_user_func([$instance, $method], $match->getRequest());
            } elseif (is_callable($destination)) {
                return call_user_func($destination, $match->getRequest());
            } else {
                throw new \Exception('Unhandled match type: ' . gettype($destination));
            }
        }
    }

}