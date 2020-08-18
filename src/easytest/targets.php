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


interface Target {}


final class _Target extends struct implements Target {
    public $name;
    public $subtargets = array();
}


function process_user_targets(array $args, &$errors) {
    if (!$args) {
        $args[] = \getcwd();
    }
    $errors = array();

    $root = $file = null;
    $targets = array();
    foreach ($args as $arg) {
        if (0 === \substr_compare($arg, namespace\_TARGET_CLASS,
                                  0, namespace\_TARGET_CLASS_LEN, true)
        ) {
            $class = \substr($arg, namespace\_TARGET_CLASS_LEN);
            if (!$class) {
                $errors[] = "Class test target '$arg' requires a class name";
                continue;
            }
            if (!$file) {
                $errors[] = "Class test target '$arg' must be specified for a file";
                continue;
            }
            namespace\_process_class_target($targets[$file]->subtargets, $class, $errors);
        }
        elseif (0 === \substr_compare($arg, namespace\_TARGET_FUNCTION,
                                      0, namespace\_TARGET_FUNCTION_LEN, true)
        ) {
            $function = \substr($arg, namespace\_TARGET_FUNCTION_LEN);
            if (!$function) {
                $errors[] = "Function test target '$arg' requires a function name";
                continue;
            }
            if (!$file) {
                $errors[] = "Function test target '$arg' must be specified for a file";
                continue;
            }
            namespace\_process_function_target($targets[$file]->subtargets, $function, $errors);
        }
        else {
            $path = $arg;
            if (0 === \substr_compare($path, namespace\_TARGET_PATH,
                                      0, namespace\_TARGET_PATH_LEN, true)
            ) {
                $path = \substr($path, namespace\_TARGET_PATH_LEN);
                if (!$path) {
                    $errors[] = "Path test target '$arg' requires a directory or file name";
                    continue;
                }
            }
            $file = namespace\_process_path_target($targets, $root, $path, $errors);
        }
    }

    if ($errors) {
        return array(null, null);
    }
    return array($root, $targets);
}


function _process_class_target(array &$targets, $target, &$errors) {
    $split = \strpos($target, '::');
    if (false === $split) {
        $methods = null;
        $classes = $target;
    }
    else {
        $methods = \substr($target, $split + 2);
        $classes = \substr($target, 0, $split);
        if (!$methods) {
            $errors[] = "Class test target '$target' has no methods specified after '::'";
            return;
        }
        if (!$classes) {
            $errors[] = "Class test target '$target' has no classes specified before '::'";
            return;
        }
    }

    foreach (\explode(',', $classes) as $class) {
        // functions and classes with identical names can coexist!
        $class = "class $class";
        $targets[$class] = new _Target($class);
    }
    if ($methods) {
        foreach (\explode(',', $methods) as $method) {
            $targets[$class]->subtargets[$method] = new _Target($method);
        }
    }
}


function _process_function_target(array &$targets, $functions, &$errors) {
    foreach (\explode(',', $functions) as $function) {
        // functions and classes with identical names can coexist!
        $function = "function $function";
        $targets[$function] = new _Target($function);
    }
}


function _process_path_target(array &$targets, &$root, $path, &$errors) {
    $realpath = \realpath($path);
    $file = null;
    if (!$realpath) {
        $errors[] = "Path '$path' does not exist";
        return $file;
    }

    if (!$root) {
        $root = namespace\_determine_root($realpath);
    }

    if (\is_dir($realpath)) {
        $realpath .= \DIRECTORY_SEPARATOR;
    }
    else {
        $file = $realpath;
    }

    $targets[$realpath] = new _Target($realpath);
    return $file;
}


function _determine_root($path) {
    // Determine a path's root test directory
    //
    // The root test directory is the highest directory above $path whose
    // case-insensitive name begins with 'test' or, if $path is a directory,
    // $path itself or, if $path is file, the dirname of $path. This is done to
    // ensure that directory fixtures are properly discovered when testing
    // individual subpaths within a test suite; discovery will begin at the
    // root directory and descend towards the specified path.
    if (\is_dir($path)) {
        $root = $parent = $path;
    }
    else {
        $root = $parent = \dirname($path);
    }

    while (0 === \substr_compare(\basename($parent), 'test', 0, 4, true)) {
        $root = $parent;
        $parent = \dirname($parent);
    }
    return $root . \DIRECTORY_SEPARATOR;
}
