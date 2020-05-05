<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestRunner {
    static public $log;

    private $logger;
    private $runner;

    public function setup() {
        $this->logger = new easytest\BasicLogger(false);
    }

    // helper assertions

    private function assert_run($test, $expected) {
        self::$log = [];
        easytest\_run_class_tests(
            $this->logger,
            easytest\_discover_class($this->logger, \get_class($test)),
            array()
        );
        $actual = self::$log;
        easytest\assert_identical($expected, $actual);
    }


    private function assert_log($expected) {
        namespace\assert_log($expected, $this->logger);
    }


    // tests

    public function test_run_test_method() {
        $this->assert_run(new SimpleTestCase(), array('test'));
        $this->assert_log(array(easytest\LOG_EVENT_PASS => 1));
    }

    public function test_fixtures() {
        $this->assert_run(
            new FixtureTestCase(),
            array(
                'setup_object',
                'setup', 'test1', 'teardown',
                'setup', 'test2', 'teardown',
                'teardown_object',
            )
        );
        $this->assert_log(array(easytest\LOG_EVENT_PASS => 2));
    }

    public function test_case_insensitivity() {
        $this->assert_run(
            new CapitalizedTestCase(),
            array(
                'SetUpObject',
                'SetUp', 'TestOne', 'TearDown',
                'SetUp', 'TestTwo', 'TearDown',
                'TearDownObject',
            )
        );
        $this->assert_log(array(easytest\LOG_EVENT_PASS => 2));
    }

    public function test_exception() {
        $this->assert_run(
            new ExceptionTestCase(),
            array('setup_object', 'setup', 'test', 'teardown', 'teardown_object')
        );
        $this->assert_log(array(
            easytest\LOG_EVENT_ERROR => 1,
            'events' => array(
                array(
                    easytest\LOG_EVENT_ERROR,
                    'ExceptionTestCase::test',
                    'How exceptional!'
                ),
            ),
        ));
    }

    public function test_error() {
        $this->assert_run(
            new ErrorTestCase(),
            array('setup_object', 'setup', 'test', 'teardown', 'teardown_object')
        );
        $this->assert_log(array(
            easytest\LOG_EVENT_ERROR => 1,
            'events' => array(
                array(
                    easytest\LOG_EVENT_ERROR,
                    'ErrorTestCase::test',
                    'Did I err?'
                ),
            ),
        ));
    }

    public function test_suppressed_error() {
        $this->assert_run(
            new SuppressedErrorTestCase(),
            array('setup_object', 'setup', 'test', 'teardown', 'teardown_object')
        );
        $this->assert_log(array(easytest\LOG_EVENT_PASS => 1));
    }

    public function test_failure() {
        $this->assert_run(
            new FailedTestCase(),
            array('setup_object', 'setup', 'test', 'teardown', 'teardown_object')
        );
        $this->assert_log(array(
            easytest\LOG_EVENT_FAIL => 1,
            'events' => array(
                array(
                    easytest\LOG_EVENT_FAIL,
                    'FailedTestCase::test',
                    'Assertion failed'
                ),
            ),
        ));
    }

    public function test_setup_object_error() {
        $this->assert_run(
            new SetupObjectErrorTestCase(),
            array('setup_object')
        );
        $this->assert_log(array(
            easytest\LOG_EVENT_ERROR => 1,
            'events' => array(
                array(
                    easytest\LOG_EVENT_ERROR,
                    'SetupObjectErrorTestCase::setup_object',
                    'An error happened'
                ),
            ),
        ));
    }

    public function test_setup_error() {
        $this->assert_run(
            new SetupErrorTestCase(),
            array('setup_object', 'setup', 'teardown_object')
        );
        $this->assert_log(array(
            easytest\LOG_EVENT_ERROR => 1,
            'events' => array(
                array(
                    easytest\LOG_EVENT_ERROR,
                    'setup for SetupErrorTestCase::test',
                    'An error happened'
                ),
            ),
        ));
    }

    public function test_teardown_error() {
        $this->assert_run(
            new TeardownErrorTestCase(),
            array('setup_object', 'setup', 'test', 'teardown', 'teardown_object')
        );
        $this->assert_log(array(
            easytest\LOG_EVENT_ERROR => 1,
            'events' => array(
                array(
                    easytest\LOG_EVENT_ERROR,
                    'teardown for TeardownErrorTestCase::test',
                    'An error happened'
                ),
            ),
        ));
    }

    public function test_teardown_object_error() {
        $this->assert_run(
            new TeardownObjectErrorTestCase(),
            array('setup_object', 'setup', 'test', 'teardown', 'teardown_object')
        );
        $this->assert_log(array(
            easytest\LOG_EVENT_PASS => 1,
            easytest\LOG_EVENT_ERROR => 1,
            'events' => array(
                array(
                    easytest\LOG_EVENT_ERROR,
                    'TeardownObjectErrorTestCase::teardown_object',
                    'An error happened'
                ),
            ),
        ));
    }

    public function test_reports_error_on_multiple_object_fixtures() {
        $result = easytest\_discover_class($this->logger, 'MultipleObjectFixtureTestCase');
        easytest\assert_identical(false, $result);
        $this->assert_log(array(
            easytest\LOG_EVENT_ERROR => 2,
            'events' => array(
                array(
                    easytest\LOG_EVENT_ERROR,
                    'MultipleObjectFixtureTestCase',
                    "Multiple setup fixtures found:\n\tSetUpObject\n\tsetup_object"
                ),
                array(
                    easytest\LOG_EVENT_ERROR,
                    'MultipleObjectFixtureTestCase',
                    "Multiple teardown fixtures found:\n\tTearDownObject\n\tteardown_object"
                ),
            ),
        ));
    }


    public function test_skip() {
        $this->assert_run(
            new SkipTestCase(),
            array('setup_object', 'setup', 'test', 'teardown', 'teardown_object')
        );
        $this->assert_log(array(easytest\LOG_EVENT_SKIP => 1));
    }

    public function test_skip_in_setup() {
        $this->assert_run(
            new SkipSetupTestCase(),
            array('setup_object', 'setup', 'teardown_object')
        );
        $this->assert_log(array(easytest\LOG_EVENT_SKIP => 1));
    }

    public function test_skip_in_setup_object() {
        $this->assert_run(
            new SkipSetupObjectTestCase(),
            array('setup_object')
        );
        $this->assert_log(array(easytest\LOG_EVENT_SKIP => 1));
    }

    public function test_skip_in_teardown() {
        $this->assert_run(
            new SkipTeardownTestCase(),
            array('setup_object', 'setup', 'test', 'teardown', 'teardown_object')
        );
        $this->assert_log(array(
            easytest\LOG_EVENT_ERROR => 1,
            'events' => array(
                array(
                    easytest\LOG_EVENT_ERROR,
                    'teardown for SkipTeardownTestCase::test',
                    'Skip me'
                ),
            ),
        ));
    }

    public function test_skip_in_teardown_object() {
        $this->assert_run(
            new SkipTeardownObjectTestCase(),
            array('setup_object', 'setup', 'test', 'teardown', 'teardown_object')
        );
        $this->assert_log(array(
            easytest\LOG_EVENT_PASS => 1,
            easytest\LOG_EVENT_ERROR => 1,
            'events' => array(
                array(
                    easytest\LOG_EVENT_ERROR,
                    'SkipTeardownObjectTestCase::teardown_object',
                    'Skip me'
                ),
            ),
        ));
    }


    public function test_logs_output_and_displays_it_on_error() {
        $this->assert_run(
            new OutputTestCase(),
            array(
                'setup_object',
                'setup', 'test_pass', 'teardown',
                'setup', 'test_fail', 'teardown',
                'setup', 'test_error', 'teardown',
                'setup', 'test_skip', 'teardown',
                'teardown_object'
            )
        );
        $this->assert_log(array(
            easytest\LOG_EVENT_PASS => 1,
            easytest\LOG_EVENT_FAIL => 1,
            easytest\LOG_EVENT_ERROR => 1,
            easytest\LOG_EVENT_SKIP => 1,
            easytest\LOG_EVENT_OUTPUT => 10,
            'events' => array(
                array(
                    easytest\LOG_EVENT_OUTPUT,
                    'setup for OutputTestCase::test_fail',
                    'setup output that should be seen',
                ),
                array(
                    easytest\LOG_EVENT_FAIL,
                    'OutputTestCase::test_fail',
                    'Assertion failed'
                ),
                array(
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown for OutputTestCase::test_fail',
                    'teardown output that should be seen',
                ),
                array(
                    easytest\LOG_EVENT_OUTPUT,
                    'setup for OutputTestCase::test_error',
                    'setup output that should be seen',
                ),
                array(
                    easytest\LOG_EVENT_ERROR,
                    'OutputTestCase::test_error',
                    'Did I err?',
                ),
                array(
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown for OutputTestCase::test_error',
                    'teardown output that should be seen',
                ),
            )
        ));
    }


    public function test_allows_our_own_output_buffering() {
        $this->assert_run(
            new OutputBufferingTestCase(),
            array(
                'setup_object',
                'setup', 'test_skip', 'teardown',
                'setup', 'test_error', 'teardown',
                'setup', 'test_fail', 'teardown',
                'setup', 'test_pass', 'teardown',
                'teardown_object'
            )
        );
        $this->assert_log(array(
            easytest\LOG_EVENT_PASS => 1,
            easytest\LOG_EVENT_FAIL => 1,
            easytest\LOG_EVENT_ERROR => 1,
            easytest\LOG_EVENT_SKIP => 1,
            easytest\LOG_EVENT_OUTPUT => 10,
            'events' => array(
                array(
                    easytest\LOG_EVENT_OUTPUT,
                    'setup for OutputBufferingTestCase::test_error',
                    'setup output that should be seen',
                ),
                array(
                    easytest\LOG_EVENT_ERROR,
                    'OutputBufferingTestCase::test_error',
                    'Did I err?',
                ),
                array(
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown for OutputBufferingTestCase::test_error',
                    'teardown output that should be seen',
                ),
                array(
                    easytest\LOG_EVENT_OUTPUT,
                    'setup for OutputBufferingTestCase::test_fail',
                    'setup output that should be seen',
                ),
                array(
                    easytest\LOG_EVENT_FAIL,
                    'OutputBufferingTestCase::test_fail',
                    'Assertion failed'
                ),
                array(
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown for OutputBufferingTestCase::test_fail',
                    'teardown output that should be seen',
                ),
            )
        ));
    }


    public function test_reports_errors_for_undeleted_output_buffers() {
        $this->assert_run(
            new UndeletedOutputBufferTestCase(),
            array('setup_object', 'setup', 'test', 'teardown', 'teardown_object')
        );
        // Note that since the test itself didn't fail, we log a pass, but we
        // also get errors due to dangling output buffers. This seems
        // desirable: errors associated with buffering will get logged and
        // cause the test suite in general to fail (so hopefully people will
        // clean up their tests), but do not otherwise impede testing.
        $this->assert_log(array(
            easytest\LOG_EVENT_PASS => 1,
            easytest\LOG_EVENT_ERROR => 4,
            'events' => array(
                array(
                    easytest\LOG_EVENT_ERROR,
                    'UndeletedOutputBufferTestCase::setup_object',
                    "An output buffer was started but never deleted.\nBuffer contents were: setup_object",
                ),
                array(
                    easytest\LOG_EVENT_ERROR,
                    'teardown for UndeletedOutputBufferTestCase::test',
                    "An output buffer was started but never deleted.\nBuffer contents were: test output",
                ),
                array(
                    easytest\LOG_EVENT_ERROR,
                    'teardown for UndeletedOutputBufferTestCase::test',
                    "An output buffer was started but never deleted.\nBuffer contents were: [the output buffer was empty]",
                ),
                array(
                    easytest\LOG_EVENT_ERROR,
                    'UndeletedOutputBufferTestCase::teardown_object',
                    "An output buffer was started but never deleted.\nBuffer contents were: teardown_object",
                ),
            )
        ));
    }


    public function test_reports_error_for_deleting_easytest_output_buffers() {
        $this->assert_run(
            new DeletingOutputBufferTestCase(),
            array('setup_object', 'setup', 'test', 'teardown', 'teardown_object')
        );
        // Note that since the test itself didn't fail, we log a pass, but we
        // also get errors due to deleting EasyTest's output buffers. This
        // seems desirable: errors associated with buffering will get logged
        // and cause the test suite in general to fail (so hopefully people
        // will clean up their tests), but do not otherwise impede testing.
        $message = "EasyTest's output buffer was deleted! Please start (and delete) your own\noutput buffer(s) using PHP's output control functions.";
        $this->assert_log(array(
            easytest\LOG_EVENT_PASS => 1,
            easytest\LOG_EVENT_ERROR => 5,
            'events' => array(
                array(
                    easytest\LOG_EVENT_ERROR,
                    'DeletingOutputBufferTestCase::setup_object',
                    $message,
                ),
                array(
                    easytest\LOG_EVENT_ERROR,
                    'setup for DeletingOutputBufferTestCase::test',
                    $message,
                ),
                array(
                    easytest\LOG_EVENT_ERROR,
                    'DeletingOutputBufferTestCase::test',
                    $message,
                ),
                array(
                    easytest\LOG_EVENT_ERROR,
                    'teardown for DeletingOutputBufferTestCase::test',
                    $message,
                ),
                array(
                    easytest\LOG_EVENT_ERROR,
                    'DeletingOutputBufferTestCase::teardown_object',
                    $message,
                ),
            )
        ));

        easytest\assert_identical('', \ob_get_contents());
    }
}


class SimpleTestCase {
    public function test() {
        TestRunner::$log[] = __FUNCTION__;
    }
}

class FixtureTestCase {
    public function setup_object() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function teardown_object() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function setup() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function teardown() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function test1() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function test2() {
        TestRunner::$log[] = __FUNCTION__;
    }
}

class CapitalizedTestCase {
    public function SetUpObject() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function TearDownObject() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function SetUp() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function TearDown() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function TestOne() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function TestTwo() {
        TestRunner::$log[] = __FUNCTION__;
    }
}


abstract class BaseTestCase {
    public function setup_object() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function teardown_object() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function setup() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function teardown() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function test() {
        TestRunner::$log[] = __FUNCTION__;
    }
}

class ExceptionTestCase extends BaseTestCase {
    public function test() {
        TestRunner::$log[] = __FUNCTION__;
        throw new \Exception('How exceptional!');
    }
}

class ErrorTestCase extends BaseTestCase {
    public function test() {
        TestRunner::$log[] = __FUNCTION__;
        trigger_error('Did I err?');
    }
}

class SuppressedErrorTestCase extends BaseTestCase {
    public function test() {
        TestRunner::$log[] = __FUNCTION__;
        @$foo['bar'];
    }
}

class FailedTestCase extends BaseTestCase {
    public function test() {
        TestRunner::$log[] = __FUNCTION__;
        easytest\fail('Assertion failed');
    }
}

class SetupObjectErrorTestCase extends BaseTestCase {
    public function setup_object() {
        TestRunner::$log[] = __FUNCTION__;
        throw new \Exception('An error happened');
    }
}

class SetupErrorTestCase extends BaseTestCase {
    public function setup() {
        TestRunner::$log[] = __FUNCTION__;
        throw new \Exception('An error happened');
    }
}

class TeardownErrorTestCase extends BaseTestCase {
    public function teardown() {
        TestRunner::$log[] = __FUNCTION__;
        throw new \Exception('An error happened');
    }
}

class TeardownObjectErrorTestCase extends BaseTestCase {
    public function teardown_object() {
        TestRunner::$log[] = __FUNCTION__;
        throw new \Exception('An error happened');
    }
}

class MultipleObjectFixtureTestCase extends BaseTestCase {
    public function SetUpObject() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function TearDownObject() {
        TestRunner::$log[] = __FUNCTION__;
    }
}

class SkipTestCase extends BaseTestCase {
    public function test() {
        TestRunner::$log[] = __FUNCTION__;
        easytest\skip('Skip me');
    }
}

class SkipSetupTestCase extends BaseTestCase {
    public function setup() {
        TestRunner::$log[] = __FUNCTION__;
        easytest\skip('Skip me');
    }
}

class SkipSetupObjectTestCase extends BaseTestCase {
    public function setup_object() {
        TestRunner::$log[] = __FUNCTION__;
        easytest\skip('Skip me');
    }
}

class SkipTeardownTestCase extends BaseTestCase {
    public function teardown() {
        TestRunner::$log[] = __FUNCTION__;
        easytest\skip('Skip me');
    }
}

class SkipTeardownObjectTestCase extends BaseTestCase {
    public function teardown_object() {
        TestRunner::$log[] = __FUNCTION__;
        easytest\skip('Skip me');
    }
}

class OutputTestCase {
    public function setup_object() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
    }

    public function teardown_object() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
    }

    public function setup() {
        TestRunner::$log[] = __FUNCTION__;
        echo 'setup output that should be seen';
        ob_start();
        echo 'setup output that should not be seen';
    }

    public function teardown() {
        TestRunner::$log[] = __FUNCTION__;
        echo 'teardown output that should not be seen';
        ob_end_clean();
        echo 'teardown output that should be seen';
    }

    public function test_pass() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
    }

    public function test_fail() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
        easytest\fail('Assertion failed');
    }

    public function test_error() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
        trigger_error('Did I err?');
    }

    public function test_skip() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
        easytest\skip('Skip me');
    }
}

class OutputBufferingTestCase {
    public function setup_object() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
    }

    public function teardown_object() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
    }

    public function setup() {
        TestRunner::$log[] = __FUNCTION__;
        echo 'setup output that should be seen';
        ob_start();
        echo 'setup output that should not be seen';
    }

    public function teardown() {
        TestRunner::$log[] = __FUNCTION__;
        echo 'teardown output that should not be seen';
        ob_end_clean();
        echo 'teardown output that should be seen';
    }

    public function test_skip() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
        easytest\skip('Skip me');
    }

    public function test_error() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
        trigger_error('Did I err?');
    }

    public function test_fail() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
        easytest\fail('Assertion failed');
    }

    public function test_pass() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
    }
}

class UndeletedOutputBufferTestCase {
    public function setup_object() {
        TestRunner::$log[] = __FUNCTION__;
        ob_start();
        echo __FUNCTION__;
    }

    public function teardown_object() {
        TestRunner::$log[] = __FUNCTION__;
        ob_start();
        echo __FUNCTION__;
    }

    public function setup() {
        TestRunner::$log[] = __FUNCTION__;
        ob_start();
    }

    public function teardown() {
        TestRunner::$log[] = __FUNCTION__;
    }

    public function test() {
        TestRunner::$log[] = __FUNCTION__;
        ob_start();
        echo 'test output';
    }
}

class DeletingOutputBufferTestCase {
    public function setup_object() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
        ob_end_clean();
    }

    public function teardown_object() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
        ob_end_clean();
    }

    public function setup() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
        ob_end_clean();
    }

    public function teardown() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
        ob_end_clean();
    }

    public function test() {
        TestRunner::$log[] = __FUNCTION__;
        echo __FUNCTION__;
        ob_end_clean();
    }
}
