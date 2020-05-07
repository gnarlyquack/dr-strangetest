<?php
/*
 * EasyTest
 * Copyright (c) 2014 Karl Nack
 *
 * This file is subject to the license terms in the LICENSE file found in the
 * top-level directory of this distribution. No part of this project,
 * including this file, may be copied, modified, propagated, or distributed
 * except according to the terms contained in the LICENSE file.
 */


class ExpectedException extends \Exception {}
class UnexpectedException extends \Exception {}



class StubReporter implements easytest\IReporter {
    private $report;
    private $blank_report;

    public function __construct($header = null, $quiet=null) {
        $this->report = $this->blank_report = [
            'Tests' => 0,
            'Errors' => [],
            'Failures' => [],
            'Skips' => [],
            'Output' => [],
        ];
    }

    public function report_success() {
        ++$this->report['Tests'];
    }

    public function report_error($source, $message) {
        if ($message instanceof Exception) {
            $message = $message->getMessage();
        }
        $this->report['Errors'][] = [$source, $message];
    }

    public function report_failure($source, $message) {
        $this->report['Failures'][] = [$source, $message->getMessage()];
    }

    public function report_skip($source, $message) {
        $this->report['Skips'][] = [$source, $message->getMessage()];
    }

    public function buffer($source, callable $callback) {
        ob_start();

        $result = $callback();

        if ($output = trim(ob_get_clean())) {
            $this->report['Output'][] = [$source, $output];
        }
        return $result;
    }

    public function render_report() {}

    public function assert_report($expected) {
        $expected = array_merge(
            $this->blank_report,
            $expected
        );
        $actual = $this->report;
        easytest\assert_identical($expected, $actual);
    }
}
