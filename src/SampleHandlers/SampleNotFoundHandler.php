<?php

/**
 * This is an example Handler that assumes it will be called only when a match is not found
 */

namespace alejoluc\Via\SampleHandlers;

class SampleNotFoundHandler {
    public function handle($requestString) {
        $out  = '<h1 style="text-align:center">404 Not Found</h1>';
        $out .= '<p style="text-align:center">' . $requestString . '</p>';
        return $out;
    }
}