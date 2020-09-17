<?php

namespace generator;
use easytest;

function setup_file($one, $two) {
    echo __FUNCTION__;
    $generate = function() use ($one, $two) {
        yield [$one, $two, 5, 6];
        yield [$one, $two, 7, 8];
    };
    return easytest\make_argument_sets($generate());
}


function test_one($one, $two, $three, $four) {}

function test_two($one, $two, $three, $four) {}
