<?php

function setup_directory_teardown_error() {}


function teardown_directory_teardown_error() {
    throw new Exception('An error happened');
}
