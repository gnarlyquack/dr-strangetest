<?php

function setup_directory_teardown_error() {
    echo __DIR__;
}


function teardown_directory_teardown_error() {
    echo __DIR__;
    \easytest\skip('Skip is an error here');
}
