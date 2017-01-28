<?php

/**
 * This is an example Match Handler that assumes it will only be called when there are other handles set up
 * to handle unmatching routes and filter errors
 */

namespace alejoluc\Via\SampleHandlers;

use alejoluc\Via\Match;

class SampleSimpleMatchHandler {

    public function handle(Match $match) {
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