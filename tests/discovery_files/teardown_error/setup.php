<?php

function setup_directory_teardown_error() {
    echo __FUNCTION__;
}


function teardown_directory_teardown_error() {
    echo __FUNCTION__;
    throw new Exception('An error happened');
}
