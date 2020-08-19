<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\process_user_targets;

use easytest;
use easytest\Context;
use easytest\Target;


// helper functions

function set_cwd(Context $context, $dir) {
    $cwd = \getcwd();
    $context->teardown(function() use ($cwd) { \chdir($cwd); });
    \chdir($dir);
}


function target_to_array(Target $target) {
    $result = (array)$target;
    foreach ($result['subtargets'] as $key => $value) {
        $result['subtargets'][$key] = target_to_array($value);
    }
    return $result;
}


// helper assertions

function assert_targets(
    Context $context, array $actual, array $actual_errors,
    $expected_root, $expected_targets, array $expected_errors
) {
    $context->assert_identical(
        $expected_root,
        $actual[0],
        'Wrong test root'
    );

    $targets = $actual[1];
    if ($targets) {
        foreach ($targets as $key => $value) {
            $targets[$key] = namespace\target_to_array($value);
        }
    }
    $context->assert_identical(
        $expected_targets,
        $targets,
        'Incorrect targets'
    );

    $context->assert_identical(
        $expected_errors,
        $actual_errors,
        'Unexpected errors'
    );
}


// tests

function test_uses_cwd_as_default_target(Context $context) {
    namespace\set_cwd($context, __DIR__);
    $actual = easytest\process_user_targets(array(), $errors);

    $root = \dirname(\dirname(__DIR__)) . \DIRECTORY_SEPARATOR;
    $targets = array(
        __DIR__ . \DIRECTORY_SEPARATOR => array(
            'name' => __DIR__ . \DIRECTORY_SEPARATOR,
            'subtargets' => array()
        )
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_processes_paths_as_path_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array('test1.php', 'test2.php', 'test_dir');

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array();
    foreach ($args as $arg) {
        $target = "$root$arg";
        if (\is_dir($target)) {
            $target .= \DIRECTORY_SEPARATOR;
        }
        $targets[$target] = array('name' => $target, 'subtargets' => array());
    }
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_processes_path_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $paths = array('test1.php', 'test2.php', 'test_dir');
    $args = array();
    foreach ($paths as $path) {
        $args[] = "--path=$path";
    }

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array();
    foreach ($paths as $path) {
        $target = "$root$path";
        if (\is_dir($target)) {
            $target .= \DIRECTORY_SEPARATOR;
        }
        $targets[$target] = array('name' => $target, 'subtargets' => array());
    }
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_processes_function_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array('test1.php', '--function=foo,bar');

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(
                'function foo' => array(
                    'name' => 'function foo',
                    'subtargets' => array(),
                ),
                'function bar' => array(
                    'name' => 'function bar',
                    'subtargets' => array(),
                ),
            ),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_processes_class_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array('test1.php', '--class=foo,bar');

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(
                'class foo' => array(
                    'name' => 'class foo',
                    'subtargets' => array(),
                ),
                'class bar' => array(
                    'name' => 'class bar',
                    'subtargets' => array(),
                ),
            ),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_processes_method_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array('test1.php', '--class=foo,bar::one,two');

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(
                'class foo' => array(
                    'name' => 'class foo',
                    'subtargets' => array(),
                ),
                'class bar' => array(
                    'name' => 'class bar',
                    'subtargets' => array(
                        'one' => array(
                            'name' => 'one',
                            'subtargets' => array(),
                        ),
                        'two' => array(
                            'name' => 'two',
                            'subtargets' => array(),
                        ),
                    ),
                ),
            ),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_eliminates_duplicate_function_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array('test1.php', '--function=one,two', '--function=two,three');

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(
                'function one' => array(
                    'name' => 'function one',
                    'subtargets' => array(),
                ),
                'function two' => array(
                    'name' => 'function two',
                    'subtargets' => array(),
                ),
                'function three' => array(
                    'name' => 'function three',
                    'subtargets' => array(),
                ),
            ),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_eliminates_duplicate_method_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array(
        'test1.php',
        '--class=foo,bar::one,two',
        '--class=foo::one,two',
        '--class=bar::two,three',
    );

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(
                'class foo' => array(
                    'name' => 'class foo',
                    'subtargets' => array(),
                ),
                'class bar' => array(
                    'name' => 'class bar',
                    'subtargets' => array(
                        'one' => array(
                            'name' => 'one',
                            'subtargets' => array(),
                        ),
                        'two' => array(
                            'name' => 'two',
                            'subtargets' => array(),
                        ),
                        'three' => array(
                            'name' => 'three',
                            'subtargets' => array(),
                        ),
                    ),
                ),
            ),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_overrides_method_targets_with_class_target(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array(
        'test1.php',
        '--class=foo::one,two',
        '--class=bar::one,two',
        '--class=cat::one,two',
        '--class=foo,bar',
        '--class=cat',
    );

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(
                'class foo' => array(
                    'name' => 'class foo',
                    'subtargets' => array(),
                ),
                'class bar' => array(
                    'name' => 'class bar',
                    'subtargets' => array(),
                ),
                'class cat' => array(
                    'name' => 'class cat',
                    'subtargets' => array(),
                ),
            ),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_eliminates_duplicate_targets_in_file(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array(
        'test1.php',
        'test2.php',
        'test1.php',
        '--class=foo,bar::one,two',
        '--function=one,two',
    );

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(),
        ),
        "{$root}test2.php" => array(
            'name' => "{$root}test2.php",
            'subtargets' => array(),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_overrides_targets_in_file_with_file_target(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array(
        'test1.php',
        '--class=foo::one,two',
        '--class=bar::one,two',
        '--class=cat::one,two',
        '--class=foo,bar',
        '--class=cat',
        'test2.php',
        'test1.php',
    );

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(),
        ),
        "{$root}test2.php" => array(
            'name' => "{$root}test2.php",
            'subtargets' => array(),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_eliminates_duplicate_path_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array(
        'test2.php',
        'test1.php',
        'test_dir/test_subdir',
        'test_dir1',
        'test_dir',
        'test2.php',
        'test_dir1/test2.php',
        'test_dir1/test1.php',
    );

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test2.php" => array(
            'name' => "{$root}test2.php",
            'subtargets' => array(),
        ),
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(),
        ),
        "{$root}test_dir1/" => array(
            'name' => "{$root}test_dir1/",
            'subtargets' => array(),
        ),
        "{$root}test_dir/" => array(
            'name' => "{$root}test_dir/",
            'subtargets' => array(),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_reports_error_for_nonexistent_paths(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array(
        'foo.php',
        'test1.php',
        'foo_dir',
    );

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    namespace\assert_targets($context, $actual, $errors, null, null, array(
        "Path 'foo.php' does not exist",
        "Path 'foo_dir' does not exist",
    ));
}


function test_reports_error_for_missing_function_name(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array(
        'test1.php',
        '--function=',
        '--function=one,,two',
        '--function=,,,',
    );

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    namespace\assert_targets($context, $actual, $errors, null, null, array(
        "Test target '--function=' requires a function name",
        "Test target '--function=one,,two' is missing one or more function names",
        "Test target '--function=,,,' is missing one or more function names",
    ));
}


function test_reports_error_for_missing_class_name(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array(
        'test1.php',
        '--class=',
        '--class=one,,two',
        '--class=foo,bar,::one,,two,',
        '--class=::one,two',
        '--class=,,,',
    );

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    namespace\assert_targets($context, $actual, $errors, null, null, array(
        "Test target '--class=' requires a class name",
        "Test target '--class=one,,two' is missing one or more class names",
        "Test target '--class=foo,bar,::one,,two,' is missing one or more class names",
        "Test target '--class=::one,two' requires a class name",
        "Test target '--class=,,,' is missing one or more class names",
    ));
}


function test_reports_error_for_missing_method_name(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array(
        'test1.php',
        '--class=one,two::',
        '--class=foo,bar::one,,two',
        '--class=foo,bar::,,,',
    );

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    namespace\assert_targets($context, $actual, $errors, null, null, array(
        "Test target '--class=one,two::' requires a method name",
        "Test target '--class=foo,bar::one,,two' is missing one or more method names",
        "Test target '--class=foo,bar::,,,' is missing one or more method names",
    ));
}


function test_determines_correct_test_root_from_directory(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array('test_dir/test_subdir', 'test_dir1');

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array();
    foreach ($args as $arg) {
        $target = $root . $arg . \DIRECTORY_SEPARATOR;
        $targets[$target] = array('name' => $target, 'subtargets' => array());
    }
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_determines_correct_test_root_from_file(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array('test_dir1/test2.php', 'test1.php');

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array();
    foreach ($args as $arg) {
        $target = $root . $arg;
        $targets[$target] = array('name' => $target, 'subtargets' => array());
    }
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_reports_error_for_path_outside_test_root(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array('test1.php', __FILE__);

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    namespace\assert_targets($context, $actual, $errors, null, null, array(
        \sprintf("Path '%s' is outside the test root directory '$root'", __FILE__),
    ));
}
