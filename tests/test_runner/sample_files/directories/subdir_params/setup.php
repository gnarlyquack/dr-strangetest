<?php

namespace subdir_params;

use strangetest;


function setup_runs_for_directory() {
    echo __DIR__;
    return array(
        array(2, 4),
        array(8, 16)
    );
}

function teardown_runs_for_directory($args) {
    echo __DIR__;
    strangetest\assert_identical(array(array(2, 4), array(8, 16)), $args);
}
