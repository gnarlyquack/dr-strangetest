<?php

namespace param_target\dir;

use easytest;


function setup_runs_for_directory($one) {
    echo __DIR__;
    return array(
        array($one),
        array(3)
    );
}


function teardown_runs_for_directory($arglists) {
    echo __DIR__;
}
