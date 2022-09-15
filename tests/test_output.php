<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

use strangetest\Logger;


class TestQuietOutput {

    private $logger;

    public function setup() {
        $this->logger = new Logger(strangetest\LOG_QUIET, new NoOutputter);
        ob_start();
    }

    public function teardown() {
        ob_end_clean();
    }


    // helper assertions

    private function assert_output($expected) {
        namespace\assert_report($expected, $this->logger);
    }


    // tests

    public function test_reports_no_tests() {
        $this->assert_output("No tests found!\n");
    }


    public function test_reports_success() {
        $this->logger->log_pass('source', __FILE__, __LINE__);
        $expected = <<<OUT



Seconds elapsed: 1
Memory used: 1 MB
Passed: 1\n
OUT;
        $this->assert_output($expected);
    }


    public function test_reports_error() {
        $file = __FILE__;
        $line = __LINE__;
        $this->logger->log_error('source', 'message', $file, $line);
        $expected = <<<OUT



ERROR: source
message
in $file:$line



Seconds elapsed: 1
Memory used: 1 MB
Errors: 1\n
OUT;
        $this->assert_output($expected);
    }


    public function test_reports_failure() {
        $file = __FILE__;
        $line = __LINE__;
        $this->logger->log_failure('source', 'message', $file, $line);
        $expected = <<<OUT



FAILED: source
message
in $file:$line



Seconds elapsed: 1
Memory used: 1 MB
Failed: 1\n
OUT;
        $this->assert_output($expected);
    }


    public function test_suppresses_skips() {
        $this->logger->log_skip('source', 'message', __FILE__, __LINE__);
        $expected = <<<OUT



This report omitted skipped tests.
To view, rerun Dr. Strangetest with the --verbose option.

Seconds elapsed: 1
Memory used: 1 MB
Skipped: 1\n
OUT;
        $this->assert_output($expected);
    }


    public function test_suppresses_output() {
        $this->logger->log_output('source', 'message', __FILE__, __LINE__, false);
        $expected = <<<OUT



This report omitted output.
To view, rerun Dr. Strangetest with the --verbose option.

Seconds elapsed: 1
Memory used: 1 MB
Output: 1\n
OUT;
        $this->assert_output($expected);
    }


    public function test_reports_output_during_an_error() {
        $file = __FILE__;
        $line = __LINE__;
        $this->logger->log_output('source', 'message', $file, $line, true);
        $expected = <<<OUT



OUTPUT: source
message
in $file:$line



Seconds elapsed: 1
Memory used: 1 MB
Output: 1\n
OUT;
        $this->assert_output($expected);
    }


    public function test_reports_multiple_events() {
        $file = __FILE__;

        $line1 = __LINE__;
        $this->logger->log_pass('pass1', $file, $line1);

        $line2 = __LINE__;
        $this->logger->log_output('output1', 'output 1', $file, $line2, false);

        $line3 = __LINE__;
        $this->logger->log_failure('fail', 'failure', $file, $line3);

        $line4 = __LINE__;
        $this->logger->log_output('output2', 'output 2', $file, $line4, true);

        $line5 = __LINE__;
        $this->logger->log_error('error', 'error', $file, $line5);

        $line6 = __LINE__;
        $this->logger->log_output('output3', 'output 3', $file, $line6, true);

        $line7 = __LINE__;
        $this->logger->log_skip('skip', 'skip', $file, $line7);

        $line8 = __LINE__;
        $this->logger->log_output('output4', 'output 4', $file, $line8, false);

        $expected = <<<OUT



FAILED: fail
failure
in $file:$line3



OUTPUT: output2
output 2
in $file:$line4



ERROR: error
error
in $file:$line5



OUTPUT: output3
output 3
in $file:$line6



This report omitted output and skipped tests.
To view, rerun Dr. Strangetest with the --verbose option.

Seconds elapsed: 1
Memory used: 1 MB
Passed: 1, Failed: 1, Errors: 1, Skipped: 1, Output: 4\n
OUT;
        $this->assert_output($expected);
    }
}



class TestVerboseOutput {

    private $logger;

    public function setup() {
        $this->logger = new Logger(strangetest\LOG_VERBOSE, new NoOutputter);
        ob_start();
    }

    public function teardown() {
        ob_end_clean();
    }


    // helper assertions

    private function assert_output($expected) {
        namespace\assert_report($expected, $this->logger);
    }


    // tests

    public function test_reports_no_tests() {
        $this->assert_output("No tests found!\n");
    }


    public function test_reports_success() {
        $this->logger->log_pass('source', __FILE__, __LINE__);
        $expected = <<<OUT



Seconds elapsed: 1
Memory used: 1 MB
Passed: 1\n
OUT;
        $this->assert_output($expected);
    }


    public function test_reports_error() {
        $file = __FILE__;
        $line = __LINE__;
        $this->logger->log_error('source', 'message', $file, $line);
        $expected = <<<OUT



ERROR: source
message
in $file:$line



Seconds elapsed: 1
Memory used: 1 MB
Errors: 1\n
OUT;
        $this->assert_output($expected);
    }


    public function test_reports_failure() {
        $file = __FILE__;
        $line = __LINE__;
        $this->logger->log_failure('source', 'message', $file, $line);
        $expected = <<<OUT



FAILED: source
message
in $file:$line



Seconds elapsed: 1
Memory used: 1 MB
Failed: 1\n
OUT;
        $this->assert_output($expected);
    }


    public function test_reports_skips() {
        $file = __FILE__;
        $line = __LINE__;
        $this->logger->log_skip('source', 'message', $file, $line);
        $expected = <<<OUT



SKIPPED: source
message
in $file:$line



Seconds elapsed: 1
Memory used: 1 MB
Skipped: 1\n
OUT;
        $this->assert_output($expected);
    }


    public function test_reports_output() {
        $file = __FILE__;
        $line = __LINE__;
        $this->logger->log_output('source', 'message', $file, $line, false);
        $expected = <<<OUT



OUTPUT: source
message
in $file:$line



Seconds elapsed: 1
Memory used: 1 MB
Output: 1\n
OUT;
        $this->assert_output($expected);
    }


    public function test_reports_multiple_events() {
        $file = __FILE__;

        $line1 = __LINE__;
        $this->logger->log_pass('pass1', $file, $line1);

        $line2 = __LINE__;
        $this->logger->log_output('output1', 'output 1', $file, $line2, false);

        $line3 = __LINE__;
        $this->logger->log_failure('fail', 'failure', $file, $line3);

        $line4 = __LINE__;
        $this->logger->log_output('output2', 'output 2', $file, $line4, true);

        $line5 = __LINE__;
        $this->logger->log_error('error', 'error', $file, $line5);

        $line6 = __LINE__;
        $this->logger->log_output('output3', 'output 3', $file, $line6, true);

        $line7 = __LINE__;
        $this->logger->log_skip('skip', 'skip', $file, $line7);

        $line8 = __LINE__;
        $this->logger->log_output('output4', 'output 4', $file, $line8, false);

        $expected = <<<OUT



OUTPUT: output1
output 1
in $file:$line2



FAILED: fail
failure
in $file:$line3



OUTPUT: output2
output 2
in $file:$line4



ERROR: error
error
in $file:$line5



OUTPUT: output3
output 3
in $file:$line6



SKIPPED: skip
skip
in $file:$line7



OUTPUT: output4
output 4
in $file:$line8



Seconds elapsed: 1
Memory used: 1 MB
Passed: 1, Failed: 1, Errors: 1, Skipped: 1, Output: 4\n
OUT;
        $this->assert_output($expected);
    }
}
