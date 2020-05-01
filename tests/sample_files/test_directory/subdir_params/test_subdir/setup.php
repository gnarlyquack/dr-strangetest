<?php

namespace subdir_params\subdir;

use easytest;


function setup_directory($one, $two) {
    return easytest\arglists(
        [$one, 2 * $one],
        [$two, $two / 2]
    );
}

function teardown_directory($arglists) {
    $args = [];
    foreach ($arglists as $list) {
        $args = \array_merge($args, $list);
    }
    echo \implode(' ', $args);
}
