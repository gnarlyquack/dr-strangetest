<?php

namespace param_target\dir;

use easytest;


function setup_directory($one) {
    return easytest\arglists(array(array($one), array(3)));
}


function teardown_directory($arglists) {}
