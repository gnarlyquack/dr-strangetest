<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

use strangetest\BasicLogger;
use strangetest\BufferingLogger;
use strangetest\Context;


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
        $state = new strangetest\State;
        $logger = new BasicLogger(strangetest\LOG_ALL);
        $path = __DIR__ . '/resources/';
        $tests = strangetest\discover_directory($state, new BufferingLogger($logger), $path, 0);
        \assert(!$logger->get_log()->get_events());

        $this->tests = $tests;
        $this->root = $this->tests->name;
    }

    public function setup()
    {
        $this->logger = new BasicLogger(strangetest\LOG_ALL);
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
                strangetest\EVENT_ERROR,
                "{$this->root}foo.php",
                'This path does not exist'),
            array(
                strangetest\EVENT_ERROR,
                "{$this->root}foo_dir",
                'This path does not exist'),
        );
        $this->assert_targets($context);
    }


    public function test_reports_error_for_missing_function_name(Context $context)
    {
        $this->args = array(
            'test1.php',
            '--function=',
            '--function=one,,two',
            '--function=,,,',
        );

        $this->events = array(
            array(
                strangetest\EVENT_ERROR,
                '--function=',
                'This specifier is missing one or more function names'),
            array(
                strangetest\EVENT_ERROR,
                '--function=one,,two',
                'This specifier is missing one or more function names'),
            array(
                strangetest\EVENT_ERROR,
                '--function=,,,',
                'This specifier is missing one or more function names'),
        );
        $this->assert_targets($context);
    }


    public function test_reports_error_for_missing_class_name(Context $context)
    {
        $this->args = array(
            'test1.php',
            '--class=',
            '--class=one;;two',
            '--class=foo;bar;::one,,two,',
            '--class=::one,two',
            '--class=;;;',
        );

        $this->events = array(
            array(
                strangetest\EVENT_ERROR,
                '--class=',
                'This specifier is missing one or more class names'),
            array(
                strangetest\EVENT_ERROR,
                '--class=one;;two',
                'This specifier is missing one or more class names'),
            array(
                strangetest\EVENT_ERROR,
                '--class=foo;bar;::one,,two,',
                'This specifier is missing one or more class names'),
            array(
                strangetest\EVENT_ERROR,
                '--class=::one,two',
                'This specifier is missing one or more class names'),
            array(
                strangetest\EVENT_ERROR,
                '--class=;;;',
                'This specifier is missing one or more class names'),
        );
        $this->assert_targets($context);
    }


    public function test_reports_error_for_missing_method_name(Context $context)
    {
        $this->args = array(
            'test1.php',
            '--class=one::',
            '--class=foo::one,,two',
            '--class=foo::,,,',
        );

        $this->events = array(
            array(
                strangetest\EVENT_ERROR,
                '--class=one::',
                'This specifier is missing one or more method names'),
            array(
                strangetest\EVENT_ERROR,
                '--class=foo::one,,two',
                'This specifier is missing one or more method names'),
            array(
                strangetest\EVENT_ERROR,
                '--class=foo::,,,',
                'This specifier is missing one or more method names'),
        );
        $this->assert_targets($context);
    }

    public function test_reports_error_for_path_outside_test_root(Context $context)
    {
        $this->args = array('test1.php', __FILE__);

        $this->events = array(
            array(
                strangetest\EVENT_ERROR,
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
        $actual = $this->logger->get_log()->get_events();
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
            \assert($this->targets['path'] === $this->tests->name);
            $result = $this->make_test_from_path_target($this->targets, $this->tests);
        }
        return $result;
    }


    private function make_test_from_path_target($target, strangetest\PathTest $tests)
    {
        $result = new strangetest\PathTest;
        $result->name = $tests->name;
        $result->group = $tests->group;
        $result->setup = $tests->setup;
        $result->teardown = $tests->teardown;
        if (isset($target['runs']))
        {
            foreach ($target['runs'] as $run)
            {
                $result->runs[] = $this->make_test_from_run_target(
                    $run, $tests->runs[$run['run']]);
            }
        }
        else
        {
            foreach ($tests->runs as $run)
            {
                $result->runs[] = $this->make_test_from_run_target($target, $run);
            }
        }
        return $result;
    }

    private function make_test_from_run_target($target, strangetest\TestRun $tests)
    {
        $result = new strangetest\TestRun;
        $result->name = $tests->name;
        $result->run_info = $tests->run_info;
        if (isset($target['tests']))
        {
            foreach ($target['tests'] as $test)
            {
                if (\is_string($test))
                {
                    $test = $tests->tests[$test];
                }
                elseif (isset($test['path']))
                {
                    $test = $this->make_test_from_path_target(
                        $test, $tests->tests[$test['path']]);
                }
                else
                {
                    $test = $this->make_test_from_class_target(
                        $test, $tests->tests[$test['class']]);
                }
                $result->tests[] = $test;
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
        $result->file = $tests->file;
        $result->group = $tests->group;
        $result->namespace = $tests->namespace;
        $result->name = $tests->name;
        $result->setup = $tests->setup;
        $result->teardown = $tests->teardown;
        if (isset($target['tests']))
        {
            foreach ($target['tests'] as $test)
            {
                $result->tests[] = $tests->tests[$test];
            }
        }
        else
        {
            $result->tests = $tests->tests;
        }
        return $result;
    }
}
