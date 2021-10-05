<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\find_directory_targets;

use strangetest;
use strangetest\BasicLogger;
use strangetest\BufferingLogger;
use strangetest\Context;
use strangetest\Logger;
use strangetest\State;
use strangetest\Target;


// helper functions

function set_cwd(Context $context, $dir) {
    $cwd = \getcwd();
    $context->teardown(function() use ($cwd) { \chdir($cwd); });
    \chdir($dir);
}


function target_to_array(Target $target) {
    $result = (array)$target;
    foreach ($result['subtargets'] as $key => $value) {
        $result['subtargets'][$key] = target_to_array($value);
    }
    return $result;
}


// helper assertions

function assert_targets(
    Context $context, array $expected, array $actual,
    Logger $logger, array $events = array()
) {
    foreach ($expected as $key => $value) {
        $expected[$key] = namespace\target_to_array($value);
    }
    $context->assert_identical($expected, $actual, 'Targets were incorrect');
    $context->assert_identical($events, $logger->get_log()->get_events(), 'Unexpected events while finding targets');
}


// tests

function test_directory_targets(Context $context) {
    $ds = \DIRECTORY_SEPARATOR;

    $state = new State();
    $logger = new BasicLogger(true);
    $path = __DIR__ . "{$ds}targets{$ds}";
    $test = strangetest\discover_directory($state, new BufferingLogger($logger), $path);
    strangetest\assert_falsy($logger->get_log()->get_events(), 'Errors during directory discovery');

    namespace\set_cwd($context, $path);
    $args = array(
        'test1.php',
        "test_dir{$ds}test_subdir{$ds}test1.php",
        'foo.txt',
        "test_dir{$ds}test1.php",
        'test2.php',
        'test_dir1',
        'setup.php',
        "test_dir{$ds}test2.php",
        "test_dir{$ds}test_subdir{$ds}test2.php",
    );

    list($root, $targets) = strangetest\process_user_targets($args, $errors);
    strangetest\assert_falsy($errors, 'Errors during target processing');

    list($error, $actual) = strangetest\find_directory_targets($logger, $test, $targets);
    $expected = array(
        array(
            'name' => "{$root}test1.php",
            'subtargets' => array()
        ),
        array(
            'name' => "{$root}test_dir{$ds}",
            'subtargets' => array(
                array(
                    'name' => "{$root}test_dir{$ds}test_subdir{$ds}test1.php",
                    'subtargets' => array(),
                ),
                array(
                    'name' => "{$root}test_dir{$ds}test1.php",
                    'subtargets' => array(),
                ),
            )
        ),
        array(
            'name' => "{$root}test2.php",
            'subtargets' => array()
        ),
        array(
            'name' => "{$root}test_dir1{$ds}",
            'subtargets' => array()
        ),
        array(
            'name' => "{$root}test_dir{$ds}",
            'subtargets' => array(
                array(
                    'name' => "{$root}test_dir{$ds}test2.php",
                    'subtargets' => array(),
                ),
                array(
                    'name' => "{$root}test_dir{$ds}test_subdir{$ds}test2.php",
                    'subtargets' => array(),
                ),
            )
        ),
    );
    $events = array(
        array(
            strangetest\EVENT_ERROR,
            "{$root}foo.txt",
            'This path is not a valid test file',
        ),
        array(
            strangetest\EVENT_ERROR,
            "{$root}setup.php",
            'This path is not a valid test file',
        ),
    );
    namespace\assert_targets($context, $actual, $expected, $logger, $events);
}
