<?php

function setup_directory_arguments_subdir1($one, $two) {
    echo __DIR__;
    return array($two, 'three');
}

function teardown_directory_arguments_subdir1() {
    echo __DIR__;
}
