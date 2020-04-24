<?php

function setup_directory_bad_loader() {
    echo __FUNCTION__;
    return function($test) {};
}

function teardown_directory_bad_loader() {
    echo __FUNCTION__;
}
