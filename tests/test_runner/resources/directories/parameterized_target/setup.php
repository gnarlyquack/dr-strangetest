<?php

namespace param_target;


function setup_run_0() {
    echo __DIR__;
    return array(1);
}

function teardown_run_0($one) {
    echo $one;
}


function setup_run1() {
    echo __DIR__;
    return array(2);
}

function teardown_run1($one) {
    echo $one;
}
