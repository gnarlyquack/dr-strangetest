<?php

namespace subdir_params\subdir;


function setup_run_0($one, $two) {
    echo __DIR__;
    return array($one, 2 * $one);
}

function teardown_run_0($one, $two) {
    echo "$one $two";
}


function setup_run_1($one, $two) {
    echo __DIR__;
    return array($two, $two / 2);
}

function teardown_run_1($one, $two) {
    echo "$one $two";
}
