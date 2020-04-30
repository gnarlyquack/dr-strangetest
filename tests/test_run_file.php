<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestRunFile {
    private $root;


    public function setup_object() {
        $this->root = \sprintf(
            '%1$s%2$ssample_files%2$stest_file%2$s',
            __DIR__, \DIRECTORY_SEPARATOR
        );
    }

    public function setup() {
        $this->logger = new easytest\BasicLogger(true);
    }


    private function assert_run($file, $expected){
        $filepath = "{$this->root}$file";
        $logger = new easytest\BasicLogger(false);
        easytest\_discover_file(new easytest\State, $logger, $filepath, null);

        assert_log($expected, $logger);
    }


    public function test_runs_function_tests() {
        $this->assert_run(
            'functions.php',
            [
                easytest\LOG_EVENT_PASS => 2,
            ]
        );
    }


    public function test_sets_up_and_tears_down_functions() {
        $this->assert_run(
            'function_fixtures.php',
            [
                easytest\LOG_EVENT_PASS => 2,
            ]
        );
    }


    public function test_sets_up_and_tears_down_file() {
        $this->assert_run(
            'file_fixtures.php',
            [
                easytest\LOG_EVENT_PASS => 3,
            ]
        );
    }


    public function test_runs_file_and_function_fixtures() {
        $this->assert_run(
            'file_and_function_fixtures.php',
            [
                easytest\LOG_EVENT_PASS => 3,
            ]
        );
    }

}
