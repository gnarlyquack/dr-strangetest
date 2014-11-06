<?php

class TestReporter {
    private $reporter;

    public function setup() {
        $this->reporter = new easytest\Reporter();
    }

    // helper assertions

    private function assert_report($expected) {
        $expected = array_merge(
            ['Tests' => 0, 'Errors' => [], 'Failures' => []],
            $expected
        );
        $actual = $this->reporter->get_report();
        assert('$expected === $actual');
    }

    // tests

    public function test_blank_report() {
        $this->assert_report([]);
    }

    public function test_report_success() {
        $this->reporter->report_success();
        $this->assert_report(['Tests' => 1]);
    }

    public function test_report_error() {
        $this->reporter->report_error('source', 'message');
        $this->assert_report(['Errors' => [['source', 'message']]]);
    }

    public function test_report_failure() {
        $this->reporter->report_failure('source', 'message');
        $this->assert_report([
            'Tests' => 1,
            'Failures' => [['source', 'message']]
        ]);
    }
}
