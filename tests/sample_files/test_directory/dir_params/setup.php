<?php

namespace dir_params;

use easytest;


function setup_directory() {
    return easytest\arglists(
        array(2, 4),
        array(8, 16)
    );
}

function teardown_directory($args) {
    echo __FUNCTION__;
    easytest\assert_identical(array(array(2, 4), array(8, 16)), $args);
}
