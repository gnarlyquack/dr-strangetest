<?php

function setup_directory_arguments() {
    return array('one', 'two');
}

function teardown_directory_arguments($one, $two) {
    easytest\assert_identical(
        array('one', 'two'), array($one, $two),
        'Incorrect arguments passed to directory teardown!'
    );
}
