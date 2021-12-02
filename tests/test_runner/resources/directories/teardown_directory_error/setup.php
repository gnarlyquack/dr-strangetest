<?php

function setup_directory_teardown_error() {
    echo __DIR__;
}


function teardown_directory_teardown_error() {
    echo __DIR__;
    strangetest\skip('Skip is an error here');
}
