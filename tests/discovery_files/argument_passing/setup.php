<?php

function setup_directory_arguments() {
    return new easytest\ArgList('one', 'two');
}

function teardown_directory_arguments($one, $two) {
    easytest\assert_identical(
        ['one', 'two'], [$one, $two],
        'Incorrect arguments passed to directory teardown!'
    );
}
