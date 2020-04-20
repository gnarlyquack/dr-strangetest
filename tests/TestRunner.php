<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestRunner {
    private $logger;
    private $runner;
    private $blank_report = [
        easytest\LOG_EVENT_PASS => 0,
        easytest\LOG_EVENT_FAIL => 0,
        easytest\LOG_EVENT_ERROR => 0,
        easytest\LOG_EVENT_SKIP => 0,
        easytest\LOG_EVENT_OUTPUT => 0,
        'events' => [],
    ];

    public function setup() {
        $this->logger = new easytest\BufferingLogger(
            new easytest\BasicLogger(true)
        );
        $this->runner = new easytest\Runner($this->logger);
    }

    // helper assertions

    private function assert_run($test, $expected) {
        $actual = $test->log;
        easytest\assert_identical([], $actual);
        $this->runner->run_test_case($test);
        $actual = $test->log;
        easytest\assert_identical($expected, $actual);
    }

    private function assert_report($report) {
        $expected = $this->blank_report;
        foreach ($report as $i => $entry) {
            $expected[$i] = $entry;
        }

        $log = $this->logger->get_log();
        $actual = [
            easytest\LOG_EVENT_PASS => $log->pass_count(),
            easytest\LOG_EVENT_FAIL => $log->failure_count(),
            easytest\LOG_EVENT_ERROR => $log->error_count(),
            easytest\LOG_EVENT_SKIP => $log->skip_count(),
            easytest\LOG_EVENT_OUTPUT => $log->output_count(),
            'events' => $log->get_events(),
        ];
        for ($i = 0, $c = count($actual['events']); $i < $c; ++$i) {
            list($type, $source, $reason) = $actual['events'][$i];
            switch ($type) {
                case easytest\LOG_EVENT_ERROR:
                    if ($reason instanceof Throwable
                        || $reason instanceof Exception)
                    {
                        $reason = $reason->getMessage();
                        $actual['events'][$i][2] = $reason;
                    }
                    break;

                case easytest\LOG_EVENT_FAIL:
                    if ($reason instanceof AssertionError
                        || $reason instanceof easytest\Failure)
                    {
                        $reason = $reason->getMessage();
                        $actual['events'][$i][2] = $reason;
                    }
                    break;

                case easytest\LOG_EVENT_SKIP:
                    $actual['events'][$i][2] = $reason->getMessage();
                    break;
            }
        }

        easytest\assert_identical($expected, $actual);
    }

    // tests

    public function test_run_test_method() {
        $this->assert_run(new SimpleTestCase(), ['test']);
        $this->assert_report([easytest\LOG_EVENT_PASS => 1]);
    }

    public function test_fixtures() {
        $this->assert_run(
            new FixtureTestCase(),
            [
                'setup_class',
                'setup', 'test1', 'teardown',
                'setup', 'test2', 'teardown',
                'teardown_class',
            ]
        );
        $this->assert_report([easytest\LOG_EVENT_PASS => 2]);
    }

    public function test_case_insensitivity() {
        $this->assert_run(
            new CapitalizedTestCase(),
            [
                'SetUpClass',
                'SetUp', 'TestOne', 'TearDown',
                'SetUp', 'TestTwo', 'TearDown',
                'TearDownClass',
            ]
        );
        $this->assert_report([easytest\LOG_EVENT_PASS => 2]);
    }

    public function test_exception() {
        $this->assert_run(
            new ExceptionTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->assert_report([
            easytest\LOG_EVENT_ERROR => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_ERROR,
                    'ExceptionTestCase::test',
                    'How exceptional!'
                ],
            ],
        ]);
    }

    public function test_error() {
        $this->assert_run(
            new ErrorTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->assert_report([
            easytest\LOG_EVENT_ERROR => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_ERROR,
                    'ErrorTestCase::test',
                    'Did I err?'
                ],
            ],
        ]);
    }

    public function test_suppressed_error() {
        $this->assert_run(
            new SuppressedErrorTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->assert_report([easytest\LOG_EVENT_PASS => 1]);
    }

    public function test_failure() {
        $this->assert_run(
            new FailedTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->assert_report([
            easytest\LOG_EVENT_FAIL => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_FAIL,
                    'FailedTestCase::test',
                    'Assertion failed'
                ],
            ],
        ]);
    }

    public function test_setup_class_error() {
        $this->assert_run(
            new SetupClassErrorTestCase(),
            ['setup_class']
        );
        $this->assert_report([
            easytest\LOG_EVENT_ERROR => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_ERROR,
                    'SetupClassErrorTestCase::setup_class',
                    'An error happened'
                ],
            ],
        ]);
    }

    public function test_setup_error() {
        $this->assert_run(
            new SetupErrorTestCase(),
            ['setup_class', 'setup', 'teardown_class']
        );
        $this->assert_report([
            easytest\LOG_EVENT_ERROR => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_ERROR,
                    'setup for SetupErrorTestCase::test',
                    'An error happened'
                ],
            ],
        ]);
    }

    public function test_teardown_error() {
        $this->assert_run(
            new TeardownErrorTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->assert_report([
            easytest\LOG_EVENT_ERROR => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_ERROR,
                    'teardown for TeardownErrorTestCase::test',
                    'An error happened'
                ],
            ],
        ]);
    }

    public function test_teardown_class_error() {
        $this->assert_run(
            new TeardownClassErrorTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->assert_report([
            easytest\LOG_EVENT_PASS => 1,
            easytest\LOG_EVENT_ERROR => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_ERROR,
                    'TeardownClassErrorTestCase::teardown_class',
                    'An error happened'
                ],
            ],
        ]);
    }

    public function test_multiple_setup_class_fixtures() {
        $this->assert_run(
            new MultipleSetupClassTestCase(),
            []
        );
        $this->assert_report([
            easytest\LOG_EVENT_ERROR => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_ERROR,
                    'MultipleSetupClassTestCase',
                    "Multiple methods found:\n\tSetUpClass\n\tsetup_class"
                ],
            ],
        ]);
    }

    public function test_multiple_teardown_class_fixtures() {
        $this->assert_run(
            new MultipleTeardownClassTestCase(),
            []
        );
        $this->assert_report([
            easytest\LOG_EVENT_ERROR => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_ERROR,
                    'MultipleTeardownClassTestCase',
                    "Multiple methods found:\n\tTearDownClass\n\tteardown_class"
                ],
            ],
        ]);
    }

    public function test_skip() {
        $this->assert_run(
            new SkipTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->assert_report([
            easytest\LOG_EVENT_SKIP => 1,
            'events' => [
                [easytest\LOG_EVENT_SKIP, 'SkipTestCase::test', 'Skip me'],
            ],
        ]);
    }

    public function test_skip_in_setup() {
        $this->assert_run(
            new SkipSetupTestCase(),
            ['setup_class', 'setup', 'teardown_class']
        );
        $this->assert_report([
            easytest\LOG_EVENT_SKIP => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_SKIP,
                    'setup for SkipSetupTestCase::test',
                    'Skip me'
                ],
            ],
        ]);
    }

    public function test_skip_in_setup_class() {
        $this->assert_run(
            new SkipSetupClassTestCase(),
            ['setup_class']
        );
        $this->assert_report([
            easytest\LOG_EVENT_SKIP => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_SKIP,
                    'SkipSetupClassTestCase::setup_class',
                    'Skip me'
                ],
            ],
        ]);
    }

    public function test_skip_in_teardown() {
        $this->assert_run(
            new SkipTeardownTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->assert_report([
            easytest\LOG_EVENT_ERROR => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_ERROR,
                    'teardown for SkipTeardownTestCase::test',
                    'Skip me'
                ],
            ],
        ]);
    }

    public function test_skip_in_teardown_class() {
        $this->assert_run(
            new SkipTeardownClassTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->assert_report([
            easytest\LOG_EVENT_PASS => 1,
            easytest\LOG_EVENT_ERROR => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_ERROR,
                    'SkipTeardownClassTestCase::teardown_class',
                    'Skip me'
                ],
            ],
        ]);
    }

    public function test_output_buffering() {
        $this->assert_run(
            new OutputTestCase(),
            []
        );
        $this->assert_report([
            easytest\LOG_EVENT_PASS => 1,
            easytest\LOG_EVENT_OUTPUT => 5,
            'events' => [
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'OutputTestCase::setup_class',
                    'setup_class'
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'setup for OutputTestCase::test',
                    'setup'
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'OutputTestCase::test',
                    'test'
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown for OutputTestCase::test',
                    'teardown'
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'OutputTestCase::teardown_class',
                    'teardown_class'
                ],
            ],
        ]);
    }
}


class SimpleTestCase {
    public $log = [];

    public function test() {
        $this->log[] = __FUNCTION__;
    }
}

class FixtureTestCase {
    public $log = [];

    public function setup_class() {
        $this->log[] = __FUNCTION__;
    }

    public function teardown_class() {
        $this->log[] = __FUNCTION__;
    }

    public function setup() {
        $this->log[] = __FUNCTION__;
    }

    public function teardown() {
        $this->log[] = __FUNCTION__;
    }

    public function test1() {
        $this->log[] = __FUNCTION__;
    }

    public function test2() {
        $this->log[] = __FUNCTION__;
    }
}

class CapitalizedTestCase {
    public $log = [];

    public function SetUpClass() {
        $this->log[] = __FUNCTION__;
    }

    public function TearDownClass() {
        $this->log[] = __FUNCTION__;
    }

    public function SetUp() {
        $this->log[] = __FUNCTION__;
    }

    public function TearDown() {
        $this->log[] = __FUNCTION__;
    }

    public function TestOne() {
        $this->log[] = __FUNCTION__;
    }

    public function TestTwo() {
        $this->log[] = __FUNCTION__;
    }
}


abstract class BaseTestCase {
    public $log = [];

    public function setup_class() {
        $this->log[] = __FUNCTION__;
    }

    public function teardown_class() {
        $this->log[] = __FUNCTION__;
    }

    public function setup() {
        $this->log[] = __FUNCTION__;
    }

    public function teardown() {
        $this->log[] = __FUNCTION__;
    }

    public function test() {
        $this->log[] = __FUNCTION__;
    }
}

class ExceptionTestCase extends BaseTestCase {
    public function test() {
        $this->log[] = __FUNCTION__;
        throw new \Exception('How exceptional!');
    }
}

class ErrorTestCase extends BaseTestCase {
    public function test() {
        $this->log[] = __FUNCTION__;
        trigger_error('Did I err?');
    }
}

class SuppressedErrorTestCase extends BaseTestCase {
    public function test() {
        $this->log[] = __FUNCTION__;
        @$foo['bar'];
    }
}

class FailedTestCase extends BaseTestCase {
    public function test() {
        $this->log[] = __FUNCTION__;
        assert(false, 'Assertion failed');
    }
}

class SetupClassErrorTestCase extends BaseTestCase {
    public function setup_class() {
        $this->log[] = __FUNCTION__;
        throw new \Exception('An error happened');
    }
}

class SetupErrorTestCase extends BaseTestCase {
    public function setup() {
        $this->log[] = __FUNCTION__;
        throw new \Exception('An error happened');
    }
}

class TeardownErrorTestCase extends BaseTestCase {
    public function teardown() {
        $this->log[] = __FUNCTION__;
        throw new \Exception('An error happened');
    }
}

class TeardownClassErrorTestCase extends BaseTestCase {
    public function teardown_class() {
        $this->log[] = __FUNCTION__;
        throw new \Exception('An error happened');
    }
}

class MultipleSetupClassTestCase extends BaseTestCase {
    public function SetUpClass() {
        $this->log[] = __FUNCTION__;
    }
}

class MultipleTeardownClassTestCase extends BaseTestCase {
    public function TearDownClass() {
        $this->log[] = __FUNCTION__;
    }
}

class SkipTestCase extends BaseTestCase {
    public function test() {
        $this->log[] = __FUNCTION__;
        easytest\skip('Skip me');
    }
}

class SkipSetupTestCase extends BaseTestCase {
    public function setup() {
        $this->log[] = __FUNCTION__;
        easytest\skip('Skip me');
    }
}

class SkipSetupClassTestCase extends BaseTestCase {
    public function setup_class() {
        $this->log[] = __FUNCTION__;
        easytest\skip('Skip me');
    }
}

class SkipTeardownTestCase extends BaseTestCase {
    public function teardown() {
        $this->log[] = __FUNCTION__;
        easytest\skip('Skip me');
    }
}

class SkipTeardownClassTestCase extends BaseTestCase {
    public function teardown_class() {
        $this->log[] = __FUNCTION__;
        easytest\skip('Skip me');
    }
}

class OutputTestCase extends BaseTestCase {
    public function setup_class() {
        echo __FUNCTION__, "\n";
    }

    public function teardown_class() {
        echo __FUNCTION__, "\n";
    }

    public function setup() {
        echo __FUNCTION__, "\n";
    }

    public function teardown() {
        echo __FUNCTION__, "\n";
    }

    public function test() {
        echo __FUNCTION__, "\n";
    }
}
