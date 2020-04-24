<?php

echo __FILE__;

function setup_directory_custom_loader() {
    return function($test) {
        printf('%s loading %s', __FILE__, $test);
        return new $test();
    };
}
