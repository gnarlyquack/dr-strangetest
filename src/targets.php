<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


const _TARGET_CLASS     = '--class=';
const _TARGET_FUNCTION  = '--function=';
const _TARGET_PATH      = '--path=';
\define('strangetest\\_TARGET_CLASS_LEN', \strlen(namespace\_TARGET_CLASS));
\define('strangetest\\_TARGET_FUNCTION_LEN', \strlen(namespace\_TARGET_FUNCTION));
\define('strangetest\\_TARGET_PATH_LEN', \strlen(namespace\_TARGET_PATH));


interface Target {
    /**
     * @return string
     */
    public function name();

    /**
     * @return Target[]
     */
    public function subtargets();
}


final class _Target extends struct implements Target {
    /** @var string */
    public $name;
    /** @var Target[] */
    public $subtargets = array();


    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * @return Target[]
     */
    public function subtargets()
    {
        return $this->subtargets;
    }
}


/**
 * @param string $root
 * @param string[] $args
 * @param string[] $errors
 * @return ?Target[]
 */
function process_user_targets(Logger $logger, $root, array $args, &$errors)
{
    \assert(\DIRECTORY_SEPARATOR === \substr($root, -1));
    namespace\_parse_targets($logger, $args, $root);

    if (!$args)
    {
        $args[] = $root;
    }
    $errors = array();

    $file = $subtarget_count = null;
    $targets = array();
    foreach ($args as $arg)
    {
        if (0 === \substr_compare($arg, namespace\_TARGET_CLASS,
                                  0, namespace\_TARGET_CLASS_LEN, true))
        {
            $class = \substr($arg, namespace\_TARGET_CLASS_LEN);
            if (!\strlen($class))
            {
                $errors[] = "Test target '$arg' requires a class name";
                continue;
            }
            if (!isset($file))
            {
                $errors[] = "Test target '$arg' must be specified for a file";
                continue;
            }
            if ($subtarget_count)
            {
                namespace\_process_class_target($targets[$file]->subtargets, $class, $errors);
            }
        }
        elseif (0 === \substr_compare($arg, namespace\_TARGET_FUNCTION,
                                      0, namespace\_TARGET_FUNCTION_LEN, true))
        {
            $function = \substr($arg, namespace\_TARGET_FUNCTION_LEN);
            if (!\strlen($function))
            {
                $errors[] = "Test target '$arg' requires a function name";
                continue;
            }
            if (!isset($file))
            {
                $errors[] = "Test target '$arg' must be specified for a file";
                continue;
            }
            if ($subtarget_count)
            {
                namespace\_process_function_target($targets[$file]->subtargets, $function, $errors);
            }
        }
        else
        {
            if ($subtarget_count > 0
                && \count($targets[$file]->subtargets) === $subtarget_count)
            {
                $targets[$file]->subtargets = array();
            }
            $file = $subtarget_count = null;

            $path = $arg;
            if (0 === \substr_compare($path, namespace\_TARGET_PATH,
                                      0, namespace\_TARGET_PATH_LEN, true))
            {
                $path = \substr($path, namespace\_TARGET_PATH_LEN);
                if (!\strlen($path))
                {
                    $errors[] = "Test target '$arg' requires a directory or file name";
                    continue;
                }
            }
            list($file, $subtarget_count)
                = namespace\_process_path_target($targets, $root, $path, $errors);
        }
    }
    if (($subtarget_count > 0)
        && (\count($targets[$file]->subtargets) === $subtarget_count))
    {
        $targets[$file]->subtargets = array();
    }

    if ($errors)
    {
        return null;
    }

    $keys = \array_keys($targets);
    \sort($keys, \SORT_STRING);
    $key = \current($keys);
    while ($key !== false)
    {
        \assert(\is_string($key));
        if (\is_dir($key))
        {
            $keylen = \strlen($key);
            $next = \next($keys);
            while (
                ($next !== false)
                && (0 === \substr_compare((string)$next, $key, 0, $keylen)))
            {
                unset($targets[$next]);
                $next = \next($keys);
            }
            $key = $next;
        }
        else
        {
            $key = \next($keys);
        }
    }
    return $targets;
}


final class _TargetIterator
{
    /** @var string[] */
    public $args;

    /** @var int */
    public $count;

    /** @var int */
    public $index = 0;

    /**
     * @param string[] $args
     */
    public function __construct(array $args)
    {
        $this->args = $args;
        $this->count = \count($args);
    }
}


/**
 * @param string[] $args
 * @param string $root
 * @return void
 */
function _parse_targets(Logger $logger, $args, $root)
{
    $valid = true;
    $targets = array();
    $iter = new _TargetIterator($args);
    while ($iter->index < $iter->count)
    {
        $target = _parse_path_target($logger, $iter, $root);
        if ($target)
        {
            $targets[] = $target;
        }
        else
        {
            $valid = false;
        }
    }

    if ($valid)
    {
        // do something here
    }
}


/**
 * @param string $root
 * @return ?_Target
 */
function _parse_path_target(Logger $logger, _TargetIterator $iter, $root)
{
    $valid = false;
    $target = $leaf = null;
    $dir = false;
    $arg = $iter->args[$iter->index++];

    $path = (\DIRECTORY_SEPARATOR === \substr($arg, 0, 1)) ? $arg : ($root . $arg);
    $realpath = \realpath($path);

    if (false === $realpath)
    {
        $logger->log_error($path, 'This path does not exist');
    }
    else
    {
        if (\is_dir($realpath))
        {
            $dir = true;
            \assert(\DIRECTORY_SEPARATOR !== \substr($realpath, -1));
            $realpath .= \DIRECTORY_SEPARATOR;
        }

        if (0 === \substr_compare($realpath, $root, 0, \strlen($root)))
        {
            $valid = true;
            $target = $leaf = new _Target($root);
            $pos = \strpos($path, \DIRECTORY_SEPARATOR, \strlen($root));
            while (false !== $pos)
            {
                ++$pos;
                $temp = new _Target(\substr($realpath, 0, $pos));
                $leaf->subtargets[] = $temp;
                $leaf = $temp;
                $pos = \strpos($realpath, \DIRECTORY_SEPARATOR, $pos);
            }
            if ($leaf->name !== $realpath)
            {
                $temp = new _Target($realpath);
                $leaf->subtargets[] = $temp;
                $leaf = $temp;
            }
        }
        else
        {
            $logger->log_error($path, "This path is outside the test root directory '$root'");
        }
    }

    while (
        ($iter->index < $iter->count)
        && \preg_match('~^--(class|function)=(.*)$~', $iter->args[$iter->index], $matches))
    {
        if ($leaf)
        {
            if ('class' === $matches[1])
            {
                if ($dir)
                {
                    $logger->log_error(
                        $iter->args[$iter->index++],
                        'Functions can only be specified for a file');
                    $valid = false;
                }
                elseif (!namespace\_parse_class_target($logger, $iter, $leaf))
                {
                    $valid = false;
                }
            }
            elseif ('function' === $matches[1])
            {
                if ($dir)
                {
                    $logger->log_error(
                        $iter->args[$iter->index++],
                        'Classes can only be specified for a file');
                    $valid = false;
                }
                elseif (!namespace\_parse_function_target($logger, $iter, $leaf))
                {
                    $valid = false;
                }
            }
            else
            {
                throw new \Exception("Unexpected path specifier: {$matches[1]}");
            }
        }
        else
        {
            // Consume specifiers that apply to an invalid path
            ++$iter->index;
        }
    }

    \assert(!$valid || $target);
    return $valid ? $target : null;
}


/**
 *
 * @return bool
 */
function _parse_function_target(Logger $logger, _TargetIterator $iter, _Target $parent)
{
    $valid = true;
    $arg = $iter->args[$iter->index++];
    $functions = \substr($arg, namespace\_TARGET_FUNCTION_LEN);
    foreach (\explode(',', $functions) as $function)
    {
        if (\strlen($function))
        {
            $function = "function $function";
            $parent->subtargets[] = new _Target($function);
        }
        else
        {
            $logger->log_error($arg, 'This specifier is missing one or more function names');
            $valid = false;
            break;
        }
    }

    return $valid;
}


/**
 * @return bool
 */
function _parse_class_target(Logger $logger, _TargetIterator $iter, _Target $parent)
{
    $valid = true;
    $arg = $iter->args[$iter->index++];
    $classes = \substr($arg, namespace\_TARGET_CLASS_LEN);
    foreach (\explode(';', $classes) as $class)
    {
        $split = \strpos($class, '::');
        if (false === $split)
        {
            $methods = array();
        }
        else
        {
            $methods = \explode(',', \substr($class, $split + 2));
            $class = \substr($class, 0, $split);
        }

        if (\strlen($class))
        {
            $class = "class $class";
            $target = new _Target($class);
            $parent->subtargets[] = $target;

            foreach ($methods as $method)
            {
                if (\strlen($method))
                {
                    $target->subtargets[] = new _Target($method);
                }
                else
                {
                    $logger->log_error(
                        $arg,
                        'This specifier is missing one or more method names');
                    $valid = false;
                    break 2;
                }
            }
        }
        else
        {
            $logger->log_error($arg, 'This specifier is missing one or more class names');
            $valid = false;
            break;
        }
    }

    return $valid;
}


/**
 * @param _Target[] $targets
 * @param string $target
 * @param string[] $errors
 * @return void
 */
function _process_class_target(array &$targets, $target, array &$errors)
{
    $classes = \explode(';', $target);
    foreach ($classes as $class)
    {
        $methods = null;
        $split = \strpos($class, '::');
        if (false !== $split)
        {
            $methods = \explode(',', \substr($class, $split + 2));
            $class = \substr($class, 0, $split);
        }

        if (!\strlen($class))
        {
            // @todo Report errors for all class names in a class target
            // We could provide a more detailed error message here, e.g.,
            // "target $i has no class name". We could also potentially report
            // errors for invalid targets while continuing to run tests for
            // valid ones.
            $errors[] = "Test target '--class=$target' is missing one or more class names";
            return;
        }

        $class = "class $class";
        if (!isset($targets[$class]))
        {
            $targets[$class] = new _Target($class);
            $subtarget_count = -1;
        }
        else
        {
            $subtarget_count = \count($targets[$class]->subtargets);
        }

        if ($subtarget_count)
        {
            if ($methods)
            {
                foreach ($methods as $method)
                {
                    if (!\strlen($method))
                    {
                        // @todo Report errors for all method names in a class target
                        // We could provide a more detailed error message here,
                        // e.g., "target method $i for class $class has no
                        // name". We could also potentially report errors for
                        // invalid targets while continuing to run tests for
                        // valid ones.
                        $errors[] = "Test target '--class=$target' is missing one or more method names";
                        return;
                    }

                    if (!isset($targets[$class]->subtargets[$method]))
                    {
                        $targets[$class]->subtargets[$method] = new _Target($method);
                    }
                }
            }
            else
            {
                $targets[$class]->subtargets = array();
            }
        }
    }
}


/**
 * @param _Target[] $targets
 * @param string $functions
 * @param string[] $errors
 * @return void
 */
function _process_function_target(array &$targets, $functions, array &$errors)
{
    foreach (\explode(',', $functions) as $function)
    {
        if (!\strlen($function))
        {
            $errors[] = "Test target '--function=$functions' is missing one or more function names";
            return;
        }

        // functions and classes with identical names can coexist!
        $function = "function $function";
        if (!isset($targets[$function]))
        {
            $targets[$function] = new _Target($function);
        }
    }
}


/**
 * @param _Target[] $targets
 * @param string $root
 * @param string $path
 * @param string[] $errors
 * @return array{?string, ?int}
 */
function _process_path_target(array &$targets, $root, $path, array &$errors)
{
    if (\DIRECTORY_SEPARATOR !== \substr($path, 0, 1))
    {
        $path = $root . $path;
    }
    $realpath = \realpath($path);
    $file = null;
    if (!$realpath)
    {
        $errors[] = "Path '$path' does not exist";
        return array(null, null);
    }
    elseif (\is_dir($realpath))
    {
        \assert(\DIRECTORY_SEPARATOR !== \substr($realpath, -1));
        $realpath .= \DIRECTORY_SEPARATOR;
    }

    if (isset($targets[$realpath]))
    {
        if (\is_dir($realpath))
        {
            return array(null, null);
        }
        return array($realpath, \count($targets[$realpath]->subtargets));
    }

    if (0 !== \substr_compare($realpath, $root, 0, \strlen($root)))
    {
        $errors[] = "Path '$path' is outside the test root directory '$root'";
        return array(null, null);
    }

    if (!\is_dir($realpath))
    {
        $file = $realpath;
    }

    $targets[$realpath] = new _Target($realpath);
    return array($file, -1);
}


/**
 * @param Target[] $targets
 * @return array{bool, Target[]}
 */
function find_directory_targets(Logger $logger, DirectoryTest $test, array $targets)
{
    $error = false;
    $result = array();
    $current = null;
    $testnamelen = \strlen($test->name);
    foreach ($targets as $target)
    {
        if ($target->name() === $test->name)
        {
            \assert(!$result);
            \assert(!$error);
            break;
        }

        $i = \strpos($target->name(), \DIRECTORY_SEPARATOR, $testnamelen);
        if (false === $i)
        {
            $childpath = $target->name();
        }
        else
        {
            $childpath = \substr($target->name(), 0, $i + 1);
        }

        if (!isset($test->tests[$childpath]))
        {
            $error = true;
            $logger->log_error(
                $target->name(),
                'This path is not a valid test ' . (\is_dir($target->name()) ? 'directory' : 'file')
            );
        }
        elseif ($childpath === $target->name())
        {
            $result[] = $target;
            $current = null;
        }
        else
        {
            if (!isset($current) || $current->name !== $childpath)
            {
                $current = new _Target($childpath);
                $result[] = $current;
            }
            $current->subtargets[] = $target;
        }
    }
    return array($error, $result);
}


/**
 * @param Target[] $targets
 * @return array{bool, Target[]}
 */
function find_file_targets(Logger $logger, FileTest $test, array $targets)
{
    $error = false;
    $result = array();
    foreach ($targets as $target)
    {
        if (isset($test->tests[$target->name()]))
        {
            $result[] = $target;
        }
        else
        {
            $error = true;
            $logger->log_error(
                $target->name(),
                "This identifier is not a valid test in {$test->name}"
            );
        }
    }
    return array($error, $result);
}


/**
 * @param Target[] $targets
 * @return array{bool, string[]}
 */
function find_class_targets(Logger $logger, ClassTest $test, array $targets)
{
    $error = false;
    $result = array();
    foreach ($targets as $target)
    {
        if (\method_exists($test->name, $target->name()))
        {
            $result[] = $target->name();
        }
        else
        {
            $error = true;
            $logger->log_error(
                $target->name(),
                "This identifier is not a valid test method in class {$test->name}"
            );
        }
    }
    return array($error, $result);
}


/**
 * @param Dependency[] $dependencies
 * @return Target[]
 */
function build_targets_from_dependencies(array $dependencies)
{
    $targets = array();
    $current_file = $current_class = null;
    foreach ($dependencies as $dependency)
    {
        if (!isset($current_file) || $current_file->name !== $dependency->file)
        {
            $current_class = null;
            $current_file = new _Target($dependency->file);
            $targets[] = $current_file;
        }
        if ($dependency->class)
        {
            $class = "class {$dependency->class}";
            if (!isset($current_class) || $current_class->name !== $class)
            {
                $current_class = new _Target($class);
                $current_file->subtargets[] = $current_class;
            }
            $current_class->subtargets[] = new _Target($dependency->function);
        }
        else
        {
            $current_class = null;
            $function = "function {$dependency->function}";
            $current_file->subtargets[] = new _Target($function);
        }
    }
    return $targets;
}
