<?php

namespace dir_params;

use easytest;


function setup_directory() {
    return easytest\arglists(
        [2, 4],
        [8, 16]
    );
}

function teardown_directory($args) {
    echo __FUNCTION__;
    easytest\assert_identical([[2, 4], [8, 16]], $args);
}
