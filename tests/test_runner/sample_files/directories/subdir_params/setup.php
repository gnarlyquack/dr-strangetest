<?php

namespace subdir_params;

use easytest;


function setup_directory() {
    echo __DIR__;
    return easytest\make_argument_sets(array(
        array(2, 4),
        array(8, 16)
    ));
}

function teardown_directory($args) {
    echo __DIR__;
    easytest\assert_identical(array(array(2, 4), array(8, 16)), $args);
}
