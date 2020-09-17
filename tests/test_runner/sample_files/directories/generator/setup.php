<?php

namespace generator;
use easytest;

function setup_directory() {
    echo __FUNCTION__;
    $generate = function() {
        yield [1, 2];
        yield [2, 4];
    };
    return easytest\make_argument_sets($generate());
}

function teardown_directory() {
    echo __FUNCTION__;
}
