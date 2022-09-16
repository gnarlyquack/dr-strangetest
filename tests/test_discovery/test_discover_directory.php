<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\discover\directory;
use strangetest;
use strangetest\Logger;
use strangetest\State;
use strangetest\_DiscoveryState;

use NoOutputter;


function setup()
{
    return array(
        new Logger(strangetest\LOG_ALL, new NoOutputter),
        __DIR__ . '/resources/directory/',
    );
}


// tests

function test_discover_directory(
    Logger $logger, $path,
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


function test_does_not_find_conditionally_nondeclared_tests(Logger $logger, $path)
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


function test_handles_error_in_setup_file(Logger $logger, $path)
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


function test_reports_error_for_multiple_directory_fixtures(Logger $logger, $path)
{
    $path .= 'multiple_fixtures/';
    $discovered = false;
    $log = array(
        array(
            strangetest\EVENT_ERROR,
            'SetupDirectoryMultipleFixtures',
            'This fixture conflicts with \'setup_directory_multiple_fixtures\' defined on line 3',
        ),
        array(
            strangetest\EVENT_ERROR,
            'TeardownDirectoryMultipleFixtures',
            'This fixture conflicts with \'teardown_directory_multiple_fixtures\' defined on line 7',
        ),
    );
    assert_discovered($logger, $path, $discovered, $log);
}


// helper assertions

function assert_discovered($logger, $path, $discovered, $log)
{
    $state = new _DiscoveryState(new State, $logger);
    $actual = strangetest\_discover_directory($state, $path, 0);

    $discovered = make_test($discovered);
    strangetest\assert_equal($actual, $discovered);


    $actual = $logger->get_log()->get_events();
    foreach ($actual as $i => $event)
    {
        if ($event instanceof strangetest\PassEvent)
        {
            $type = strangetest\EVENT_PASS;
            $source = $event->source;
            $reason = null;
        }
        elseif ($event instanceof strangetest\FailEvent)
        {
            $type = strangetest\EVENT_FAIL;
            $source = $event->source;
            $reason = $event->reason;
        }
        elseif ($event instanceof strangetest\ErrorEvent)
        {
            $type = strangetest\EVENT_ERROR;
            $source = $event->source;
            $reason = $event->reason;
        }
        elseif ($event instanceof strangetest\SkipEvent)
        {
            $type = strangetest\EVENT_SKIP;
            $source = $event->source;
            $reason = $event->reason;
        }
        else
        {
            \assert($event instanceof strangetest\OutputEvent);
            $type = strangetest\EVENT_OUTPUT;
            $source = $event->source;
            $reason = $event->output;
        }

        $actual[$i] = array($type, $source, $reason);
    }
    strangetest\assert_identical($actual, $log);
}
