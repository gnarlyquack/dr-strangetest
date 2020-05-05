<?php

namespace param_target\dir\subdir;

use easytest;


function setup_directory($one) {
    return easytest\arglists(array(array($one), array(4)));
}


function teardown_directory($arglists) {}
