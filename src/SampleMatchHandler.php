<?php

namespace alejoluc\Via;

class SampleMatchHandler {

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
            $result = $match->getResult();

            if (is_string($result)) {
                return $result;
            } elseif (is_array($result)) {
                if (count($result) < 2) {
                    throw new \InvalidArgumentException('Route must return an array of at least two elements:
                    controller and method names');
                }

                $controller = $result[0];
                $method     = $result[1];
                if (!class_exists($controller)) {
                    throw new \Exception('Class ' . $controller . ' could not be found');
                }
                if (!method_exists($controller, $method)) {
                    throw new \Exception('Class ' . $controller . ' does not have a ' . $method . '() method');
                }

                $instance = new $controller();
                return call_user_func_array([$instance, $method], $match->getParameters());
            } elseif (is_callable($result)) {
                return call_user_func_array($result, $match->getParameters());
            } else {
                throw new \Exception('Unhandled match type: ' . gettype($result));
            }
        }
    }

}