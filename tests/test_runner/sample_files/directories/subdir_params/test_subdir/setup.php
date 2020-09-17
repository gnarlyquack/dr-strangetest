<?php

namespace subdir_params\subdir;

use easytest;


function setup_directory($one, $two) {
    echo __DIR__;
    return easytest\make_argument_sets(array(
        array($one, 2 * $one),
        array($two, $two / 2)
    ));
}

function teardown_directory($arglists) {
    $args = array();
    foreach ($arglists as $list) {
        $args = \array_merge($args, $list);
    }
    echo \implode(' ', $args);
}
