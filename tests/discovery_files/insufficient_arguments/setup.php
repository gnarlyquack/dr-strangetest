<?php

function setup_directory_insufficient_arguments() {
    return new easytest\ArgList('one');
}

function teardown_directory_insufficient_arguments() {}
