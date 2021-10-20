<?php

namespace param_target\dir;


function setup_run_0($one) {
    echo __DIR__;
    return array($one);
}

function teardown_run0($one) {
    echo $one;
}


function setup_run_1($one) {
    echo __DIR__;
    return array(3);
}

function teardown_run1($one) {
    echo $one;
}
