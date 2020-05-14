<?php

function setup_directory_insufficient_arguments() {
    echo __DIR__;
    return array('one');
}

function teardown_directory_insufficient_arguments() {
    echo __DIR__;
}
