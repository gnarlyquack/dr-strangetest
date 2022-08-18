<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\discover\directory;
use strangetest;
use strangetest\State;
use strangetest\_DiscoveryState;


function setup()
{
    return array(
        new strangetest\BasicLogger(strangetest\LOG_ALL),
        __DIR__ . '/resources/directory/',
    );
}


// tests

function test_discover_directory(
    strangetest\BasicLogger $logger, $path,
    strangetest\Context $context)
{
    $path .= 'discover_directory/';
    $discovered = array(
        'directory' => $path,
        'setup' => 'setup',
        'teardown' => 'teardown',
        'tests' => array(
            array(
                'file' => 'test.php',
                'tests' => array(
                    array('function' => 'test_one', 'namespace' => 'discovery'),
                ),
            ),
            array(
                'directory' => 'TEST_DIR1/',
                'setup' => 'SetupDirectoryTEST1',
                'teardown' => 'TearDownDirectoryTEST1',
                'tests' => array(
                    array(
                        'file' => 'TEST1.PHP',
                        'tests' => array(
                            array('function' => 'TEST_TWO'),
                        ),
                    ),
                    array(
                        'file' => 'TEST2.PHP',
                        'tests' => array(
                            array('function' => 'TEST_THREE'),
                        ),
                    ),
                ),
            ),
            array(
                'directory' => 'test_dir2/',
                'setup' => 'setup_directory_test2',
                'teardown' => 'teardown_directory_test2',
                'tests' => array(
                    array(
                        'file' => 'test1.php',
                        'tests' => array(
                            array('function' => 'test_four'),
                        ),
                    ),
                    array(
                        'file' => 'test2.php',
                        'tests' => array(
                            array('function' => 'test_five'),
                        ),
                    ),
                ),
            ),
        ),
    );
    $log = array();
    assert_discovered($logger, $path, $discovered, $log);
}


function test_does_not_find_conditionally_nondeclared_tests(
    strangetest\BasicLogger $logger, $path)
{
    $path .= 'conditional/';
    $discovered = array(
        'directory' => $path,
        'setup' => 'condition\\setup_directory',
        'teardown' => 'condition\\teardown_directory',
        'tests' => array(
            array(
                'file' => 'test_conditional_a.php',
                'tests' => array(
                    array(
                        'namespace' => 'condition',
                        'class' => 'TestA',
                        'tests' => array(
                            array('function' => 'test'),
                        ),
                    ),
                    array(
                        'namespace' => 'condition',
                        'function' => 'test_one',
                    ),
                ),
            ),
            array(
                'file' => 'test_conditional_b.php',
                'tests' => array(
                    array(
                        'namespace' => 'condition',
                        'class' => 'TestB',
                        'tests' => array(
                            array('function' => 'test'),
                        ),
                    ),
                    array(
                        'namespace' => 'condition',
                        'function' => 'test_two',
                    ),
                ),
            ),
        ),
    );
    $log = array();
    assert_discovered($logger, $path, $discovered, $log);
}


function test_handles_error_in_setup_file(strangetest\BasicLogger $logger, $path)
{
    $path .= 'setup_error/';
    $discovered = false;
    // Note that any exception thrown while including a file, including a
    // skip, is reported as an error
    $log = array(
        array(
            strangetest\EVENT_ERROR,
            "{$path}setup.php",
            'Skip me',
        ),
    );
    assert_discovered($logger, $path, $discovered, $log);
}


function test_reports_error_for_multiple_directory_fixtures(
    strangetest\BasicLogger $logger, $path)
{
    $path .= 'multiple_fixtures/';
    $discovered = false;
    $log = array(
        array(
            strangetest\EVENT_ERROR,
            "{$path}setup.php",
            "Multiple conflicting fixtures found:\n    1) setup_directory_multiple_fixtures\n    2) SetupDirectoryMultipleFixtures",
        ),
        array(
            strangetest\EVENT_ERROR,
            "{$path}setup.php",
            "Multiple conflicting fixtures found:\n    1) teardown_directory_multiple_fixtures\n    2) TeardownDirectoryMultipleFixtures",
        ),
    );
    assert_discovered($logger, $path, $discovered, $log);
}


// helper assertions

function assert_discovered($logger, $path, $discovered, $log)
{
    $state = new _DiscoveryState(new State);
    $actual = strangetest\_discover_directory(
        $state,
        new strangetest\BufferingLogger($logger),
        $path,
        0
    );

    $discovered = make_test($discovered);
    strangetest\assert_equal($discovered, $actual);


    $actual = $logger->get_log()->get_events();
    foreach ($actual as $i => $event)
    {
        list($type, $source, $reason) = $event;
        // @bc 5.6 Check if reason is instance of Exception
        if ($reason instanceof \Throwable || $reason instanceof \Exception)
        {
            $actual[$i][2] = $reason->getMessage();
        }
    }
    strangetest\assert_identical($log, $actual);
}
