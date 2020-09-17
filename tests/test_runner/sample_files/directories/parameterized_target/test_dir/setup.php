<?php

namespace param_target\dir;

use easytest;


function setup_directory($one) {
    echo __DIR__;
    return easytest\make_argument_sets(array(array($one), array(3)));
}


function teardown_directory($arglists) {
    echo __DIR__;
}
