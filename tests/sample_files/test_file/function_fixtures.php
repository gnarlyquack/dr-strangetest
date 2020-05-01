<?php

namespace function_fixtures;

use easytest;

function setup_function() {
    return [2, 4];
}

function teardown_function($one, $two) {
    easytest\assert_identical(2, $one);
    easytest\assert_identical(4, $two);
}

function test_one($one, $two) {
    easytest\assert_identical(2 * $one, $two);
}

function test_two($one, $two) {
    easytest\assert_identical(6 * $one, 3 * $two);
}
