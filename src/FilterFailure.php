<?php

namespace alejoluc\Via;

class FilterFailure {
    private $error_code;
    private $error_message;
    private $payload;

    public function __construct($error_message = null, $error_code = null, array $payload = []) {
        $this->error_message = $error_message;
        $this->error_code    = $error_code;
        $this->payload       = $payload;
    }

    public function setErrorCode($error_code) {
        $this->error_code = $error_code;
    }

    public function getErrorCode() {
        return $this->error_code;
    }

    public function setErrorMessage($message) {
        $this->error_message = $message;
    }

    public function getErrorMessage() {
        return $this->error_message;
    }

    public function setPayload(array $payload) {
        $this->payload = $payload;
    }

    public function getPayload() {
        return $this->payload;
    }
}