<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


// These function comprise the API to generate EasyTest-specific exceptions

function skip($reason) {
    throw new Skip($reason);
}


function fail($reason) {
    throw new Failure($reason);
}



// Implementation


// #BC(5.6): Extend Failure from Exception
if (\version_compare(\PHP_VERSION, '7.0', '<')) {

    final class Failure extends \Exception {

        public function __construct($message) {
            parent::__construct($message);
            $result = namespace\_find_client_call_site();
            if ($result) {
                list($this->file, $this->line, $this->trace) = $result;
            }
        }


        public function __toString() {
            if (!$this->string) {
                $this->string = namespace\_format_exception_string(
                    "%s\n\nin %s on line %s",
                    $this->message, $this->file, $this->line, $this->trace
                );
            }
            return $this->string;
        }

        private $string;
        private $trace;
    }

}
else {

    final class Failure extends \AssertionError {

        public function __construct($message) {
            parent::__construct($message);
            $result = namespace\_find_client_call_site();
            if ($result) {
                list($this->file, $this->line, $this->trace) = $result;
            }
        }


        public function __toString() {
            if (!$this->string) {
                $this->string = namespace\_format_exception_string(
                    "%s\n\nin %s on line %s",
                    $this->message, $this->file, $this->line, $this->trace
                );
            }
            return $this->string;
        }

        private $string;
        private $trace;
    }

}



final class Skip extends \Exception {

    public function __construct($message, Skip $previous = null) {
        parent::__construct($message, 0, $previous);
        $result = namespace\_find_client_call_site();
        if ($result) {
            list($this->file, $this->line, $this->trace) = $result;
        }
    }


    public function __toString() {
        if (!$this->string) {
            $prev = $this->getPrevious();
            if ($prev) {
                $message = "$prev->message\n$this->message";
                $file = $prev->file;
                $line = $prev->line;
                $trace = $prev->trace;
            }
            else {
                $message = $this->message;
                $file = $this->file;
                $line = $this->line;
                $trace = $this->trace;
            }
            $this->string = namespace\_format_exception_string(
                "%s\nin %s on line %s",
                $message, $file, $line, $trace
            );
        }
        return $this->string;
    }

    private $string;
    private $trace;
}



function _find_client_call_site() {
    // Find the first call in a backtrace that's outside of easytest
    // #BC(5.3): Pass false for debug_backtrace() $option parameter
    $trace = \defined('DEBUG_BACKTRACE_IGNORE_ARGS')
           ? \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS)
           : \debug_backtrace(false);
    foreach ($trace as $i => $frame) {
        // Apparently there's no file if we were thrown from the error
        // handler
        if (isset($frame['file'])
            && __DIR__ !== \dirname($frame['file']))
        {
            break;
        }
    }

    if (isset($i, $frame['file'], $frame['line'])) {
        return array(
            $frame['file'],
            $frame['line'],
            // Advance the trace index ($i) so the trace array provides a
            // backtrace from the call site
            \array_slice($trace, $i + 1),
        );
    }
    return null;
}


function _format_exception_string($format, $message, $file, $line, $trace) {
    $string = \sprintf($format, $message, $file, $line);

    // Create a backtrace excluding calls made within easytest
    $buffer = array();
    foreach ($trace as $frame) {
        // #BC(5.3): Functions have no file if executed in call_user_func()
        // call_user_func() specifically was giving us a (fatal) error, but
        // possibly we should guard against this regardless?
        if (!isset($frame['file'])) {
            continue;
        }
        if ( __DIR__ === \dirname($frame['file'])) {
            // We don't want to walk the entire call stack, because easytest's
            // entry point is probably outside the easytest directory, and we
            // don't want to erroneously show that as a client call. We need a
            // checkpoint so, once we hit it, we know we can't be in client
            // code anymore. It seems "discover_tests" is the lowest we can set
            // that checkpoint, as clients can throw exceptions in a variety of
            // places (e.g., setup fixtures) all of which are subsumed by
            // discover_tests
            if ('easytest\\discover_tests' === $frame['function']) {
                break;
            }
            continue;
        }

        $callee = $frame['function'];
        if (isset($frame['class'])) {
            $callee = "{$frame['class']}{$frame['type']}{$callee}";
        }

        $buffer[] = "{$frame['file']}({$frame['line']}): {$callee}()";
    }
    if ($buffer) {
        $string = \sprintf(
            "%s\n\nCalled from:\n%s",
            $string, \implode("\n", $buffer)
        );
    }

    return $string;
}
