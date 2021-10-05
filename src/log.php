<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


final class BasicLog implements Log {
    /** @var float */
    public $megabytes_used;

    /** @var float */
    public $seconds_elapsed;

    /** @var int[] $count */
    private $count;

    /** @var array{int, string, string|\Throwable|null}[] */
    private $events;


    /**
     * @param int[] $count
     * @param array{int, string, string|\Throwable|null}[] $events
     */
    public function __construct(array $count, array $events) {
        $this->count = $count;
        $this->events = $events;
    }


    public function pass_count() {
        return $this->count[namespace\EVENT_PASS];
    }


    public function failure_count() {
        return $this->count[namespace\EVENT_FAIL];
    }


    public function error_count() {
        return $this->count[namespace\EVENT_ERROR];
    }


    public function skip_count() {
        return $this->count[namespace\EVENT_SKIP];
    }


    public function output_count() {
        return $this->count[namespace\EVENT_OUTPUT];
    }


    public function seconds_elapsed() {
        return $this->seconds_elapsed;
    }


    public function memory_used() {
        return $this->megabytes_used;
    }


    public function get_events() {
        // This is safe because PHP arrays are copy-on-write
        return $this->events;
    }
}



final class BasicLogger implements Logger {
    /** @var int[] */
    private $count = array(
        namespace\EVENT_PASS   => 0,
        namespace\EVENT_ERROR  => 0,
        namespace\EVENT_FAIL   => 0,
        namespace\EVENT_SKIP   => 0,
        namespace\EVENT_OUTPUT => 0,
    );

    /** @var array{int, string, string|\Throwable|null}[] */
    private $events = array();

    /** @var bool */
    private $verbose;


    /**
     * @param bool $verbose
     */
    public function __construct($verbose) {
        $this->verbose = $verbose;
    }


    public function log_pass($source) {
        ++$this->count[namespace\EVENT_PASS];
        if ($this->verbose & namespace\LOG_PASS) {
            $this->events[] = array(namespace\EVENT_PASS, $source, null);
        }
    }


    public function log_failure($source, $reason) {
        ++$this->count[namespace\EVENT_FAIL];
        $this->events[] = array(namespace\EVENT_FAIL, $source, $reason);
    }


    public function log_error($source, $reason) {
        ++$this->count[namespace\EVENT_ERROR];
        $this->events[] = array(namespace\EVENT_ERROR, $source, $reason);
    }


    public function log_skip($source, $reason, $during_error = false) {
        ++$this->count[namespace\EVENT_SKIP];
        if ($during_error) {
            \assert($reason instanceof \Throwable);
            $reason = new Skip('Although this test was skipped, there was also an error', $reason);
            $this->events[] = array(namespace\EVENT_SKIP, $source, $reason);
        }
        elseif ($this->verbose & namespace\LOG_SKIP) {
            $this->events[] = array(namespace\EVENT_SKIP, $source, $reason);
        }
    }


    public function log_output($source, $reason, $during_error = false) {
        ++$this->count[namespace\EVENT_OUTPUT];
        if (($this->verbose & namespace\LOG_OUTPUT) || $during_error) {
            $this->events[] = array(namespace\EVENT_OUTPUT, $source, $reason);
        }
    }


    /**
     * @return BasicLog
     */
    public function get_log() {
        return new BasicLog($this->count, $this->events);
    }
}
