<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


// These functions constitute the API to generate Dr. Strangetest exceptions

/**
 * @api
 * @param string $reason
 * @return never
 * @throws Skip
 */
function skip($reason)
{
    throw new Skip($reason);
}


/**
 * @api
 * @param string $reason
 * @return never
 * @throws Failure
 */
function fail($reason)
{
    throw new Failure($reason);
}



// Implementation


// @bc 5.6 Extend Failure from Exception
if (\version_compare(\PHP_VERSION, '7.0', '<'))
{
    /**
     * @api
     */
    final class Failure extends \Exception {

        /**
         * @param string $message
         */
        public function __construct($message)
        {
            parent::__construct($message);
            $result = namespace\_find_client_call_site();
            if ($result)
            {
                list($this->file, $this->line, $this->trace) = $result;
            }
        }

        /**
         * @return string
         */
        public function __toString()
        {
            if (!$this->string)
            {
                $this->string = namespace\_format_exception_string(
                    "%s\n\nin %s on line %s",
                    $this->message, $this->file, $this->line, $this->trace);
            }
            return $this->string;
        }

        /** @var string */
        private $string;

        /**
         * @var array{function: string,
         *            line?: int,
         *            file?:string,
         *            class?: class-string,
         *            object?: object,
         *            type?: string,
         *            args?: mixed[]}[]
         */
        private $trace;
    }

}
else
{
    /**
     * @api
     */
    final class Failure extends \AssertionError {

        /**
         * @param string $message
         */
        public function __construct($message)
        {
            parent::__construct($message);
            $result = namespace\_find_client_call_site();
            if ($result)
            {
                list($this->file, $this->line, $this->trace) = $result;
            }
        }

        /**
         * @return string
         */
        public function __toString()
        {
            if (!$this->string)
            {
                $this->string = namespace\_format_exception_string(
                    "%s\n\nin %s on line %s",
                    $this->message, $this->file, $this->line, $this->trace);
            }
            return $this->string;
        }

        /** @var string */
        private $string;

        /**
         * @var array{function: string,
         *            line?: int,
         *            file?:string,
         *            class?: class-string,
         *            object?: object,
         *            type?: string,
         *            args?: mixed[]}[]
         */
        private $trace;
    }

}


/**
 * @api
 */
final class Skip extends \Exception {

    /**
     * @param string $message
     */
    public function __construct($message, \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $result = namespace\_find_client_call_site();
        if ($result)
        {
            list($this->file, $this->line, $this->trace) = $result;
        }
    }


    /**
     * @return string
     */
    public function __toString()
    {
        if (!$this->string)
        {
            $prev = $this->getPrevious();
            if ($prev)
            {
                $message = $prev->getMessage() . "\n{$this->message}";
                $file = $prev->getFile();
                $line = $prev->getLine();
                $trace = $prev->getTrace();
            }
            else
            {
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

    /** @var string */
    private $string;

    /**
     * @var array{function: string,
     *            line?: int,
     *            file?:string,
     *            class?: class-string,
     *            object?: object,
     *            type?: string,
     *            args?: mixed[]}[]
     */
    private $trace;
}


final class InvalidCodePath extends \Exception {}



/**
 * @return ?array{string, int, array{function: string,
 *                                   line?: int,
 *                                   file?:string,
 *                                   class?: class-string,
 *                                   object?: object,
 *                                   type?: string,
 *                                   args?: mixed[]}[]}
 */
function _find_client_call_site()
{
    // Find the first call in a backtrace that's outside of Dr. Strangetest
    // @bc 5.3 Pass false for debug_backtrace() $option parameter
    $trace = \defined('DEBUG_BACKTRACE_IGNORE_ARGS')
           ? \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS)
           : \debug_backtrace(0);
    foreach ($trace as $i => $frame)
    {
        // Apparently there's no file if we were thrown from the error
        // handler
        if (isset($frame['file'])
            && __DIR__ !== \dirname($frame['file']))
        {
            break;
        }
    }

    if (isset($i, $frame['file'], $frame['line']))
    {
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


/**
 * @param string $format
 * @param string $message
 * @param string $file
 * @param int $line
 * @param array{function: string,
 *              line?: int,
 *              file?:string,
 *              class?: class-string,
 *              object?: object,
 *              type?: string,
 *              args?: mixed[]}[] $trace
 * @return string
 */
function _format_exception_string($format, $message, $file, $line, $trace)
{
    $string = \sprintf($format, $message, $file, $line);

    // Create a backtrace excluding calls made within Dr. Strangetest
    $buffer = array();
    foreach ($trace as $frame)
    {
        // @bc 5.3 Functions have no file if executed in call_user_func()
        // call_user_func() specifically was giving us a (fatal) error, but
        // possibly we should guard against this regardless?
        if (!isset($frame['file']))
        {
            continue;
        }
        if ( __DIR__ === \dirname($frame['file']))
        {
            continue;
        }
        if ('strangetest\\main' === $frame['function'])
        {
            break;
        }

        $callee = $frame['function'];
        if (isset($frame['class']))
        {
            \assert(isset($frame['type']));
            $callee = "{$frame['class']}{$frame['type']}{$callee}";
        }

        \assert(isset($frame['line']));
        $buffer[] = "{$frame['file']}({$frame['line']}): {$callee}()";
    }
    if ($buffer)
    {
        $string = \sprintf(
            "%s\n\nCalled from:\n%s",
            $string, \implode("\n", $buffer)
        );
    }

    return $string;
}
