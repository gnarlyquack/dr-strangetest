<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

use strangetest\Logger;
use strangetest\PassEvent;
use strangetest\FailEvent;
use strangetest\ErrorEvent;
use strangetest\SkipEvent;
use strangetest\OutputEvent;


class TestQuietOutput {

    private $logger;

    public function setup() {
        $this->logger = new Logger(\TEST_ROOT, strangetest\LOG_QUIET, new NoOutputter);
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
        $this->logger->log_pass(new PassEvent('source', __FILE__, __LINE__));
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
        $this->logger->log_error(new ErrorEvent('source', 'message', $file, $line));
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
        $this->logger->log_failure(new FailEvent('source', 'message', $file, $line));
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
        $this->logger->log_skip(new SkipEvent('source', 'message', __FILE__, __LINE__));
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
        $this->logger->log_output(new OutputEvent('source', 'message', __FILE__, __LINE__), false);
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
        $this->logger->log_output(new OutputEvent('source', 'message', $file, $line), true);
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
        $this->logger->log_pass(new PassEvent('pass1', $file, $line1));

        $line2 = __LINE__;
        $this->logger->log_output(new OutputEvent('output1', 'output 1', $file, $line2), false);

        $line3 = __LINE__;
        $this->logger->log_failure(new FailEvent('fail', 'failure', $file, $line3));

        $line4 = __LINE__;
        $this->logger->log_output(new OutputEvent('output2', 'output 2', $file, $line4), true);

        $line5 = __LINE__;
        $this->logger->log_error(new ErrorEvent('error', 'error', $file, $line5));

        $line6 = __LINE__;
        $this->logger->log_output(new OutputEvent('output3', 'output 3', $file, $line6), true);

        $line7 = __LINE__;
        $this->logger->log_skip(new SkipEvent('skip', 'skip', $file, $line7));

        $line8 = __LINE__;
        $this->logger->log_output(new OutputEvent('output4', 'output 4', $file, $line8), false);

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
        $this->logger = new Logger(\TEST_ROOT, strangetest\LOG_VERBOSE, new NoOutputter);
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
        $this->logger->log_pass(new PassEvent('source', __FILE__, __LINE__));
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
        $this->logger->log_error(new ErrorEvent('source', 'message', $file, $line));
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
        $this->logger->log_failure(new FailEvent('source', 'message', $file, $line));
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
        $this->logger->log_skip(new SkipEvent('source', 'message', $file, $line));
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
        $this->logger->log_output(new OutputEvent('source', 'message', $file, $line), false);
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
        $this->logger->log_pass(new PassEvent('pass1', $file, $line1));

        $line2 = __LINE__;
        $this->logger->log_output(new OutputEvent('output1', 'output 1', $file, $line2), false);

        $line3 = __LINE__;
        $this->logger->log_failure(new FailEvent('fail', 'failure', $file, $line3));

        $line4 = __LINE__;
        $this->logger->log_output(new OutputEvent('output2', 'output 2', $file, $line4), true);

        $line5 = __LINE__;
        $this->logger->log_error(new ErrorEvent('error', 'error', $file, $line5));

        $line6 = __LINE__;
        $this->logger->log_output(new OutputEvent('output3', 'output 3', $file, $line6), true);

        $line7 = __LINE__;
        $this->logger->log_skip(new SkipEvent('skip', 'skip', $file, $line7));

        $line8 = __LINE__;
        $this->logger->log_output(new OutputEvent('output4', 'output 4', $file, $line8), false);

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
