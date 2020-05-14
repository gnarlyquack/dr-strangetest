<?php

function setup_directory_arguments() {
    echo __DIR__;
    return array('one', 'two');
}

function teardown_directory_arguments($one, $two) {
    echo __DIR__;
    easytest\assert_identical(
        array('one', 'two'), array($one, $two),
        'Incorrect arguments passed to directory teardown!'
    );
}
