<?php

echo __FILE__;

class test_output_buffering {
    public function __construct() {
        echo __FUNCTION__;
    }

    public function test() {}
}
