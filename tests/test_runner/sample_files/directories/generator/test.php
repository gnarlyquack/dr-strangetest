<?php

namespace generator;


function setup_runs_for_file($one, $two) {
    yield [$one, $two, 5, 6];
    yield [$one, $two, 7, 8];
}

function teardown_runs_for_file() {
    echo __FUNCTION__;
}


function test_one($one, $two, $three, $four) {}

function test_two($one, $two, $three, $four) {}
