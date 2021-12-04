<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


const _TARGET_CLASS     = '--class=';
const _TARGET_FUNCTION  = '--function=';
\define('strangetest\\_TARGET_CLASS_LEN', \strlen(namespace\_TARGET_CLASS));
\define('strangetest\\_TARGET_FUNCTION_LEN', \strlen(namespace\_TARGET_FUNCTION));


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
    /** @var ?_Target[] */
    public $subtargets;


    /**
     * @param string $name
     * @param ?_Target[] $subtargets
     */
    public function __construct($name, $subtargets = array())
    {
        $this->name = $name;
        $this->subtargets = $subtargets;
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * @return ?_Target[]
     */
    public function subtargets()
    {
        return $this->subtargets;
    }
}


/**
 * @param string[] $args
 * @return ?Target[]
 */
function process_user_targets(Logger $logger, DirectoryTest $tests, array $args)
{
    \assert(\DIRECTORY_SEPARATOR === \substr($tests->name, -1));
    \assert(\count($args) > 0);

    $targets = namespace\_parse_targets($logger, $args, $tests->name);
    $targets = namespace\_validate_targets($logger, $tests, $targets);

    if ($targets->had_errors)
    {
        $result = null;
    }
    else
    {
        \assert(\count($targets->targets) > 0);
        $result = new _Target('');
        foreach ($targets->targets as $target)
        {
            namespace\_deduplicate_target($target, $result);
        }
        $result = $result->subtargets[$tests->name]->subtargets;
    }
    return $result;

}


final class _ParseTargetIterator
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


final class ParsedTargets
{
    /** @var _PathTarget[] */
    public $targets = array();

    /** @var bool */
    public $had_errors = false;
}


final class _PathTarget
{
    /** @var string */
    public $arg;

    /** @var int */
    public $argnum;

    /** @var string */
    public $path;

    /**
     * @var array<_PathTarget|_ClassTarget|_FunctionTarget>
     * */
    public $targets = array();

    /**
     * @param string $arg
     * @param int $argnum
     * @param string $path
     */
    public function __construct($arg, $argnum, $path)
    {
        $this->arg = $arg;
        $this->argnum = $argnum;
        $this->path = $path;
    }
}

final class _ClassTarget
{
    /** @var string */
    public $arg;

    /** @var int */
    public $argnum;

    /** @var string */
    public $class;

    /** @var int */
    public $classnum;

    /** @var _MethodTarget[] */
    public $targets = array();

    /**
     * @param string $arg
     * @param int $argnum
     * @param string $class
     * @param int $classnum
     */
    public function __construct($arg, $argnum, $class, $classnum)
    {
        $this->arg = $arg;
        $this->argnum = $argnum;
        $this->class = $class;
        $this->classnum = $classnum;
    }
}


final class _MethodTarget
{
    /** @var string */
    public $arg;

    /** @var int */
    public $argnum;

    /** @var string */
    public $class;

    /** @var int */
    public $classnum;

    /** @var string */
    public $method;

    /** @var int */
    public $methodnum;

    /**
     * @param string $arg
     * @param int $argnum
     * @param string $class
     * @param int $classnum
     * @param string $method
     * @param int $methodnum
     */
    public function __construct($arg, $argnum, $class, $classnum, $method, $methodnum)
    {
        $this->arg = $arg;
        $this->argnum = $argnum;
        $this->class = $class;
        $this->classnum = $classnum;
        $this->method = $method;
        $this->methodnum = $methodnum;
    }
}


final class _FunctionTarget
{
    /** @var string */
    public $arg;

    /** @var int */
    public $argnum;

    /** @var string */
    public $function;

    /** @var int */
    public $functionnum;

    /**
     * @param string $arg
     * @param int $argnum
     * @param string $function
     * @param int $functionnum
     */
    public function __construct($arg, $argnum, $function, $functionnum)
    {
        $this->arg = $arg;
        $this->argnum = $argnum;
        $this->function = $function;
        $this->functionnum = $functionnum;
    }
}


/**
 * @param string[] $args
 * @param string $root
 * @return ParsedTargets
 */
function _parse_targets(Logger $logger, array $args, $root)
{
    $parsed = new ParsedTargets;
    $iter = new _ParseTargetIterator($args);
    // @todo Ensure all identifiers are trimmed
    while ($iter->index < $iter->count)
    {
        $target = namespace\_parse_path_target($logger, $iter, $root);
        if ($target)
        {
            $parsed->targets[] = $target;
        }
        else
        {
            $parsed->had_errors = true;
        }
    }
    return $parsed;
}


/**
 * @param string $root
 * @return ?_PathTarget
 */
function _parse_path_target(Logger $logger, _ParseTargetIterator $iter, $root)
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
            $target = $leaf = new _PathTarget($arg, $iter->index, $root);
            $pos = \strpos($realpath, \DIRECTORY_SEPARATOR, \strlen($root));
            while (false !== $pos)
            {
                ++$pos;
                $temp = new _PathTarget($arg, $iter->index, \substr($realpath, 0, $pos));
                $leaf->targets[] = $temp;
                $leaf = $temp;
                $pos = \strpos($realpath, \DIRECTORY_SEPARATOR, $pos);
            }
            if (!$dir)
            {
                $temp = new _PathTarget($arg, $iter->index, $realpath);
                $leaf->targets[] = $temp;
                $leaf = $temp;
            }
        }
        else
        {
            $logger->log_error($path, "This path is outside the test root directory $root");
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
function _parse_function_target(
    Logger $logger, _ParseTargetIterator $iter, _PathTarget $parent)
{
    $valid = true;
    $arg = $iter->args[$iter->index++];
    $functions = \substr($arg, namespace\_TARGET_FUNCTION_LEN);
    foreach (\explode(',', $functions) as $i => $function)
    {
        if (\strlen($function))
        {
            $parent->targets[] = new _FunctionTarget(
                $arg, $iter->index, $function, $i + 1);
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
function _parse_class_target(
    Logger $logger, _ParseTargetIterator $iter, _PathTarget $parent)
{
    $valid = true;
    $arg = $iter->args[$iter->index++];
    $classes = \substr($arg, namespace\_TARGET_CLASS_LEN);
    foreach (\explode(';', $classes) as $i => $class)
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
            $target = new _ClassTarget($arg, $iter->index, $class, $i + 1);
            $parent->targets[] = $target;

            foreach ($methods as $j => $method)
            {
                if (\strlen($method))
                {
                    $target->targets[] = new _MethodTarget(
                        $arg, $iter->index, $class, $i + 1, $method, $j + 1);
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


final class ValidatedTargets
{
    /** @var _Target[] */
    public $targets = array();

    /** @var bool */
    public $had_errors;

    /**
     * @param bool $had_errors
     */
    public function __construct($had_errors)
    {
        $this->had_errors = $had_errors;
    }
}


/**
 * @return ValidatedTargets
 */
function _validate_targets(Logger $logger, DirectoryTest $tests, ParsedTargets $parsed)
{
    $validated = new ValidatedTargets($parsed->had_errors);
    $suite = new DirectoryTest;
    $suite->tests[$tests->name] = $tests;
    foreach ($parsed->targets as $target)
    {
        $target = namespace\_validate_target($logger, $suite, $target);
        if ($target)
        {
            $validated->targets[] = $target;
        }
        else
        {
            $validated->had_errors = true;
        }
    }
    return $validated;
}


/**
 * @param DirectoryTest|FileTest|ClassTest $test
 * @param _ClassTarget|_FunctionTarget|_PathTarget|_MethodTarget $unvalidated
 * @return ?_Target
 */
function _validate_target(Logger $logger, $test, $unvalidated)
{
    $valid = false;
    $validated = null;

    if ($unvalidated instanceof _PathTarget)
    {
        $name = $unvalidated->path;
        if (isset($test->tests[$name]))
        {
            $valid = true;
            $validated = new _Target($name);
            if ($unvalidated->targets)
            {
                $test = $test->tests[$name];
                \assert(!($test instanceof FunctionTest));
                foreach ($unvalidated->targets as $subtarget)
                {
                    $subtarget = namespace\_validate_target($logger, $test, $subtarget);
                    if ($subtarget)
                    {
                        $validated->subtargets[] = $subtarget;
                    }
                    else
                    {
                        $valid = false;
                    }
                }
            }
        }
        else
        {
            $logger->log_error(
                $unvalidated->arg,
                "Path {$unvalidated->path} is not a test path"
            );
        }
    }
    elseif ($unvalidated instanceof _ClassTarget)
    {
        $name = "class {$unvalidated->class}";
        if (isset($test->tests[$name]))
        {
            $valid = true;
            $validated = new _Target($name);
            if ($unvalidated->targets)
            {
                $test = $test->tests[$name];
                \assert(!($test instanceof FunctionTest));
                foreach ($unvalidated->targets as $subtarget)
                {
                    $subtarget = namespace\_validate_target($logger, $test, $subtarget);
                    if ($subtarget)
                    {
                        $validated->subtargets[] = $subtarget;
                    }
                    else
                    {
                        $valid = false;
                    }
                }
            }
        }
        else
        {
            $logger->log_error(
                $unvalidated->arg,
                "Class {$unvalidated->class} is not a test class"
            );
        }
    }
    elseif ($unvalidated instanceof _FunctionTarget)
    {
        $name = "function {$unvalidated->function}";
        if (isset($test->tests[$name]))
        {
            $valid = true;
            $validated = new _Target($name);
        }
        else
        {
            $logger->log_error(
                $unvalidated->arg,
                "Function {$unvalidated->function} is not a test function"
            );
        }
    }
    elseif ($unvalidated instanceof _MethodTarget)
    {
        $name = $unvalidated->method;
        if (isset($test->tests[$name]))
        {
            $valid = true;
            $validated = new _Target($name);
        }
        else
        {
            $logger->log_error(
                $unvalidated->arg,
                "Method {$unvalidated->method} is not a test method"
            );
        }
    }
    else
    {
        throw new \Exception("Unxpected subtarget type: " . \get_class($unvalidated));
    }

    \assert(!$valid || $validated);
    return $valid ? $validated : null;
}


/**
 * @return void
 */
function _deduplicate_target(_Target $child, _Target $parent)
{
    \assert(isset($parent->subtargets));

    $name = $child->name;
    if (isset($parent->subtargets[$name]))
    {
        $to = $parent->subtargets[$name];
    }
    else
    {
        $to = new _Target($name);
        $parent->subtargets[$name] = $to;
    }

    if (!$child->subtargets)
    {
        $to->subtargets = null;
    }
    elseif (isset($to->subtargets))
    {
        foreach ($child->subtargets as $subtarget)
        {
            namespace\_deduplicate_target($subtarget, $to);
        }
    }
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
