<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestReporter {
    private $logger;
    private $output;

    public function setup() {
        $this->logger = new easytest\BufferingLogger(
            new easytest\BasicLogger(false)
        );
        $this->output = ob_get_contents();
        ob_clean();
    }

    // helper assertions

    private function assert_output($expected) {
        easytest\output_log($this->logger->get_log());
        $actual = $this->output . ob_get_contents();
        ob_clean();
        easytest\assert_identical($expected, $actual);
    }

    // tests

    public function test_blank_report() {
        $this->assert_output("No tests found!\n", false);
    }

    public function test_report_success() {
        $this->logger->log_pass();
        $this->assert_output("\n\n\nPassed: 1\n", true);
    }

    public function test_report_error() {
        $this->logger->log_error('source', 'message');
        $expected = <<<OUT


======================================================================
ERROR: source
----------------------------------------------------------------------
message


Errors: 1\n
OUT;
        $this->assert_output($expected, false);
    }

    public function test_report_failure() {
        $this->logger->log_failure('source', 'message');
        $expected = <<<OUT


======================================================================
FAILED: source
----------------------------------------------------------------------
message


Failed: 1\n
OUT;
        $this->assert_output($expected, false);
    }


    public function test_suppresses_skips_in_quiet_mode() {
        $this->logger->log_skip('source', 'message');
        $expected = <<<OUT



This report omitted skipped tests.
To view, rerun easytest with the --verbose option.

Skipped: 1\n
OUT;
        $this->assert_output($expected, true);
    }


    public function test_reports_skips_in_verbose_mode() {
        $this->logger = new easytest\BufferingLogger(
            new easytest\BasicLogger(true)
        );
        ob_clean();

        $this->logger->log_skip('source', 'message');
        $expected = <<<OUT


======================================================================
SKIPPED: source
----------------------------------------------------------------------
message


Skipped: 1\n
OUT;
        $this->assert_output($expected, true);
    }


    public function test_suppresses_output_in_quiet_mode() {
        $actual = $this->logger->buffer(
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



This report omitted output.
To view, rerun easytest with the --verbose option.

Output: 1\n
OUT;
        $this->assert_output($expected, true);
    }


    public function test_reports_output_during_exception() {
        /* Exception should be re-thrown */
        easytest\assert_throws(
            'easytest\\Failure',
            function () {
                $this->logger->buffer(
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


======================================================================
OUTPUT: test_output
----------------------------------------------------------------------
output


Output: 1\n
OUT;
        $this->assert_output($expected, true);
    }


    public function test_reports_output_in_verbose_mode() {
        $this->logger = new easytest\BufferingLogger(
            new easytest\BasicLogger(true)
        );
        ob_clean();

        $actual = $this->logger->buffer(
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


======================================================================
OUTPUT: test_output
----------------------------------------------------------------------
output


Output: 1\n
OUT;
        $this->assert_output($expected, true);
    }

    public function test_multiple_buffers() {
        $expected_buffers = ob_get_level();

        /* Exception should be re-thrown */
        easytest\assert_throws(
            'easytest\\Failure',
            function () {
                $this->logger->buffer(
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


======================================================================
OUTPUT: test_multiple_buffers
----------------------------------------------------------------------
buffer 1 output


buffer 3 output


Output: 1\n
OUT;
        $this->assert_output($expected, true);
    }


    public function test_combined_report_in_quiet_mode() {
        $this->logger->log_pass();
        $this->logger->log_failure('fail1', 'failure 1');
        $this->logger->log_error('error1', 'error 1');
        $this->logger->log_skip('skip1', 'skip 1');

        $this->logger->buffer('output1', function() { echo 'output 1'; });
        $this->logger->log_pass();

        $e = easytest\assert_throws(
            'easytest\\Failure',
            function() {
                $this->logger->buffer(
                    'output2',
                    function() {
                        echo 'output 2';
                        easytest\fail('failure 2');
                    }
                );
            }
        );
        $this->logger->log_failure('fail2', $e->getMessage());

        $e = easytest\assert_throws(
            'easytest\\Error',
            function() {
                $this->logger->buffer(
                    'output3',
                    function() {
                        echo 'output 3';
                        trigger_error('error 2');
                    }
                );
            }
        );
        $this->logger->log_error('error2', $e->getMessage());

        $e = easytest\assert_throws(
            'easytest\\Skip',
            function() {
                $this->logger->buffer(
                    'output4',
                    function() {
                        echo 'output 4';
                        easytest\skip('skip 2');
                    }
                );
            }
        );
        $this->logger->log_skip('skip2', $e->getMessage());

        $expected = <<<OUT


======================================================================
FAILED: fail1
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
FAILED: fail2
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

Passed: 2, Failed: 2, Errors: 2, Skipped: 2, Output: 4\n
OUT;
        $this->assert_output($expected, false);
    }


    public function test_combined_report_in_verbose_mode() {
        $this->logger = new easytest\BufferingLogger(
            new easytest\BasicLogger(true)
        );
        ob_clean();

        $this->logger->log_pass();
        $this->logger->log_failure('fail1', 'failure 1');
        $this->logger->log_error('error1', 'error 1');
        $this->logger->log_skip('skip1', 'skip 1');

        $this->logger->buffer('output1', function() { echo 'output 1'; });
        $this->logger->log_pass();

        $e = easytest\assert_throws(
            'easytest\\Failure',
            function() {
                $this->logger->buffer(
                    'output2',
                    function() {
                        echo 'output 2';
                        easytest\fail('failure 2');
                    }
                );
            }
        );
        $this->logger->log_failure('fail2', $e->getMessage());

        $e = easytest\assert_throws(
            'easytest\\Error',
            function() {
                $this->logger->buffer(
                    'output3',
                    function() {
                        echo 'output 3';
                        trigger_error('error 2');
                    }
                );
            }
        );
        $this->logger->log_error('error2', $e->getMessage());

        $e = easytest\assert_throws(
            'easytest\\Skip',
            function() {
                $this->logger->buffer(
                    'output4',
                    function() {
                        echo 'output 4';
                        easytest\skip('skip 2');
                    }
                );
            }
        );
        $this->logger->log_skip('skip2', $e->getMessage());

        $expected = <<<OUT


======================================================================
FAILED: fail1
----------------------------------------------------------------------
failure 1

======================================================================
ERROR: error1
----------------------------------------------------------------------
error 1

======================================================================
SKIPPED: skip1
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
FAILED: fail2
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
SKIPPED: skip2
----------------------------------------------------------------------
skip 2


Passed: 2, Failed: 2, Errors: 2, Skipped: 2, Output: 4\n
OUT;
        $this->assert_output($expected);
    }
}
