<?php

namespace noniterable_arglists;

use easytest;


function setup_file() {
    return easytest\make_argument_sets(1);
}

function teardown_file($arg) {
    if ($arg !== 1) {
        echo '$arg = ', \print_r($arg, true);
    }
    echo '.';
}


function test_one() {}
