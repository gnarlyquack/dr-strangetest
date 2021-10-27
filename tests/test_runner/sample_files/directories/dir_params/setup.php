<?php

namespace dir_params;

use strangetest;


function setup_directory() {
    echo __FUNCTION__;
}

function teardown_directory() {
    echo __FUNCTION__;
}


function setup_run_0() {
    echo __DIR__;
    return array(2, 4);
}

function teardown_run_0($one, $two) {
    echo "$one $two";
}


function setup_run_1() {
    echo __DIR__;
    return array(8, 16);
}

function teardown_run_1($one, $two) {
    echo "$one $two";
}
