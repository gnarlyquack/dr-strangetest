<?php

namespace converts_arglist;

use easytest;


function setup_file() {
    return new \NoRewindIterator(new \ArrayIterator(array(1, 2, 3)));
}

function teardown_file($args) {
    if (!($args instanceof \NoRewindIterator)) {
        echo '$args = ', \print_r($args, true);
    }
    echo '.';
}


function test_one($one, $two, $three) {
    easytest\assert_identical(array(1, 2, 3), array($one, $two, $three));
}

function test_two($one, $two, $three) {
    easytest\assert_identical(array(1, 2, 3), array($one, $two, $three));
}
