<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestRunner {
    private $reporter;
    private $runner;

    public function setup() {
        $this->reporter = new StubReporter();
        $this->runner = new easytest\Runner($this->reporter);
    }

    // helper assertions

    private function assert_run($test, $expected) {
        $actual = $test->log;
        easytest\assert_identical([], $actual);
        $this->runner->run_test_case($test);
        $actual = $test->log;
        easytest\assert_identical($expected, $actual);
    }

    // tests

    public function test_run_test_method() {
        $this->assert_run(new SimpleTestCase(), ['test']);
        $this->reporter->assert_report(['Tests' => 1]);
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
        $this->reporter->assert_report(['Tests' => 2]);
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
        $this->reporter->assert_report(['Tests' => 2]);
    }

    public function test_exception() {
        $this->assert_run(
            new ExceptionTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->reporter->assert_report([
            'Errors' => [
                ['ExceptionTestCase::test', 'How exceptional!'],
            ],
        ]);
    }

    public function test_error() {
        $this->assert_run(
            new ErrorTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->reporter->assert_report([
            'Errors' => [
                ['ErrorTestCase::test', 'Did I err?'],
            ],
        ]);
    }

    public function test_suppressed_error() {
        $this->assert_run(
            new SuppressedErrorTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->reporter->assert_report(['Tests' => 1]);
    }

    public function test_failure() {
        $this->assert_run(
            new FailedTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->reporter->assert_report([
            'Failures' => [
                ['FailedTestCase::test', 'Assertion failed'],
            ],
        ]);
    }

    public function test_setup_class_error() {
        $this->assert_run(
            new SetupClassErrorTestCase(),
            ['setup_class']
        );
        $this->reporter->assert_report([
            'Errors' => [
                ['SetupClassErrorTestCase::setup_class', 'An error happened'],
            ],
        ]);
    }

    public function test_setup_error() {
        $this->assert_run(
            new SetupErrorTestCase(),
            ['setup_class', 'setup', 'teardown_class']
        );
        $this->reporter->assert_report([
            'Errors' => [
                ['setup for SetupErrorTestCase::test', 'An error happened'],
            ],
        ]);
    }

    public function test_teardown_error() {
        $this->assert_run(
            new TeardownErrorTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->reporter->assert_report([
            'Errors' => [
                ['teardown for TeardownErrorTestCase::test', 'An error happened'],
            ],
        ]);
    }

    public function test_teardown_class_error() {
        $this->assert_run(
            new TeardownClassErrorTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->reporter->assert_report([
            'Tests' => 1,
            'Errors' => [
                ['TeardownClassErrorTestCase::teardown_class', 'An error happened'],
            ],
        ]);
    }

    public function test_multiple_setup_class_fixtures() {
        $this->assert_run(
            new MultipleSetupClassTestCase(),
            []
        );
        $this->reporter->assert_report([
            'Errors' => [
                [
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
        $this->reporter->assert_report([
            'Errors' => [
                [
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
        $this->reporter->assert_report([
            'Skips' => [
                ['SkipTestCase::test', 'Skip me'],
            ],
        ]);
    }

    public function test_skip_in_setup() {
        $this->assert_run(
            new SkipSetupTestCase(),
            ['setup_class', 'setup', 'teardown_class']
        );
        $this->reporter->assert_report([
            'Skips' => [
                ['setup for SkipSetupTestCase::test', 'Skip me'],
            ],
        ]);
    }

    public function test_skip_in_setup_class() {
        $this->assert_run(
            new SkipSetupClassTestCase(),
            ['setup_class']
        );
        $this->reporter->assert_report([
            'Skips' => [
                ['SkipSetupClassTestCase::setup_class', 'Skip me'],
            ],
        ]);
    }

    public function test_skip_in_teardown() {
        $this->assert_run(
            new SkipTeardownTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->reporter->assert_report([
            'Errors' => [
                ['teardown for SkipTeardownTestCase::test', 'Skip me'],
            ],
        ]);
    }

    public function test_skip_in_teardown_class() {
        $this->assert_run(
            new SkipTeardownClassTestCase(),
            ['setup_class', 'setup', 'test', 'teardown', 'teardown_class']
        );
        $this->reporter->assert_report([
            'Tests' => 1,
            'Errors' => [
                ['SkipTeardownClassTestCase::teardown_class', 'Skip me'],
            ],
        ]);
    }

    public function test_output_buffering() {
        $this->assert_run(
            new OutputTestCase(),
            []
        );
        $this->reporter->assert_report([
            'Tests' => 1,
            'Output' => [
                ['OutputTestCase::setup_class', 'setup_class'],
                ['setup for OutputTestCase::test', 'setup'],
                ['OutputTestCase::test', 'test'],
                ['teardown for OutputTestCase::test', 'teardown'],
                ['OutputTestCase::teardown_class', 'teardown_class'],
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
