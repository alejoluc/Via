<?php

namespace alejoluc\Via;

class SampleMatchHandler {

    public function handle(Match $match) {
        if (!$match->isMatch()) {
            echo '<h1>404 NOT FOUND</h1>';
        } elseif (!$match->filtersPass()) {
            echo 'Filter error: ' . $match->getFilterError() . '<br />';
        } else {
            $result = $match->getResult();

            if (is_string($result)) {
                echo $result;
            } elseif (is_callable($result)) {
                echo call_user_func_array($result, $match->getParameters());
            } else {
                throw new Exception('Unhandled match type: ' . gettype($result));
            }
        }
    }

}