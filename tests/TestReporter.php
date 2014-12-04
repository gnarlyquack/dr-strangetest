<?php

class TestReporter {
    private $reporter;

    public function setup() {
        $this->reporter = new easytest\Reporter('EasyTest');
    }

    public function teardown() {
        ob_clean();
    }

    // helper assertions

    private function assert_report($expected) {
        $this->reporter->render_report();
        $expected = "EasyTest\n\n$expected";
        $actual = ob_get_contents();
        assert('$expected === $actual');
    }

    // tests

    public function test_blank_report() {
        $this->assert_report("No tests found!\n");
    }

    public function test_report_success() {
        $this->reporter->report_success();
        $this->assert_report(".\n\nTests: 1\n");
    }

    public function test_report_error() {
        $this->reporter->report_error('source', 'message');
        $expected = <<<OUT
E

======================================================================
ERROR: source
----------------------------------------------------------------------
message

Tests: 0, Errors: 1\n
OUT;
        $this->assert_report($expected);
    }

    public function test_report_failure() {
        $this->reporter->report_failure('source', 'message');
        $expected = <<<OUT
F

======================================================================
FAILURE: source
----------------------------------------------------------------------
message

Tests: 1, Failures: 1\n
OUT;
        $this->assert_report($expected);
    }

    public function test_report_skip() {
        $this->reporter->report_skip('source', 'message');
        $expected = <<<OUT
S

======================================================================
SKIP: source
----------------------------------------------------------------------
message

Tests: 0, Skips: 1\n
OUT;
        $this->assert_report($expected);
    }

    public function test_output_is_normally_suppressed() {
        $actual = $this->reporter->buffer(
            'test_output',
            function() {
                echo 'output';
                return 'foo';
            }
        );

        /* Callback result should be returned */
        assert('"foo" === $actual');

        /* Output should be buffered and reported */
        $expected = <<<OUT
O

Tests: 0, Output: 1\n
OUT;
        $this->assert_report($expected);
    }

    public function test_output_is_reported_on_exception() {
        /* Exception should be re-thrown */
        easytest\assert_exception(
            'easytest\\Failure',
            function () {
                $this->reporter->buffer(
                    'test_output',
                    function() {
                        echo 'output';
                        throw new easytest\Failure();
                    }
                );
            }
        );

        /* Output should be buffered and reported */
        $expected = <<<OUT
O

======================================================================
OUTPUT: test_output
----------------------------------------------------------------------
output

Tests: 0, Output: 1\n
OUT;
        $this->assert_report($expected);
    }

    public function test_multiple_buffers() {
        $expected_buffers = ob_get_level();

        /* Exception should be re-thrown */
        easytest\assert_exception(
            'easytest\\Failure',
            function () {
                $this->reporter->buffer(
                    'test_multiple_buffers',
                    function() {
                        echo 'buffer 1 output';
                        ob_start();
                        // no output in buffer 2
                        ob_start();
                        echo 'buffer 3 output';
                        throw new easytest\Failure();
                    }
                );
            }
        );

        /* All buffers should be cleared */
        $actual_buffers = ob_get_level();
        assert('$expected_buffers === $actual_buffers');

        /* Output should be reported */
        $expected = <<<OUT
O

======================================================================
OUTPUT: test_multiple_buffers
----------------------------------------------------------------------
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Buffer 1 ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
buffer 1 output

~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Buffer 3 ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
buffer 3 output

Tests: 0, Output: 1\n
OUT;
        $this->assert_report($expected);
    }

    public function test_combined_report() {
        $this->reporter->report_success();
        $this->reporter->report_failure('fail1', 'failure 1');
        $this->reporter->report_error('error1', 'error 1');
        $this->reporter->report_skip('skip1', 'skip 1');

        $this->reporter->buffer('output1', function() { echo 'output 1'; });
        $this->reporter->report_success();

        $e = easytest\assert_exception(
            'easytest\\Failure',
            function() {
                $this->reporter->buffer(
                    'output2',
                    function() {
                        echo 'output 2';
                        throw new easytest\Failure('failure 2');
                    }
                );
            }
        );
        $this->reporter->report_failure('fail2', $e->getMessage());

        $e = easytest\assert_exception(
            'easytest\\Error',
            function() {
                $this->reporter->buffer(
                    'output3',
                    function() {
                        echo 'output 3';
                        trigger_error('error 2');
                    }
                );
            }
        );
        $this->reporter->report_error('error2', $e->getMessage());

        $e = easytest\assert_exception(
            'easytest\\Skip',
            function() {
                $this->reporter->buffer(
                    'output4',
                    function() {
                        echo 'output 4';
                        throw new easytest\Skip('skip 2');
                    }
                );
            }
        );
        $this->reporter->report_skip('skip2', $e->getMessage());

        $expected = <<<OUT
.FESO.OFOEOS

======================================================================
FAILURE: fail1
----------------------------------------------------------------------
failure 1

======================================================================
ERROR: error1
----------------------------------------------------------------------
error 1

======================================================================
SKIP: skip1
----------------------------------------------------------------------
skip 1

======================================================================
OUTPUT: output2
----------------------------------------------------------------------
output 2

======================================================================
FAILURE: fail2
----------------------------------------------------------------------
failure 2

======================================================================
OUTPUT: output3
----------------------------------------------------------------------
output 3

======================================================================
ERROR: error2
----------------------------------------------------------------------
error 2

======================================================================
OUTPUT: output4
----------------------------------------------------------------------
output 4

======================================================================
SKIP: skip2
----------------------------------------------------------------------
skip 2

Tests: 4, Errors: 2, Failures: 2, Output: 4, Skips: 2\n
OUT;
        $this->assert_report($expected);
    }
}
