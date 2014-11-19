<?php

class StubReporter implements easytest\IReporter {
    private $report;
    private $blank_report;

    public function __construct() {
        $this->report = $this->blank_report = [
            'Tests' => 0,
            'Errors' => [],
            'Failures' => [],
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

    public function render_report() {}

    public function assert_report($expected) {
        $expected = array_merge(
            $this->blank_report,
            $expected
        );
        $actual = $this->report;
        assert('$expected === $actual');
    }
}
