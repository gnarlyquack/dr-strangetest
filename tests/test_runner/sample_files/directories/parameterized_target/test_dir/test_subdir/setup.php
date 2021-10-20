<?php

namespace param_target\dir\subdir;


function setup_run_0($one) {
    echo __DIR__;
    return array($one);
}

function teardown_run_0($one) {
    echo $one;
}


function setup_run_1($one) {
    echo __DIR__;
    return array(4);
}

function teardown_run_1($one) {
    echo $one;
}
