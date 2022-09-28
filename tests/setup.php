<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.


use strangetest\ClassInfo;
use strangetest\ClassTest;
use strangetest\FunctionInfo;
use strangetest\MethodInfo;


const TEST_ROOT = __DIR__;

const EVENT_PASS   = 1;
const EVENT_FAIL   = 2;
const EVENT_ERROR  = 3;
const EVENT_SKIP   = 4;
const EVENT_OUTPUT = 5;


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
        \EVENT_PASS => 0,
        \EVENT_FAIL => 0,
        \EVENT_ERROR => 0,
        \EVENT_SKIP => 0,
        \EVENT_OUTPUT => 0,
        'events' => array(),
    );
    foreach ($log as $i => $entry) {
        $expected[$i] = $entry;
    }

    $actual = $logger->get_log();
    $actual = array(
        \EVENT_PASS => $actual->pass_count(),
        \EVENT_FAIL => $actual->failure_count(),
        \EVENT_ERROR => $actual->error_count(),
        \EVENT_SKIP => $actual->skip_count(),
        \EVENT_OUTPUT => $actual->output_count(),
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
            $type = \EVENT_PASS;
            $source = $event->source;
            $reason = null;
        }
        elseif ($event instanceof strangetest\FailEvent)
        {
            $type = \EVENT_FAIL;
            $source = $event->source;
            $reason = $event->reason;
        }
        elseif ($event instanceof strangetest\ErrorEvent)
        {
            $type = \EVENT_ERROR;
            $source = $event->source;
            $reason = $event->reason;
        }
        elseif ($event instanceof strangetest\SkipEvent)
        {
            $type = \EVENT_SKIP;
            $source = $event->source;
            $reason = $event->reason;
        }
        else
        {
            \assert($event instanceof strangetest\OutputEvent);
            $type = \EVENT_OUTPUT;
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
    );
    $spec = \array_merge($default, $spec);
    \assert(isset($spec['directory']));
    \assert(!isset($spec['group']));

    $dir = new strangetest\DirectoryTest;
    $dir->name = $parent ? "{$parent->name}{$spec['directory']}" : $spec['directory'];
    $dir->setup = _function_from_reflection(
        new \ReflectionFunction($spec['setup']));
    $dir->teardown = _function_from_reflection(
        new \ReflectionFunction($spec['teardown']));

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
        'setup_file' => null,
        'teardown_file' => null,
        'setup_function' => null,
        'teardown_function' => null,
    );
    $spec = \array_merge($default, $spec);
    \assert(isset($spec['file']));
    \assert(!isset($spec['group']));

    $file = new strangetest\FileTest;
    $file->name = "{$dir->name}{$spec['file']}";
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
    $r = new \ReflectionClass("{$namespace}{$spec['class']}");
    $class->test = new ClassInfo;
    $class->test->name = $r->name;
    $class->test->namespace = $namespace;
    $class->test->file = $r->getFileName();
    $class->test->line = $r->getStartLine();

    if ($spec['setup_object'])
    {
        $class->setup_object = _method_from_reflection(
            $class->test,
            new \ReflectionMethod($spec['class'], $spec['setup_object']));
    }
    if ($spec['teardown_object'])
    {
        $class->teardown_object = _method_from_reflection(
            $class->test,
            new \ReflectionMethod($spec['class'], $spec['teardown_object']));
    }

    if ($spec['setup_method'])
    {
        $class->setup_method = _method_from_reflection(
            $class->test,
            new \ReflectionMethod($spec['class'], $spec['setup_method']));
    }
    if ($spec['teardown_method'])
    {
        $class->teardown_method = _method_from_reflection(
            $class->test,
            new \ReflectionMethod($spec['class'], $spec['teardown_method']));
    }

    $index = 'class ' . strangetest\normalize_identifier($class->test->name);
    $file->tests[$index] = $class;
    foreach ($spec['tests'] as $test)
    {
        make_method_test($test, $class);
    }
}


function make_method_test($spec, ClassTest $class)
{
    \assert(isset($spec['function']));

    $test = new strangetest\MethodTest;
    $test->name = "{$class->test->name}::{$spec['function']}";
    $test->test = _method_from_reflection(
        $class->test,
        new \ReflectionMethod($class->test->name, $spec['function']));

    $index = strangetest\normalize_identifier($spec['function']);
    $class->tests[$index] = $test;
}


function make_function_test($spec, $file)
{
    $defaults = array(
        'namespace' => '',
    );
    $spec = \array_merge($defaults, $spec);
    \assert(isset($spec['function']));
    \assert(!isset($spec['group']));

    $namespace = $spec['namespace'] ? "{$spec['namespace']}\\" : '';

    $test = new strangetest\FunctionTest;
    $test->name = "{$namespace}{$spec['function']}";
    $test->test = _function_from_reflection(new \ReflectionFunction($test->name));

    $index = 'function ' . strangetest\normalize_identifier($test->name);
    $file->tests[$index] = $test;
}


function _method_from_reflection(ClassInfo $class, \ReflectionMethod $method)
{
    $result = new MethodInfo;
    $result->name = $method->name;
    $result->class = $class;
    $result->file = $method->getFileName();
    $result->line = $method->getStartLine();

    return $result;
}


function _function_from_reflection(\ReflectionFunction $function)
{
    $result = new FunctionInfo;
    $result->name = $function->name;
    $result->namespace = $function->getNamespaceName();
    $result->short_name = $function->getShortName();
    $result->file = $function->getFileName();
    $result->line = $function->getStartLine();

    if (\strlen($result->namespace))
    {
        $result->namespace .= '\\';
    }

    return $result;
}
