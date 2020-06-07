<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


final class BufferingLogger implements Logger {
    public $logger;
    public $buffer;
    public $error = false;
    public $ob_level_current;
    public $ob_level_start;
    public $queued = array();


    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }


    public function log_pass($source) {
        if ($this->buffer) {
            $this->queued[] = array(namespace\EVENT_PASS, $source, null);
        }
        else {
            $this->logger->log_pass($source);
        }
    }


    public function log_failure($source, $reason) {
        if ($this->buffer) {
            $this->queued[] = array(namespace\EVENT_FAIL, $source, $reason);
            $this->error = true;
        }
        else {
            $this->logger->log_failure($source, $reason);
        }
    }


    public function log_error($source, $reason) {
        if ($this->buffer) {
            $this->queued[] = array(namespace\EVENT_ERROR, $source, $reason);
            $this->error = true;
        }
        else {
            $this->logger->log_error($source, $reason);
        }
    }


    public function log_skip($source, $reason, $during_error = false) {
        if ($this->buffer) {
            $this->queued[] = array(namespace\EVENT_SKIP, $source, $reason);
        }
        else {
            $this->logger->log_skip($source, $reason, $during_error);
        }
    }


    public function log_output($source, $output, $during_error) {
        if ($this->buffer) {
            $this->queued[] = array(namespace\EVENT_OUTPUT, $source, $output);
        }
        else {
            $this->logger->log_output($source, $output, $during_error);
        }
    }
}



function start_buffering(BufferingLogger $logger, $source) {
    if ($logger->buffer) {
        namespace\_reset_buffer($logger);
        $logger->buffer = $source;
    }
    else {
        \ob_start();
        $logger->buffer = $source;
        $logger->ob_level_start = $logger->ob_level_current = \ob_get_level();
    }
}


function end_buffering(BufferingLogger $logger) {
    $buffers = array();
    for ($level = \ob_get_level();
         $level > $logger->ob_level_start;
         --$level)
    {
        $logger->log_error(
            $logger->buffer,
            \sprintf(
                "An output buffer was started but never deleted.\nBuffer contents were: %s",
                namespace\_format_buffer(\ob_get_clean())
            )
        );
    }

    if ($level < $logger->ob_level_start) {
        $logger->log_error(
            $logger->buffer,
            "EasyTest's output buffer was deleted! Please start (and delete) your own\noutput buffer(s) using PHP's output control functions."
        );
    }
    else {
        $output = \ob_get_clean();
        if (\strlen($output)) {
            $logger->log_output(
                $logger->buffer,
                namespace\_format_buffer($output),
                $logger->error
            );
        }
    }

    foreach ($logger->queued as $event) {
        list($type, $source, $reason) = $event;
        switch ($type) {
            case namespace\EVENT_PASS:
                $logger->logger->log_pass($source);
                break;

            case namespace\EVENT_FAIL:
                $logger->logger->log_failure($source, $reason);
                break;

            case namespace\EVENT_ERROR:
                $logger->logger->log_error($source, $reason);
                break;

            case namespace\EVENT_SKIP:
                $logger->logger->log_skip($source, $reason, $logger->error);
                break;

            case namespace\EVENT_OUTPUT:
                $logger->logger->log_output($source, $reason, $logger->error);
                break;
        }
    }

    $logger->buffer = null;
    $logger->error = false;
    $logger->queued = array();
}


function _reset_buffer(BufferingLogger $logger) {
    $level = \ob_get_level();

    if ($level < $logger->ob_level_start) {
        $logger->log_error(
            $logger->buffer,
            "EasyTest's output buffer was deleted! Please start (and delete) your own\noutput buffer(s) using PHP's output control functions."
        );
        \ob_start();
        $logger->ob_level_start = $logger->ob_level_current = \ob_get_level();
        return;
    }

    $buffers = array();
    if ($level > $logger->ob_level_start) {
        // Somebody else is doing their own output buffering, so we don't
        // want to mess it with. If their buffering started before we last
        // reset our own buffer then we don't need to do anything. But if
        // their buffering started after we last reset our own buffer, then
        // we want to pop off the other buffer(s), handle our own, and then
        // put the other buffer(s) back.
        if ($logger->ob_level_current > $logger->ob_level_start) {
            return;
        }

        $logger->ob_level_current = $level;
        while ($level-- > $logger->ob_level_start) {
            $buffers[] = \ob_get_clean();
        }
    }

    $output = \ob_get_contents();
    \ob_clean();
    if (\strlen($output)) {
        $logger->log_output(
            $logger->buffer,
            namespace\_format_buffer($output),
            $logger->error
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
    \assert($logger->ob_level_current === \ob_get_level());
}


function _format_buffer($buffer) {
    if ('' === $buffer) {
        return '[the output buffer was empty]';
    }
    if ('' === \trim($buffer)) {
        return \sprintf(
            "[the output buffer contained only whitespace]\n%s",
            \var_export($buffer, true)
        );
    }
    return $buffer;
}
