<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestReporter {
    private $reporter;
    private $output;

    public function setup() {
        $this->reporter = new easytest\Reporter('EasyTest', true);
        $this->output = ob_get_contents();
        ob_clean();
    }

    // helper assertions

    private function assert_report($expected_output, $expected_result) {
        $actual_result = $this->reporter->render_report();
        easytest\assert_identical($expected_result, $actual_result);
        $expected_output = "EasyTest\n\n$expected_output";
        $actual_output = $this->output . ob_get_contents();
        ob_clean();
        easytest\assert_identical($expected_output, $actual_output);
    }

    // tests

    public function test_blank_report() {
        $this->assert_report("No tests found!\n", false);
    }

    public function test_report_success() {
        $this->reporter->report_success();
        $this->assert_report(".\n\n\nTests: 1\n", true);
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
        $this->assert_report($expected, false);
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
        $this->assert_report($expected, false);
    }


    public function test_suppresses_skips_in_quiet_mode() {
        $this->reporter->report_skip('source', 'message');
        $expected = <<<OUT
S


This report omitted skipped tests.
To view, rerun easytest with the --verbose option.

Tests: 0, Skips: 1\n
OUT;
        $this->assert_report($expected, true);
    }


    public function test_reports_skips_in_verbose_mode() {
        $this->reporter = new easytest\Reporter('EasyTest', false);
        ob_clean();

        $this->reporter->report_skip('source', 'message');
        $expected = <<<OUT
S

======================================================================
SKIP: source
----------------------------------------------------------------------
message


Tests: 0, Skips: 1\n
OUT;
        $this->assert_report($expected, true);
    }


    public function test_suppresses_output_in_quiet_mode() {
        $actual = $this->reporter->buffer(
            'test_output',
            function() {
                echo 'output';
                return 'foo';
            }
        );

        /* Callback result should be returned */
        easytest\assert_identical('foo', $actual);

        /* Output should be buffered and reported */
        $expected = <<<OUT
O


This report omitted output.
To view, rerun easytest with the --verbose option.

Tests: 0, Output: 1\n
OUT;
        $this->assert_report($expected, true);
    }


    public function test_reports_output_during_exception() {
        /* Exception should be re-thrown */
        easytest\assert_throws(
            'easytest\\Failure',
            function () {
                $this->reporter->buffer(
                    'test_output',
                    function() {
                        echo 'output';
                        easytest\fail('F');
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
        $this->assert_report($expected, true);
    }


    public function test_reports_output_in_verbose_mode() {
        $this->reporter = new easytest\Reporter('EasyTest', false);
        ob_clean();

        $actual = $this->reporter->buffer(
            'test_output',
            function() {
                echo 'output';
                return 'foo';
            }
        );

        /* Callback result should be returned */
        easytest\assert_identical('foo', $actual);

        /* Output should be buffered and reported */
        $expected = <<<OUT
O

======================================================================
OUTPUT: test_output
----------------------------------------------------------------------
output


Tests: 0, Output: 1\n
OUT;
        $this->assert_report($expected, true);
    }

    public function test_multiple_buffers() {
        $expected_buffers = ob_get_level();

        /* Exception should be re-thrown */
        easytest\assert_throws(
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
                        easytest\fail('F');
                    }
                );
            }
        );

        /* All buffers should be cleared */
        $actual_buffers = ob_get_level();
        easytest\assert_identical($expected_buffers, $actual_buffers);

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
        $this->assert_report($expected, true);
    }


    public function test_combined_report_in_quiet_mode() {
        $this->reporter->report_success();
        $this->reporter->report_failure('fail1', 'failure 1');
        $this->reporter->report_error('error1', 'error 1');
        $this->reporter->report_skip('skip1', 'skip 1');

        $this->reporter->buffer('output1', function() { echo 'output 1'; });
        $this->reporter->report_success();

        $e = easytest\assert_throws(
            'easytest\\Failure',
            function() {
                $this->reporter->buffer(
                    'output2',
                    function() {
                        echo 'output 2';
                        easytest\fail('failure 2');
                    }
                );
            }
        );
        $this->reporter->report_failure('fail2', $e->getMessage());

        $e = easytest\assert_throws(
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

        $e = easytest\assert_throws(
            'easytest\\Skip',
            function() {
                $this->reporter->buffer(
                    'output4',
                    function() {
                        echo 'output 4';
                        easytest\skip('skip 2');
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


This report omitted output and skipped tests.
To view, rerun easytest with the --verbose option.

Tests: 4, Errors: 2, Failures: 2, Output: 4, Skips: 2\n
OUT;
        $this->assert_report($expected, false);
    }


    public function test_combined_report_in_verbose_mode() {
        $this->reporter = new easytest\Reporter('EasyTest', false);
        ob_clean();

        $this->reporter->report_success();
        $this->reporter->report_failure('fail1', 'failure 1');
        $this->reporter->report_error('error1', 'error 1');
        $this->reporter->report_skip('skip1', 'skip 1');

        $this->reporter->buffer('output1', function() { echo 'output 1'; });
        $this->reporter->report_success();

        $e = easytest\assert_throws(
            'easytest\\Failure',
            function() {
                $this->reporter->buffer(
                    'output2',
                    function() {
                        echo 'output 2';
                        easytest\fail('failure 2');
                    }
                );
            }
        );
        $this->reporter->report_failure('fail2', $e->getMessage());

        $e = easytest\assert_throws(
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

        $e = easytest\assert_throws(
            'easytest\\Skip',
            function() {
                $this->reporter->buffer(
                    'output4',
                    function() {
                        echo 'output 4';
                        easytest\skip('skip 2');
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
OUTPUT: output1
----------------------------------------------------------------------
output 1

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
        $this->assert_report($expected, false);
    }
}
