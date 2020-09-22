<?php

namespace param_target;

use easytest;


function setup_runs_for_directory() {
    echo __DIR__;
    return array(
        array(1),
        array(2)
    );
}


function teardown_runs_for_directory($arglists) {
    echo __DIR__;
}
