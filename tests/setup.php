<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.


const TEST_ROOT = __DIR__;

class ExpectedException extends \Exception {}
class UnexpectedException extends \Exception {}


final class NoOutputter extends strangetest\struct implements strangetest\LogOutputter
{
    public function output_pass() {}

    public function output_failure() {}

    public function output_error() {}

    public function output_skip() {}

    public function output_output() {}
}


function assert_log(array $log, strangetest\Logger $logger) {
    $expected = array(
        strangetest\EVENT_PASS => 0,
        strangetest\EVENT_FAIL => 0,
        strangetest\EVENT_ERROR => 0,
        strangetest\EVENT_SKIP => 0,
        strangetest\EVENT_OUTPUT => 0,
        'events' => array(),
    );
    foreach ($log as $i => $entry) {
        $expected[$i] = $entry;
    }

    $actual = $logger->get_log();
    $actual = array(
        strangetest\EVENT_PASS => $actual->pass_count(),
        strangetest\EVENT_FAIL => $actual->failure_count(),
        strangetest\EVENT_ERROR => $actual->error_count(),
        strangetest\EVENT_SKIP => $actual->skip_count(),
        strangetest\EVENT_OUTPUT => $actual->output_count(),
        'events' => $actual->get_events(),
    );
    for ($i = 0, $c = count($actual['events']); $i < $c; ++$i) {
        list($type, $source, $reason) = $actual['events'][$i];
        if ($reason instanceof \Throwable
            // @bc 5.6 Check if $reason is instance of Exception
            || $reason instanceof \Exception)
        {
            $actual['events'][$i][2] = $reason->getMessage();
        }
    }
    strangetest\assert_identical($expected, $actual);
}


function assert_events($expected, strangetest\Logger $logger) {
    $actual = $logger->get_log()->events;
    foreach ($actual as $i => $event)
    {
        if ($event instanceof strangetest\PassEvent)
        {
            $type = strangetest\EVENT_PASS;
            $source = $event->source;
            $reason = null;
        }
        elseif ($event instanceof strangetest\FailEvent)
        {
            $type = strangetest\EVENT_FAIL;
            $source = $event->source;
            $reason = $event->reason;
        }
        elseif ($event instanceof strangetest\ErrorEvent)
        {
            $type = strangetest\EVENT_ERROR;
            $source = $event->source;
            $reason = $event->reason;
        }
        elseif ($event instanceof strangetest\SkipEvent)
        {
            $type = strangetest\EVENT_SKIP;
            $source = $event->source;
            $reason = $event->reason;
        }
        else
        {
            \assert($event instanceof strangetest\OutputEvent);
            $type = strangetest\EVENT_OUTPUT;
            $source = $event->source;
            $reason = $event->output;
        }

        $actual[$i] = array($type, $source, $reason);
    }
    strangetest\assert_identical($actual, $expected);
}


function assert_report($expected, strangetest\Logger $logger) {
    $log = $logger->get_log();
    $log->seconds_elapsed = 1;
    $log->megabytes_used = 1;
    strangetest\output_log($log);
    strangetest\assert_identical(ob_get_contents(), $expected);
}


// helper functions

function make_test($spec)
{
    if ($spec)
    {
        $result = make_directory_test($spec);
    }
    else
    {
        $result = false;
    }
    return $result;
}


function make_directory_test($spec, $parent = null)
{
    $default = array(
        'setup' => null,
        'teardown' => null,
        'group' => 0,
    );
    $spec = \array_merge($default, $spec);
    \assert(isset($spec['directory']));

    $dir = new strangetest\DirectoryTest;
    $dir->name = $parent ? "{$parent->name}{$spec['directory']}" : $spec['directory'];
    $dir->run_group_id = $spec['group'];
    $dir->setup = new \ReflectionFunction($spec['setup']);
    $dir->teardown = new \ReflectionFunction($spec['teardown']);

    foreach ($spec['tests'] as $test)
    {
        if (isset($test['file']))
        {
            make_file_test($test, $dir);
        }
        elseif (isset($test['directory']))
        {
            make_directory_test($test, $dir);
        }
        else
        {
            throw new \Exception(
                \sprintf(
                    'Unexpected directory test spec: %s',
                    strangetest\format_variable($test))
            );
        }
    }

    \assert(!isset($spec['runs']));

    if ($parent)
    {
        $parent->tests[$dir->name] = $dir;
    }

    return $dir;
}


function make_file_test($spec, $dir)
{
    $default = array(
        'group' => 0,
        'setup_file' => null,
        'teardown_file' => null,
        'setup_function' => null,
        'teardown_function' => null,
    );
    $spec = \array_merge($default, $spec);
    \assert(isset($spec['file']));

    $file = new strangetest\FileTest;
    $file->name = "{$dir->name}{$spec['file']}";
    $file->run_group_id = $spec['group'];
    $file->setup_file = $spec['setup_file'];
    $file->teardown_file = $spec['teardown_file'];
    $file->setup_function = $spec['setup_function'];
    $file->teardown_function = $spec['teardown_function'];

    $dir->tests[$file->name] = $file;
    foreach ($spec['tests'] as $test)
    {
        if (isset($test['function']))
        {
            make_function_test($test, $file);
        }
        elseif (isset($test['class']))
        {
            make_class_test($test, $file);
        }
        else
        {
            throw new \Exception(
                \sprintf(
                    'Unexpected file test spec: %s',
                    strangetest\format_variable($test))
            );
        }
    }

    \assert(!isset($spec['runs']));
}


function make_class_test($spec, $file)
{
    $defaults = array(
        'group' => 0,
        'namespace' => '',
        'setup_object' => null,
        'teardown_object' => null,
        'setup_method' => null,
        'teardown_method' => null,
    );
    $spec = \array_merge($defaults, $spec);
    \assert(isset($spec['class']));

    $namespace = $spec['namespace'] ? "{$spec['namespace']}\\" : '';

    $class = new strangetest\ClassTest;
    $class->run_group_id = $spec['group'];
    $class->test = new \ReflectionClass("{$namespace}{$spec['class']}");

    if ($spec['setup_object'])
    {
        $class->setup_object = new \ReflectionMethod($spec['class'], $spec['setup_object']);
    }
    if ($spec['teardown_object'])
    {
        $class->teardown_object = new \ReflectionMethod($spec['class'], $spec['teardown_object']);
    }

    if ($spec['setup_method'])
    {
        $class->setup_method = new \ReflectionMethod($spec['class'], $spec['setup_method']);
    }
    if ($spec['teardown_method'])
    {
        $class->teardown_method = new \ReflectionMethod($spec['class'], $spec['teardown_method']);
    }

    $file->tests["class {$class->test->name}"] = $class;
    foreach ($spec['tests'] as $test)
    {
        make_method_test($test, $class);
    }
}


function make_method_test($spec, $class)
{
    $defaults = array(
        'group' => 0,
    );
    $spec = \array_merge($defaults, $spec);
    \assert(isset($spec['function']));

    $test = new strangetest\MethodTest;
    $test->name = "{$class->test->name}::{$spec['function']}";
    $test->run_group_id = $spec['group'];
    $test->test = new \ReflectionMethod($class->test->name, $spec['function']);
    $class->tests[$spec['function']] = $test;
}


function make_function_test($spec, $file)
{
    $defaults = array(
        'group' => 0,
        'namespace' => '',
    );
    $spec = \array_merge($defaults, $spec);
    \assert(isset($spec['function']));

    $namespace = $spec['namespace'] ? "{$spec['namespace']}\\" : '';

    $test = new strangetest\FunctionTest;
    $test->name = "{$namespace}{$spec['function']}";
    $test->run_group_id = $spec['group'];
    $test->test = new \ReflectionFunction($test->name);
    $file->tests["function $test->name"] = $test;
}
