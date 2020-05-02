<?php

namespace subdir_params\subdir;

use easytest;


function setup_directory($one, $two) {
    return easytest\arglists(array(
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
