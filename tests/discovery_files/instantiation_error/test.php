<?php

$this->log[] = __FILE__;

class test_instantiation_error_one {
    public function __construct() {
        throw new Exception('An error happened');
    }
}

class test_instantiation_error_two {}
