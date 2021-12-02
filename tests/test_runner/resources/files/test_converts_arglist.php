<?php

namespace converts_arglist;

use strangetest;


function setup_file() {
    return new \NoRewindIterator(new \ArrayIterator(array(1, 2, 3)));
}

function teardown_file($one, $two, $three) {
    echo '.';
}


function test_one($one, $two, $three) {
    strangetest\assert_identical(array(1, 2, 3), array($one, $two, $three));
}

function test_two($one, $two, $three) {
    strangetest\assert_identical(array(1, 2, 3), array($one, $two, $three));
}
