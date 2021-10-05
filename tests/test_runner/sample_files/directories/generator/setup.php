<?php

namespace generator;


function setup_runs_for_directory() {
    yield [1, 2];
    yield [2, 4];
}

function teardown_runs_for_directory() {
    echo __FUNCTION__;
}
