<?php

namespace param_target\dir\subdir;

use easytest;


function setup_runs_for_directory($one) {
    echo __DIR__;
    return array(
        array($one),
        array(4)
    );
}


function teardown_runs_for_directory($arglists) {
    echo __DIR__;
}
