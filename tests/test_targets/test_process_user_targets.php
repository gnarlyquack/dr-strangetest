<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\process_user_targets;

use strangetest;
use strangetest\BasicLogger;
use strangetest\BufferingLogger;
use strangetest\Context;
use strangetest\DirectoryTest;
use strangetest\Target;



// helper functions

function logger()
{
    return new strangetest\BasicLogger(strangetest\LOG_ALL);
}


function target_to_array(Target $target)
{
    $result = (array)$target;
    if (isset($result['subtargets']))
    {
        foreach ($result['subtargets'] as $key => $value)
        {
            $result['subtargets'][$key] = target_to_array($value);
        }
    }
    return $result;
}


// helper assertions

function assert_targets(
    Context $context, $targets, array $actual_errors,
    $expected_root, $expected_targets, array $expected_errors
) {
    if ($targets) {
        foreach ($targets as $key => $value) {
            $targets[$key] = namespace\target_to_array($value);
        }
    }

    $context->subtest(
        function() use ($expected_targets, $targets)
        {
            strangetest\assert_identical($expected_targets, $targets, 'Incorrect targets');
        }
    );
    $context->subtest(
        function() use ($expected_errors, $actual_errors)
        {
            strangetest\assert_identical($expected_errors, $actual_errors, 'Unexpected errors');
        }
    );
}


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
        $path = __DIR__ . \DIRECTORY_SEPARATOR . 'resources' . \DIRECTORY_SEPARATOR;
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

        foreach ($this->args as $arg) {
            $target = $this->root . $arg;
            if (\is_dir($target)) {
                $target .= \DIRECTORY_SEPARATOR;
            }
            $this->targets[$target] = array('name' => $target, 'subtargets' => null);
        }
        $this->assert_targets($context);
    }

    public function test_processes_function_targets(Context $context)
    {
        $this->args = array('test1.php', '--function=test1_2,test1_1');

        $this->targets = array(
            "{$this->root}test1.php" => array(
                'name' => "{$this->root}test1.php",
                'subtargets' => array(
                    'function test1_2' => array(
                        'name' => 'function test1_2',
                        'subtargets' => null,
                    ),
                    'function test1_1' => array(
                        'name' => 'function test1_1',
                        'subtargets' => null,
                    ),
                ),
            ),
        );
        $this->assert_targets($context);
    }

    public function test_processes_class_targets(Context $context)
    {
        $this->args = array('test1.php', '--class=test1_2;test1_3');

        $this->targets = array(
            "{$this->root}test1.php" => array(
                'name' => "{$this->root}test1.php",
                'subtargets' => array(
                    'class test1_2' => array(
                        'name' => 'class test1_2',
                        'subtargets' => null,
                    ),
                    'class test1_3' => array(
                        'name' => 'class test1_3',
                        'subtargets' => null,
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
            "{$this->root}test1.php" => array(
                'name' => "{$this->root}test1.php",
                'subtargets' => array(
                    'class test1_1' => array(
                        'name' => 'class test1_1',
                        'subtargets' => array(
                            'testone' => array(
                                'name' => 'testone',
                                'subtargets' => null,
                            ),
                            'testtwo' => array(
                                'name' => 'testtwo',
                                'subtargets' => null,
                            ),
                        ),
                    ),
                    'class test1_2' => array(
                        'name' => 'class test1_2',
                        'subtargets' => array(
                            'testone' => array(
                                'name' => 'testone',
                                'subtargets' => null,
                            ),
                            'testtwo' => array(
                                'name' => 'testtwo',
                                'subtargets' => null,
                            ),
                        ),
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
            "{$this->root}test1.php" => array(
                'name' => "{$this->root}test1.php",
                'subtargets' => array(
                    'function test1_1' => array(
                        'name' => 'function test1_1',
                        'subtargets' => null,
                    ),
                    'function test1_2' => array(
                        'name' => 'function test1_2',
                        'subtargets' => null,
                    ),
                    'function test1_3' => array(
                        'name' => 'function test1_3',
                        'subtargets' => null,
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
            "{$this->root}test1.php" => array(
                'name' => "{$this->root}test1.php",
                'subtargets' => array(
                    'class test1_1' => array(
                        'name' => 'class test1_1',
                        'subtargets' => null,
                    ),
                    'class test1_2' => array(
                        'name' => 'class test1_2',
                        'subtargets' => array(
                            'testthree' => array(
                                'name' => 'testthree',
                                'subtargets' => null,
                            ),
                            'testtwo' => array(
                                'name' => 'testtwo',
                                'subtargets' => null,
                            ),
                            'testone' => array(
                                'name' => 'testone',
                                'subtargets' => null,
                            ),
                        ),
                    ),
                    'class test1_3' => array(
                        'name' => 'class test1_3',
                        'subtargets' => null,
                    ),
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
            "{$this->root}test1.php" => array(
                'name' => "{$this->root}test1.php",
                'subtargets' => array(
                    'class test1_1' => array(
                        'name' => 'class test1_1',
                        'subtargets' => null,
                    ),
                    'class test1_2' => array(
                        'name' => 'class test1_2',
                        'subtargets' => null,
                    ),
                ),
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
            "{$this->root}test1.php" => array(
                'name' => "{$this->root}test1.php",
                'subtargets' => null,
            ),
            "{$this->root}test2.php" => array(
                'name' => "{$this->root}test2.php",
                'subtargets' => null,
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
            "{$this->root}test1.php" => array(
                'name' => "{$this->root}test1.php",
                'subtargets' => null,
            ),
            "{$this->root}test2.php" => array(
                'name' => "{$this->root}test2.php",
                'subtargets' => null,
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
            "{$this->root}test2.php" => array(
                'name' => "{$this->root}test2.php",
                'subtargets' => null,
            ),
            "{$this->root}test1.php" => array(
                'name' => "{$this->root}test1.php",
                'subtargets' => null,
            ),
            "{$this->root}test_dir/" => array(
                'name' => "{$this->root}test_dir/",
                'subtargets' => null,
            ),
            "{$this->root}test_dir1/" => array(
                'name' => "{$this->root}test_dir1/",
                'subtargets' => null,
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


    private function assert_targets(Context $context)
    {
        $targets = strangetest\process_user_targets($this->logger, $this->tests, $this->args);

        if ($targets)
        {
            foreach ($targets as $key => $value) {
                $targets[$key] = namespace\target_to_array($value);
            }
        }

        $expected_targets = $this->targets;
        $context->subtest(
            function() use ($expected_targets, $targets)
            {
                strangetest\assert_identical(
                    $expected_targets, $targets,
                    'Incorrect targets');
            }
        );

        $events = $this->logger->get_log()->get_events();
        $expected_events = $this->events;
        $context->subtest(
            function() use ($expected_events, $events)
            {
                strangetest\assert_identical(
                    $expected_events, $events,
                    'Unexpected events');
            }
        );
    }
}
