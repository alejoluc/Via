<?php

/**
 * This is an example Handler that assumes it will be called only when one or several filters fail
 */

namespace alejoluc\Via\SampleHandlers;

use alejoluc\Via\FilterFailure;

class SampleFiltersErrorHandler {
    public function handle($filterErrors) {
        $out = '<h1>Some filters reported an error:</h1>';
        $out .= '<ul>';
        foreach ($filterErrors as $error) {
            if ($error instanceof FilterFailure) {
                $out .= '<li>' . $error->getErrorMessage() . '</li>';
            } else {
                $out .= '<li>' . $error . '</li>';
            }
        }
        $out .= '</ul>';
        return $out;
    }
}