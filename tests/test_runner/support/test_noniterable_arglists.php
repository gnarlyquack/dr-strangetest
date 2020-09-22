<?php

namespace noniterable_arglists;

use easytest;


function setup_runs() {
    return 1;
}

function teardown_runs($arg) {
    if ($arg !== 1) {
        echo '$arg = ', \print_r($arg, true);
    }
    echo '.';
}


function test_one() {}
