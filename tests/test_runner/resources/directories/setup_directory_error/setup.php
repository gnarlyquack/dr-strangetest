<?php

function setup_directory_setup_error() {
    throw new Exception('An error happened');
}

function teardown_directory_setup_error() {
    echo __FUNCTION__;
}
