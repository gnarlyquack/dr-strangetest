<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

use strangetest\Context;
use strangetest\LogBufferer;
use strangetest\Logger;
use strangetest\State;


// tests

class TestProcessUserTargets
{
    private $tests;
    private $logger;
    private $args;
    private $root;

    private $targets;
    private $events;

    public function setup_object()
    {
        $state = new State;
        $state->bufferer = new LogBufferer(\TEST_ROOT);
        $logger = new Logger(\TEST_ROOT, strangetest\LOG_ALL, new NoOutputter);
        $state->logger = $logger;
        $path = __DIR__ . '/resources/';
        $tests = strangetest\discover_tests($state, $path, 0);
        \assert(!$logger->get_log()->events);

        $this->tests = $tests;
        $this->root = ($this->tests instanceof strangetest\DirectoryTest)
            ? $this->tests->name
            : $this->tests->path;
    }

    public function setup()
    {
        $this->logger = new Logger(\TEST_ROOT, strangetest\LOG_ALL, new NoOutputter);
        $this->targets = null;
        $this->events = array();
    }

    public function test_processes_paths_as_path_targets(Context $context)
    {
        $this->args = array('test1.php', 'test2.php', 'test_dir');

        $this->targets = array(
            'path' => $this->root,
            'tests' => array(
                array('path' => "{$this->root}test1.php"),
                array('path' => "{$this->root}test2.php"),
                array('path' => "{$this->root}test_dir/"),
            ),
        );
        $this->assert_targets($context);
    }

    public function test_processes_function_targets(Context $context)
    {
        $this->args = array('test1.php', '--function=test1_2,test1_1');

        $this->targets = array(
            'path' => $this->root,
            'tests' => array(
                array(
                    'path' => "{$this->root}test1.php",
                    'tests' => array('function test1_2', 'function test1_1'),
                ),
            ),
        );
        $this->assert_targets($context);
    }

    public function test_processes_class_targets(Context $context)
    {
        $this->args = array('test1.php', '--class=test1_2;test1_3');

        $this->targets = array(
            'path' => $this->root,
            'tests' => array(
                array(
                    'path' => "{$this->root}test1.php",
                    'tests' => array(
                        array('class' => 'class test1_2'),
                        array('class' => 'class test1_3'),
                    ),
                ),
            ),
        );
        $this->assert_targets($context);
    }

    public function test_processes_method_targets(Context $context)
    {
        $this->args = array(
            'test1.php',
            '--class=test1_1::testone,testtwo;test1_2::testone,testtwo');

        $this->targets = array(
            'path' => $this->root,
            'tests' => array(
                array(
                    'path' => "{$this->root}test1.php",
                    'tests' => array(
                        array(
                            'class' => 'class test1_1',
                            'tests' => array('testone', 'testtwo'),
                        ),
                        array(
                            'class' => 'class test1_2',
                            'tests' => array('testone', 'testtwo'),
                        )
                    ),
                ),
            ),
        );
        $this->assert_targets($context);
    }

    public function test_eliminates_duplicate_function_targets(Context $context)
    {
        $this->args = array(
            'test1.php',
            '--function=test1_1,test1_2',
            '--function=test1_1,test1_3');

        $this->targets = array(
            'path' => $this->root,
            'tests' => array(
                array(
                    'path' => "{$this->root}test1.php",
                    'tests' => array(
                        'function test1_1',
                        'function test1_2',
                        'function test1_3',
                    ),
                ),
            ),
        );
        $this->assert_targets($context);
    }


    public function test_eliminates_duplicate_method_targets(Context $context)
    {
        $this->args = array(
            'test1.php',
            '--class=test1_1;test1_2::testthree,testtwo;test1_3::testone,testtwo',
            '--class=test1_1::testone,testtwo',
            '--class=test1_3',
            '--class=test1_2::testone,testtwo',
        );

        $this->targets = array(
            'path' => $this->root,
            'tests' => array(
                array(
                    'path' => "{$this->root}test1.php",
                    'tests' => array(
                        array('class' => 'class test1_1'),
                        array(
                            'class' => 'class test1_2',
                            'tests' => array('testthree', 'testtwo', 'testone'),
                        ),
                        array('class' => 'class test1_3'),
                    )
                ),
            ),
        );
        $this->assert_targets($context);
    }


    public function test_overrides_method_targets_with_class_target(Context $context)
    {
        $this->args = array(
            'test1.php',
            '--class=test1_1::testone,testtwo',
            '--class=test1_2',
            '--class=test1_1',
            '--class=test1_2::testone,testtwo',
        );

        $this->targets = array(
            'path' => $this->root,
            'tests' => array(
                array(
                    'path' => "{$this->root}test1.php",
                    'tests' => array(
                        array('class' => 'class test1_1'),
                        array('class' => 'class test1_2'),
                    ),
                )
            ),
        );
        $this->assert_targets($context);
    }

    public function test_eliminates_duplicate_targets_in_file(Context $context)
    {
        $this->args = array(
            'test1.php',
            'test2.php',
            'test1.php',
            '--class=test1_1;test1_2::testone,testtwo',
            '--function=test1_1,test1_2',
        );

        $this->targets = array(
            'path' => $this->root,
            'tests' => array(
                array('path' => "{$this->root}test1.php"),
                array('path' => "{$this->root}test2.php"),
            ),
        );
        $this->assert_targets($context);
    }

    public function test_overrides_targets_in_file_with_file_target(Context $context)
    {
        $this->args = array(
            'test1.php',
            '--class=test1_1::testone,testtwo;test1_2',
            'test2.php',
            'test1.php',
        );

        $this->targets = array(
            'path' => $this->root,
            'tests' => array(
                array('path' => "{$this->root}test1.php"),
                array('path' => "{$this->root}test2.php"),
            ),
        );
        $this->assert_targets($context);
    }

    public function test_eliminates_duplicate_path_targets(Context $context)
    {
        $this->args = array(
            'test2.php',
            'test1.php',
            'test_dir/test_subdir',
            'test_dir1',
            'test_dir',
            'test2.php',
            'test_dir1/test2.php',
            'test_dir1/test1.php',
        );

        $this->targets = array(
            'path' => $this->root,
            'tests' => array(
                array('path' => "{$this->root}test2.php"),
                array('path' => "{$this->root}test1.php"),
                array('path' => "{$this->root}test_dir/"),
                array('path' => "{$this->root}test_dir1/"),
            ),
        );
        $this->assert_targets($context);
    }


    function test_reports_error_for_nonexistent_paths(Context $context)
    {
        $this->args = array(
            'foo.php',
            'test1.php',
            'foo_dir',
        );

        $this->events = array(
            array(
                \EVENT_ERROR,
                'foo.php',
                'This path does not exist'),
            array(
                \EVENT_ERROR,
                'foo_dir',
                'This path does not exist'),
        );
        $this->assert_targets($context);
    }


    public function test_reports_error_for_missing_function_name(Context $context)
    {
        $this->args = array(
            'test1.php',
            '--function=',
            '--function=test1_1,,test1_2',
            '--function=,,,',
        );

        $this->events = array(
            array(
                \EVENT_ERROR,
                '--function=',
                'Function specifier 1 is missing a name'),
            array(
                \EVENT_ERROR,
                '--function=test1_1,,test1_2',
                'Function specifier 2 is missing a name'),
            array(
                \EVENT_ERROR,
                '--function=,,,',
                'Function specifier 1 is missing a name'),
            array(
                \EVENT_ERROR,
                '--function=,,,',
                'Function specifier 2 is missing a name'),
            array(
                \EVENT_ERROR,
                '--function=,,,',
                'Function specifier 3 is missing a name'),
            array(
                \EVENT_ERROR,
                '--function=,,,',
                'Function specifier 4 is missing a name'),
        );
        $this->assert_targets($context);
    }


    public function test_reports_error_for_missing_class_name(Context $context)
    {
        $this->args = array(
            'test1.php',
            '--class=',
            '--class=test1_1;;test1_3',
            '--class=test1_1;test1_2;::one,,two,',
            '--class=::one,two',
            '--class=;;;',
        );

        $this->events = array(
            array(
                \EVENT_ERROR,
                '--class=',
                'Class specifier 1 is missing a name'),
            array(
                \EVENT_ERROR,
                '--class=test1_1;;test1_3',
                'Class specifier 2 is missing a name'),
            array(
                \EVENT_ERROR,
                '--class=test1_1;test1_2;::one,,two,',
                'Class specifier 3 is missing a name'),
            array(
                \EVENT_ERROR,
                '--class=::one,two',
                'Class specifier 1 is missing a name'),
            array(
                \EVENT_ERROR,
                '--class=;;;',
                'Class specifier 1 is missing a name'),
            array(
                \EVENT_ERROR,
                '--class=;;;',
                'Class specifier 2 is missing a name'),
            array(
                \EVENT_ERROR,
                '--class=;;;',
                'Class specifier 3 is missing a name'),
            array(
                \EVENT_ERROR,
                '--class=;;;',
                'Class specifier 4 is missing a name'),
        );
        $this->assert_targets($context);
    }


    public function test_reports_error_for_missing_method_name(Context $context)
    {
        $this->args = array(
            'test1.php',
            '--class=test1_1::',
            '--class=test1_2::testone,,testthree',
            '--class=test1_3::,,,',
        );

        $this->events = array(
            array(
                \EVENT_ERROR,
                '--class=test1_1::',
                "Method specifier 1 for class 'test1_1' is missing a name"),
            array(
                \EVENT_ERROR,
                '--class=test1_2::testone,,testthree',
                "Method specifier 2 for class 'test1_2' is missing a name"),
            array(
                \EVENT_ERROR,
                '--class=test1_3::,,,',
                "Method specifier 1 for class 'test1_3' is missing a name"),
            array(
                \EVENT_ERROR,
                '--class=test1_3::,,,',
                "Method specifier 2 for class 'test1_3' is missing a name"),
            array(
                \EVENT_ERROR,
                '--class=test1_3::,,,',
                "Method specifier 3 for class 'test1_3' is missing a name"),
            array(
                \EVENT_ERROR,
                '--class=test1_3::,,,',
                "Method specifier 4 for class 'test1_3' is missing a name"),
        );
        $this->assert_targets($context);
    }

    public function test_reports_error_for_path_outside_test_root(Context $context)
    {
        $this->args = array('test1.php', __FILE__);

        $this->events = array(
            array(
                \EVENT_ERROR,
                __FILE__,
                "This path is outside the test root directory {$this->root}"),
        );
        $this->assert_targets($context);
    }

    public function test_parses_runs(Context $context)
    {
        $this->args = array('test1.php', '--run=run1');

        $this->targets = array(
            'path' => $this->root,
            'runs' => array(
                array(
                    'run' => 'run1',
                    'tests' => array(
                        array('path' => "{$this->root}test1.php"),
                    ),
                ),
            ),
        );
        $this->assert_targets($context);
    }


    // helper methods

    private function assert_targets(Context $context)
    {
        $expected = $this->make_tests_from_targets();
        $actual = strangetest\process_specifiers($this->logger, $this->tests, $this->args);

        $context->subtest(
            function() use ($expected, $actual)
            {
                strangetest\assert_equal(
                    $expected, $actual,
                    'Incorrect targets');
            }
        );

        $expected = $this->events;
        $events = $this->logger->get_log()->events;
        $actual = array();
        foreach ($events as $event)
        {
            if ($event instanceof strangetest\PassEvent)
            {
                $type = \EVENT_PASS;
                $source = $event->source;
                $reason = null;
            }
            elseif ($event instanceof strangetest\FailEvent)
            {
                $type = \EVENT_FAIL;
                $source = $event->source;
                $reason = $event->reason;
            }
            elseif ($event instanceof strangetest\ErrorEvent)
            {
                $type = \EVENT_ERROR;
                $source = $event->source;
                $reason = $event->reason;
            }
            elseif ($event instanceof strangetest\SkipEvent)
            {
                $type = \EVENT_SKIP;
                $source = $event->source;
                $reason = $event->reason;
            }
            else
            {
                \assert($event instanceof strangetest\OutputEvent);
                $type = \EVENT_OUTPUT;
                $source = $event->source;
                $reason = $event->output;
            }

            $actual[] = array($type, $source, $reason);
        }
        $context->subtest(
            function() use ($expected, $actual)
            {
                strangetest\assert_identical(
                    $expected, $actual,
                    'Unexpected events');
            }
        );
    }

    private function make_tests_from_targets()
    {
        $result = null;
        if ($this->targets)
        {
            \assert($this->targets['path'] === $this->root);
            if ($this->tests instanceof strangetest\TestRunGroup)
            {
                $result = $this->make_test_from_run_target($this->targets, $this->tests);
            }
            else
            {
                $result = $this->make_test_from_directory_target($this->targets, $this->tests);
            }
        }
        return $result;
    }


    private function make_test_from_run_target($target, strangetest\TestRunGroup $tests)
    {
        $result = new strangetest\TestRunGroup;
        $result->id = $tests->id;
        $result->path = $tests->path;

        if (isset($target['runs']))
        {
            foreach ($target['runs'] as $run)
            {
                $source = $tests->runs[$run['run']];
                $test_run = new strangetest\TestRun;
                $test_run->name = $source->name;
                $test_run->setup = $source->setup;
                $test_run->teardown = $source->teardown;
                if ($source->tests instanceof strangetest\DirectoryTest)
                {
                    $test_run->tests = $this->make_test_from_directory_target(
                        $run, $source->tests);
                }
                else
                {
                    $test_run->tests = $this->make_test_from_file_target(
                        $run, $source->tests);
                }
                $result->runs[$run['run']] = $test_run;
            }
        }
        else
        {
            foreach ($tests->runs as $run)
            {
                $test_run = new strangetest\TestRun;
                $test_run->name = $run->name;
                $test_run->setup = $run->setup;
                $test_run->teardown = $run->teardown;
                if ($run->tests instanceof strangetest\DirectoryTest)
                {
                    $test_run->tests = $this->make_test_from_directory_target(
                        $target, $run->tests);
                }
                else
                {
                    $test_run->tests = $this->make_test_from_file_target(
                        $target, $run->tests);
                }
                $result->runs[$run->name] = $test_run;
            }
        }

        return $result;
    }

    private function make_test_from_directory_target($target, strangetest\DirectoryTest $tests)
    {
        $result = new strangetest\DirectoryTest;
        $result->name = $tests->name;
        $result->setup = $tests->setup;
        $result->teardown = $tests->teardown;

        if (isset($target['tests']))
        {
            foreach ($target['tests'] as $test)
            {
                $path = $test['path'];
                $source = $tests->tests[$path];
                if ($source instanceof strangetest\TestRunGroup)
                {
                    $test = $this->make_test_from_run_target($test, $source);
                }
                elseif ($source instanceof strangetest\DirectoryTest)
                {
                    $test = $this->make_test_from_directory_target($test, $source);
                }
                else
                {
                    \assert($source instanceof strangetest\FileTest);
                    $test = $this->make_test_from_file_target($test, $source);
                }
                $result->tests[$path] = $test;
            }
        }
        else
        {
            $result->tests = $tests->tests;
        }
        return $result;
    }

    private function make_test_from_file_target($target, strangetest\FileTest $tests)
    {
        $result = new strangetest\FileTest;
        $result->name = $tests->name;
        $result->setup_file = $tests->setup_file;
        $result->teardown_file = $tests->teardown_file;
        $result->setup_function = $tests->setup_function;
        $result->teardown_function = $tests->teardown_function;

        if (isset($target['tests']))
        {
            foreach ($target['tests'] as $test)
            {
                if (\is_string($test))
                {
                    $name = $test;
                    $test = $tests->tests[$test];
                }
                else
                {
                    $name = $test['class'];
                    $test = $this->make_test_from_class_target(
                        $test, $tests->tests[$test['class']]);
                }
                $result->tests[$name] = $test;
            }
        }
        else
        {
            $result->tests = $tests->tests;
        }
        return $result;
    }

    private function make_test_from_class_target($target, strangetest\ClassTest $tests)
    {
        $result = new strangetest\ClassTest;
        $result->test = $tests->test;
        $result->setup_object = $tests->setup_object;
        $result->teardown_object = $tests->teardown_object;
        $result->setup_method = $tests->setup_method;
        $result->teardown_method = $tests->teardown_method;
        if (isset($target['tests']))
        {
            foreach ($target['tests'] as $test)
            {
                $result->tests[$test] = $tests->tests[$test];
            }
        }
        else
        {
            $result->tests = $tests->tests;
        }
        return $result;
    }
}
