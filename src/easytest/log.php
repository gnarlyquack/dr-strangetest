<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


final class BasicLog implements Log {

    public function __construct($count, $events) {
        $this->count = $count;
        $this->events = $events;
    }


    public function pass_count() {
        return $this->count[namespace\LOG_EVENT_PASS];
    }


    public function failure_count() {
        return $this->count[namespace\LOG_EVENT_FAIL];
    }


    public function error_count() {
        return $this->count[namespace\LOG_EVENT_ERROR];
    }


    public function skip_count() {
        return $this->count[namespace\LOG_EVENT_SKIP];
    }


    public function output_count() {
        return $this->count[namespace\LOG_EVENT_OUTPUT];
    }


    public function get_events() {
        // This is safe because PHP arrays are copy-on-write
        return $this->events;
    }

    private $count;
    private $events;
}



final class BasicLogger implements Logger {

    public function __construct($verbose) {
        $this->verbose = $verbose;
    }


    public function log_pass() {
        ++$this->count[namespace\LOG_EVENT_PASS];
    }


    public function log_failure($source, $reason) {
        ++$this->count[namespace\LOG_EVENT_FAIL];
        $this->events[] = [namespace\LOG_EVENT_FAIL, $source, $reason];
    }


    public function log_error($source, $reason) {
        ++$this->count[namespace\LOG_EVENT_ERROR];
        $this->events[] = [namespace\LOG_EVENT_ERROR, $source, $reason];
    }


    public function log_skip($source, $reason) {
        ++$this->count[namespace\LOG_EVENT_SKIP];
        if ($this->verbose) {
            $this->events[] = [namespace\LOG_EVENT_SKIP, $source, $reason];
        }
    }


    public function log_output($source, $reason, $during_error) {
        ++$this->count[namespace\LOG_EVENT_OUTPUT];
        if ($this->verbose || $during_error) {
            $this->events[] = [namespace\LOG_EVENT_OUTPUT, $source, $reason];
        }
    }


    public function get_log() {
        return new BasicLog($this->count, $this->events);
    }

    private $count = [
        namespace\LOG_EVENT_PASS   => 0,
        namespace\LOG_EVENT_ERROR  => 0,
        namespace\LOG_EVENT_FAIL   => 0,
        namespace\LOG_EVENT_SKIP   => 0,
        namespace\LOG_EVENT_OUTPUT => 0,
    ];
    private $events = [];
    private $verbose;
}



final class LiveUpdatingLogger implements Logger {

    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }


    public function log_pass() {
        namespace\output_pass();
        $this->logger->log_pass();
    }


    public function log_failure($source, $reason) {
        namespace\output_failure();
        $this->logger->log_failure($source, $reason);
    }


    public function log_error($source, $reason) {
        namespace\output_error();
        $this->logger->log_error($source, $reason);
    }


    public function log_skip($source, $reason) {
        namespace\output_skip();
        $this->logger->log_skip($source, $reason);
    }


    public function log_output($source, $reason, $during_error) {
        namespace\output_output();
        $this->logger->log_output($source, $reason, $during_error);
    }


    public function get_log() {
        return $this->logger->get_log();
    }

    private $logger;
}


final class BufferingLogger implements Logger {

    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }


    public function log_pass() {
        if ($this->buffer) {
            $this->queued[] = [namespace\LOG_EVENT_PASS, null];
        }
        else
        {
            $this->logger->log_pass();
        }
    }


    public function log_failure($source, $reason) {
        if ($this->buffer) {
            $this->queued[] = [namespace\LOG_EVENT_FAIL, [$source, $reason]];
            $this->buffer_error = true;
        }
        else
        {
            $this->logger->log_failure($source, $reason);
        }
    }


    public function log_error($source, $reason) {
        if ($this->buffer) {
            $this->queued[] = [namespace\LOG_EVENT_ERROR, [$source, $reason]];
            $this->buffer_error = true;
        }
        else
        {
            $this->logger->log_error($source, $reason);
        }
    }


    public function log_skip($source, $reason) {
        if ($this->buffer) {
            $this->queued[] = [namespace\LOG_EVENT_SKIP, [$source, $reason]];
        }
        else
        {
            $this->logger->log_skip($source, $reason);
        }
    }


    public function log_output($source, $output, $during_error) {
        if ($this->buffer) {
            $this->queued[] = [namespace\LOG_EVENT_OUTPUT, [$source, $output]];
        }
        else
        {
            $this->logger->log_output($source, $output, $during_error);
        }
    }


    public function get_log() {
        return $this->logger->get_log();
    }


    public function start_buffering($source) {
        if ($this->buffer) {
            // switch to buffering a new source
            $this->reset_buffer();
        }
        else {
            \ob_start();
            $this->ob_level_current = $this->ob_level_start = \ob_get_level();
        }

        $this->buffer = $source;
    }


    public function end_buffering() {
        $source = $this->buffer;
        $buffers = [];
        for ($level = \ob_get_level();
             $level > $this->ob_level_start;
             --$level)
        {
            $message = "An output buffer was started but never deleted.";
            $output = \ob_get_clean();
            if (\strlen($output)) {
                $message = \sprintf(
                    "%s\nBuffer contents were: %s",
                    $message, \var_export($output, true)
                );
            }
            else {
                $message = "$message\nThe buffer was empty.";
            }
            $this->log_error($source, $message);
        }

        $output = \ob_get_clean();
        if (\strlen($output)) {
            $this->log_output(
                $source,
                \var_export($output, true),
                $this->buffer_error
            );
        }

        $this->buffer = null;
        $buffer_error = $this->buffer_error;
        $this->buffer_error = false;
        if ($this->queued) {
            foreach ($this->queued as $event) {
                list($type, $data) = $event;
                switch ($type) {
                    case namespace\LOG_EVENT_PASS:
                        $this->log_pass();
                        break;

                    case namespace\LOG_EVENT_FAIL:
                        list($source, $reason) = $data;
                        $this->log_failure($source, $reason);
                        break;

                    case namespace\LOG_EVENT_ERROR:
                        list($source, $reason) = $data;
                        $this->log_error($source, $reason);
                        break;

                    case namespace\LOG_EVENT_SKIP:
                        list($source, $reason) = $data;
                        $this->log_skip($source, $reason);
                        break;

                    case namespace\LOG_EVENT_OUTPUT:
                        list($source, $reason) = $data;
                        $this->log_output($source, $reason, $buffer_error);
                        break;
                }
            }
            $this->queued = [];
        }
    }


    private function reset_buffer() {
        $level = \ob_get_level();
        assert($level >= $this->ob_level_start);

        $buffers = [];
        if ($level > $this->ob_level_start) {
            // Somebody else is doing their own output buffering, so we don't
            // want to mess it with. If their buffering started before we last
            // reset our own buffer then we don't need to do anything. But if
            // their buffering started after we last reset our own buffer, then
            // we want to pop off the other buffer(s), handle our own, and then
            // put the other buffer(s) back.
            if ($this->ob_level_current > $this->ob_level_start) {
                return;
            }

            $this->ob_level_current = $level;
            while ($level-- > $this->ob_level_start) {
                $buffers[] = \ob_get_clean();
            }
        }

        $output = \ob_get_contents();
        \ob_clean();
        if (\strlen($output)) {
            $this->log_output(
                $this->buffer,
                \var_export($output, true),
                $this->buffer_error
            );
        }

        while ($buffers) {
            // Output buffers stack, so the first buffer we read was the last
            // buffer that was written. To restore them in the correct order,
            // we output them in reverse order from which we read them
            $buffer = \array_pop($buffers);
            \ob_start();
            echo $buffer;
        }
        assert( $this->ob_level_current === \ob_get_level());
    }


    private $buffer;
    private $queued = [];
    private $ob_level_current;
    private $ob_level_start;
    private $buffer_error = false;
    private $logger;
}
