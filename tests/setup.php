<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class ExpectedException extends \Exception {}
class UnexpectedException extends \Exception {}


function assert_log(array $log, strangetest\BasicLogger $logger) {
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
            // #BC(5.6): Check if $reason is instance of Exception
            || $reason instanceof \Exception)
        {
            $actual['events'][$i][2] = $reason->getMessage();
        }
    }
    strangetest\assert_identical($expected, $actual);
}


function assert_events($expected, strangetest\BasicLogger $logger) {
    $actual = $logger->get_log()->get_events();
    foreach ($actual as $i => $event) {
        list($type, $source, $reason) = $event;
        // #BC(5.6): Check if reason is instance of Exception
        if ($reason instanceof \Throwable
            || $reason instanceof \Exception)
        {
            $reason = $reason->getMessage();
        }

        $actual[$i] = array($type, $source, $reason);
    }
    strangetest\assert_identical($expected, $actual);
}


function assert_report($expected, strangetest\BasicLogger $logger) {
    $log = $logger->get_log();
    $log->seconds_elapsed = 1;
    $log->megabytes_used = 1;
    strangetest\output_log($log);
    strangetest\assert_identical($expected, ob_get_contents());
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
    $dir->group = $spec['group'];
    $dir->setup = $spec['setup'];
    $dir->teardown = $spec['teardown'];

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
    if ($parent)
    {
        $parent->tests[$dir->name] = $dir;
    }
    else
    {
        return $dir;
    }
}


function make_file_test($spec, $dir)
{
    $default = array(
        'setup' => null,
        'teardown' => null,
        'group' => 0,
    );
    $spec = \array_merge($default, $spec);
    \assert(isset($spec['file']));

    $file = new strangetest\FileTest;
    $file->name = "{$dir->name}{$spec['file']}";
    $file->group = $spec['group'];
    $file->setup = $spec['setup'];
    $file->teardown = $spec['teardown'];

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
}


function make_class_test($spec, $file)
{
    $defaults = array(
        'group' => 0,
        'namespace' => '',
        'setup' => null,
        'teardown' => null,
    );
    $spec = \array_merge($defaults, $spec);
    \assert(isset($spec['class']));

    $class = new strangetest\ClassTest;
    $class->file = $file->name;
    $class->group = $spec['group'];
    $class->namespace = $spec['namespace'] ? "{$spec['namespace']}\\" : '';
    $class->name = "{$class->namespace}{$spec['class']}";
    $class->setup = $spec['setup'];
    $class->teardown = $spec['teardown'];

    $file->tests["class {$class->name}"] = $class;
    foreach ($spec['tests'] as $test)
    {
        make_method_test($test, $class);
    }
}


function make_method_test($spec, $class)
{
    $defaults = array(
        'group' => 0,
        'setup' => null,
        'teardown' => null,
    );
    $spec = \array_merge($defaults, $spec);
    \assert(isset($spec['function']));

    $test = new strangetest\FunctionTest;
    $test->file = $class->file;
    $test->group = $spec['group'];
    $test->namespace = $class->namespace;
    $test->class = $class->name;
    $test->function = $spec['function'];
    $test->name = "{$class->name}::{$spec['function']}";
    $test->setup_name = $spec['setup'];
    $test->teardown_name = $spec['teardown'];

    $class->tests[$test->function] = $test;
}


function make_function_test($spec, $file)
{
    $defaults = array(
        'group' => 0,
        'namespace' => '',
        'class' => null,
        'setup' => null,
        'teardown' => null,
    );
    $spec = \array_merge($defaults, $spec);
    \assert(isset($spec['function']));

    $test = new strangetest\FunctionTest;
    $test->file = $file->name;
    $test->group = $spec['group'];
    $test->namespace = $spec['namespace'] ? "{$spec['namespace']}\\" : '';
    $test->name = $test->function = $test->test = "{$test->namespace}{$spec['function']}";
    if ($spec['setup'])
    {
        $test->setup_name = $spec['setup'];
        $test->setup
            = $spec['namespace']
            ? "{$spec['namespace']}//{$spec['setup']}"
            : $spec['setup'];
    }
    if ($spec['teardown'])
    {
        $test->teardown_name = $spec['teardown'];
        $test->teardown
            = $spec['namespace']
            ? "{$spec['namespace']}//{$spec['teardown']}"
            : $spec['teardown'];
    }

    $file->tests["function $test->name"] = $test;
}
