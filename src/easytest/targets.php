<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


const _TARGET_CLASS    = '--class=';
const _TARGET_FUNCTION = '--function=';
const _TARGET_PATH     = '--path=';
\define('easytest\\_TARGET_CLASS_LEN', \strlen(namespace\_TARGET_CLASS));
\define('easytest\\_TARGET_FUNCTION_LEN', \strlen(namespace\_TARGET_FUNCTION));
\define('easytest\\_TARGET_PATH_LEN', \strlen(namespace\_TARGET_PATH));


interface Target {
    public function name();
    public function subtargets();
}


final class _Target extends struct implements Target {
    public $name;
    public $subtargets = array();

    public function name() {
        return $this->name;
    }

    public function subtargets() {
        return $this->subtargets;
    }
}


function process_user_targets(array $args, &$errors) {
    if (!$args) {
        $args[] = \getcwd();
    }
    $errors = array();

    $root = $file = $subtarget_count = null;
    $targets = array();
    foreach ($args as $arg) {
        if (0 === \substr_compare($arg, namespace\_TARGET_CLASS,
                                  0, namespace\_TARGET_CLASS_LEN, true)
        ) {
            $class = \substr($arg, namespace\_TARGET_CLASS_LEN);
            if (!\strlen($class)) {
                $errors[] = "Test target '$arg' requires a class name";
                continue;
            }
            if (!isset($file)) {
                $errors[] = "Test target '$arg' must be specified for a file";
                continue;
            }
            if ($subtarget_count) {
                namespace\_process_class_target($targets[$file]->subtargets, $class, $errors);
            }
        }
        elseif (0 === \substr_compare($arg, namespace\_TARGET_FUNCTION,
                                      0, namespace\_TARGET_FUNCTION_LEN, true)
        ) {
            $function = \substr($arg, namespace\_TARGET_FUNCTION_LEN);
            if (!\strlen($function)) {
                $errors[] = "Test target '$arg' requires a function name";
                continue;
            }
            if (!isset($file)) {
                $errors[] = "Test target '$arg' must be specified for a file";
                continue;
            }
            if ($subtarget_count) {
                namespace\_process_function_target($targets[$file]->subtargets, $function, $errors);
            }
        }
        else {
            if ($subtarget_count > 0
                && \count($targets[$file]->subtargets) === $subtarget_count
            ) {
                $targets[$file]->subtargets = array();
            }
            $file = $subtarget_count = null;

            $path = $arg;
            if (0 === \substr_compare($path, namespace\_TARGET_PATH,
                                      0, namespace\_TARGET_PATH_LEN, true)
            ) {
                $path = \substr($path, namespace\_TARGET_PATH_LEN);
                if (!\strlen($path)) {
                    $errors[] = "Test target '$arg' requires a directory or file name";
                    continue;
                }
            }
            list($file, $subtarget_count)
                = namespace\_process_path_target($targets, $root, $path, $errors);
        }
    }
    if ($subtarget_count > 0
        && \count($targets[$file]->subtargets) === $subtarget_count
    ) {
        $targets[$file]->subtargets = array();
    }

    if ($errors) {
        return array(null, null);
    }

    $keys = \array_keys($targets);
    \sort($keys, \SORT_STRING);
    $key = \current($keys);
    while ($key !== false) {
        if (\is_dir($key)) {
            $keylen = \strlen($key);
            $next = \next($keys);
            while (
                $next !== false
                && 0 === \substr_compare($next, $key, 0, $keylen)
            ) {
                unset($targets[$next]);
                $next = \next($keys);
            }
            $key = $next;
        }
        else {
            $key = \next($keys);
        }
    }
    return array($root, $targets);
}


function _process_class_target(array &$targets, $target, array &$errors) {
    $split = \strpos($target, '::');
    if (false === $split) {
        $classes = $target;
        $methods = null;
    }
    else {
        $classes = \substr($target, 0, $split);
        $methods = \substr($target, $split + 2);
        if (!\strlen($classes)) {
            $errors[] = "Test target '--class=$target' requires a class name";
            return;
        }
        if (!\strlen($methods)) {
            $errors[] = "Test target '--class=$target' requires a method name";
            return;
        }
    }

    $classes = \explode(',', $classes);
    $max_index = \count($classes) - 1;
    foreach ($classes as $index => $class) {
        // functions and classes with identical names can coexist!
        if (!\strlen($class)) {
            $errors[] = "Test target '--class=$target' is missing one or more class names";
            return;
        }

        $class = "class $class";
        if (!isset($targets[$class])) {
            $targets[$class] = new _Target($class);
            $subtarget_count = -1;
        }
        elseif ($index < $max_index) {
            $targets[$class]->subtargets = array();
        }
        else {
            $subtarget_count = \count($targets[$class]->subtargets);
        }
    }

    if ($methods && $subtarget_count) {
        $targets = &$targets[$class]->subtargets;
        foreach (\explode(',', $methods) as $method) {
            if (!\strlen($method)) {
                $errors[] = "Test target '--class=$target' is missing one or more method names";
                return;
            }

            if (!isset($targets[$method])) {
                $targets[$method] = new _Target($method);
            }
        }
    }
    elseif ($subtarget_count > 0) {
        $targets[$class]->subtargets = array();
    }
}


function _process_function_target(array &$targets, $functions, array &$errors) {
    foreach (\explode(',', $functions) as $function) {
        if (!\strlen($function)) {
            $errors[] = "Test target '--function=$functions' is missing one or more function names";
            return;
        }

        // functions and classes with identical names can coexist!
        $function = "function $function";
        if (!isset($targets[$function])) {
            $targets[$function] = new _Target($function);
        }
    }
}


function _process_path_target(array &$targets, &$root, $path, array &$errors) {
    $realpath = \realpath($path);
    $file = null;
    if (!$realpath) {
        $errors[] = "Path '$path' does not exist";
        return array(null, null);
    }

    if (isset($targets[$realpath])) {
        if (\is_dir($realpath)) {
            return array(null, null);
        }
        return array($realpath, \count($targets[$realpath]->subtargets));
    }

    if (!isset($root)) {
        $root = namespace\_determine_test_root($realpath);
    }
    elseif (0 !== \substr_compare($realpath, $root, 0, \strlen($root))) {
        $errors[] = "Path '$path' is outside the test root directory '$root'";
        return array(null, null);
    }

    if (\is_dir($realpath)) {
        $realpath .= \DIRECTORY_SEPARATOR;
    }
    else {
        $file = $realpath;
    }

    $targets[$realpath] = new _Target($realpath);
    return array($file, -1);
}


function _determine_test_root($path) {
    // The test root directory is the first directory above $path whose
    // case-insensitive name does not begin with 'test'. If $path is a
    // directory, this could be $path itself. This is done to ensure that
    // directory fixtures are properly discovered when testing individual
    // subpaths within a test suite; discovery will begin at the root directory
    // and descend towards the specified path.
    if (!\is_dir($path)) {
        $path = \dirname($path);
    }

    while (0 === \substr_compare(\basename($path), 'test', 0, 4, true)) {
        $path = \dirname($path);
    }
    return $path . \DIRECTORY_SEPARATOR;
}


function find_directory_targets(Logger $logger, DirectoryTest $test, array $targets) {
    $error = false;
    $result = array();
    $current = null;
    $testnamelen = \strlen($test->name);
    foreach ($targets as $target) {
        if ($target->name === $test->name) {
            \assert(!$result);
            \assert(!$error);
            break;
        }

        $i = \strpos($target->name, \DIRECTORY_SEPARATOR, $testnamelen);
        if (false === $i) {
            $childpath = $target->name;
        }
        else {
            $childpath = \substr($target->name, 0, $i + 1);
        }

        if (!isset($test->tests[$childpath])) {
            $error = true;
            $logger->log_error(
                $target->name,
                'This path is not a valid test ' . (\is_dir($target->name) ? 'directory' : 'file')
            );
        }
        elseif ($childpath === $target->name) {
            $result[] = $target;
            $current = null;
        }
        else {
            if (!isset($current) || $current->name !== $childpath) {
                $current = new _Target($childpath);
                $result[] = $current;
            }
            $current->subtargets[] = $target;
        }
    }
    return array($error, $result);
}


function find_file_targets(Logger $logger, FileTest $test, array $targets) {
    $error = false;
    $result = array();
    foreach ($targets as $target) {
        if (isset($test->tests[$target->name])) {
            $result[] = $target;
        }
        else {
            $error = true;
            $logger->log_error(
                $target->name,
                "This identifier is not a valid test in {$test->name}"
            );
        }
    }
    return array($error, $result);
}


function find_class_targets(Logger $logger, ClassTest $test, array $targets) {
    $error = false;
    $result = array();
    foreach ($targets as $target) {
        if (\method_exists($test->name, $target->name)) {
            $result[] = $target->name;
        }
        else {
            $error = true;
            $logger->log_error(
                $target->name,
                "This identifier is not a valid test method in class {$test->name}"
            );
        }
    }
    return array($error, $result);
}


function build_targets_from_dependencies(array $dependencies) {
    $targets = array();
    $current_file = $current_class = null;
    foreach ($dependencies as $dependency) {
        if (!isset($current_file) || $current_file->name !== $dependency->file) {
            $current_class = null;
            $current_file = new _Target($dependency->file);
            $targets[] = $current_file;
        }
        if ($dependency->class) {
            $class = "class {$dependency->class}";
            if (!isset($current_class) || $current_class->name !== $class) {
                $current_class = new _Target($class);
                $current_file->subtargets[] = $current_class;
            }
            $current_class->subtargets[] = new _Target($dependency->function);
        }
        else {
            $current_class = null;
            $function = "function {$dependency->function}";
            $current_file->subtargets[] = new _Target($function);
        }
    }
    return $targets;
}
