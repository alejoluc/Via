<?php

namespace Via;

class DefaultMatchHandler {

    public function handle(Match $match) {
        if (!$match->isMatch()) {
            return '<h1>404</h1>';
        }
        // TODO: Implement handling for non-passing filters

        $result = $match->getResult();

        if (is_callable($result)) {
            return call_user_func_array($result, $match->getParameters());
        } else {
            return $result;
        }
    }

}