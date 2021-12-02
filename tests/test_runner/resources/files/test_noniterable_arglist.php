<?php

namespace noniterable_arglist;


function setup_file() {
    return 1;
}

function teardown_file($arg) {
    if ($arg !== 1) {
        echo '$arg = ', \print_r($arg, true);
    }
    echo '.';
}


function test_one() {}
