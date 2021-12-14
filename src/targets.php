<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


const _SPECIFIER_PATTERN = '~^--(class|function|run)=(.*)$~';
\define('strangetest\\_TARGET_CLASS_LEN', \strlen('--class='));
\define('strangetest\\_TARGET_FUNCTION_LEN', \strlen('--function='));
\define('strangetest\\_TARGET_RUN_LEN', \strlen('--run='));


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

    /** @var string[] */
    public $runs;

    /** @var ?_Target[] */
    public $subtargets;


    /**
     * @param string $name
     * @param string[] $runs
     * @param ?_Target[] $subtargets
     */
    public function __construct($name, $runs = array(), $subtargets = array())
    {
        $this->name = $name;
        $this->runs = $runs;
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
 * @return ?PathTest
 */
function process_specifiers(Logger $logger, PathTest $tests, array $args)
{
    \assert(\DIRECTORY_SEPARATOR === \substr($tests->name, -1));
    \assert(\count($args) > 0);

    $specifiers = namespace\_parse_specifiers($logger, $args, $tests->name);
    $specifiers = namespace\_validate_specifiers($logger, $tests, $specifiers);

    if ($specifiers->had_errors)
    {
        $result = null;
    }
    else
    {
        $result = namespace\_normalize_specifiers($tests, $specifiers->specifiers);
        $result = namespace\_build_test_from_path_target($result, $tests);
    }
    return $result;

}


final class _ArgIterator
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


final class _ParsedSpecifiers
{
    /** @var _ParsedPathSpecifier[] */
    public $specifiers = array();

    /** @var bool */
    public $had_errors = false;
}


final class _ParsedPathSpecifier
{
    /** @var string */
    public $arg;

    /** @var int */
    public $argnum;

    /** @var string */
    public $path;

    /**
     * @var array<_ParsedPathSpecifier|_ParsedClassSpecifier|_ParsedFunctionSpecifier>
     * */
    public $specifiers = array();

    /** @var _ParsedRunSpecifier[] */
    public $runs = array();

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


final class _ParsedRunSpecifier
{
    /** @var string */
    public $arg;

    /** @var string[] */
    public $names;

    /**
     * @param string $arg
     * @param string[] $names
     */
    public function __construct($arg, array $names)
    {
        $this->arg = $arg;
        $this->names = $names;
    }
}


final class _ParsedClassSpecifier
{
    /** @var string */
    public $arg;

    /** @var int */
    public $argnum;

    /** @var string */
    public $class;

    /** @var int */
    public $classnum;

    /** @var _ParsedMethodSpecifier[] */
    public $specifiers = array();

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


final class _ParsedMethodSpecifier
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


final class _ParsedFunctionSpecifier
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
 * @return _ParsedSpecifiers
 */
function _parse_specifiers(Logger $logger, array $args, $root)
{
    $parsed = new _ParsedSpecifiers;
    $iter = new _ArgIterator($args);
    // @todo Ensure all identifiers are trimmed
    while ($iter->index < $iter->count)
    {
        $specifier = namespace\_parse_path_specifier($logger, $iter, $root);
        if ($specifier)
        {
            $parsed->specifiers[] = $specifier;
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
 * @return ?_ParsedPathSpecifier
 */
function _parse_path_specifier(Logger $logger, _ArgIterator $iter, $root)
{
    $valid = false;
    $specifier = $leaf = null;
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
            $specifier = $leaf = new _ParsedPathSpecifier($arg, $iter->index, $root);
            $pos = \strpos($realpath, \DIRECTORY_SEPARATOR, \strlen($root));
            while (false !== $pos)
            {
                ++$pos;
                $temp = new _ParsedPathSpecifier(
                    $arg, $iter->index, \substr($realpath, 0, $pos));
                $leaf->specifiers[] = $temp;
                $leaf = $temp;
                $pos = \strpos($realpath, \DIRECTORY_SEPARATOR, $pos);
            }
            if (!$dir)
            {
                $temp = new _ParsedPathSpecifier($arg, $iter->index, $realpath);
                $leaf->specifiers[] = $temp;
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
        && \preg_match(namespace\_SPECIFIER_PATTERN, $iter->args[$iter->index], $matches))
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
                elseif (!namespace\_parse_class_specifier($logger, $iter, $leaf))
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
                elseif (!namespace\_parse_function_specifier($logger, $iter, $leaf))
                {
                    $valid = false;
                }
            }
            else
            {
                \assert('run' === $matches[1]);
                if (!namespace\_parse_run_specifier($logger, $iter, $leaf))
                {
                    $valid = false;
                }
            }
        }
        else
        {
            // Consume specifiers that apply to an invalid path
            ++$iter->index;
        }
    }

    \assert(!$valid || $specifier);
    return $valid ? $specifier : null;
}


/**
 *
 * @return bool
 */
function _parse_function_specifier(
    Logger $logger, _ArgIterator $iter, _ParsedPathSpecifier $parent)
{
    $valid = true;
    $arg = $iter->args[$iter->index++];
    $functions = \substr($arg, namespace\_TARGET_FUNCTION_LEN);
    foreach (\explode(',', $functions) as $i => $function)
    {
        if (\strlen($function))
        {
            $parent->specifiers[] = new _ParsedFunctionSpecifier(
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
function _parse_class_specifier(
    Logger $logger, _ArgIterator $iter, _ParsedPathSpecifier $parent)
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
            $specifier = new _ParsedClassSpecifier($arg, $iter->index, $class, $i + 1);
            $parent->specifiers[] = $specifier;

            foreach ($methods as $j => $method)
            {
                if (\strlen($method))
                {
                    $specifier->specifiers[] = new _ParsedMethodSpecifier(
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


/**
 * @return bool
 */
function _parse_run_specifier(
    Logger $logger, _ArgIterator $iter, _ParsedPathSpecifier $parent)
{
    $valid = true;
    $arg = $iter->args[$iter->index++];
    $runs = \substr($arg, namespace\_TARGET_RUN_LEN);
    foreach (\explode(';', $runs) as $run)
    {
        $names = \explode(',', $run);
        $parsed = array();
        foreach ($names as $name)
        {
            if ($parsed || \strlen($name))
            {
                $parsed[] = $name;
            }
        }

        if ($parsed)
        {
            $parent->runs[] = new _ParsedRunSpecifier($arg, $parsed);
        }
        else
        {
            $logger->log_error($arg, "Run specifier '{$run}' listed no runs");
            $valid = false;
        }
    }

    return $valid;
}


final class _ValidSpecifiers
{
    /** @var _ValidPathSpecifier[] */
    public $specifiers = array();

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


final class _ValidPathSpecifier
{
    /** @var string */
    public $name;

    /** @var ?string[] */
    public $runs = array();

    /**
     * @var array<_ValidPathSpecifier|_ValidClassSpecifier|_ValidFunctionSpecifier>
     * */
    public $specifiers = array();

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}


final class _ValidClassSpecifier
{
    /** @var string */
    public $name;

    /** @var _ValidFunctionSpecifier[] */
    public $specifiers = array();

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}


final class _ValidFunctionSpecifier
{
    /** @var string */
    public $name;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}


/**
 * @return _ValidSpecifiers
 */
function _validate_specifiers(Logger $logger, PathTest $tests, _ParsedSpecifiers $parsed)
{
    $validated = new _ValidSpecifiers($parsed->had_errors);
    $suite = new PathTest;
    $suite->tests[$tests->name] = $tests;
    foreach ($parsed->specifiers as $specifier)
    {
        $specifier = namespace\_validate_path_specifier($logger, $suite, $specifier);
        if ($specifier)
        {
            $validated->specifiers[] = $specifier;
        }
        else
        {
            $validated->had_errors = true;
        }
    }
    return $validated;
}


/**
 * @param array<array{_ValidPathSpecifier, PathTest}> $stack
 * @return ?_ValidPathSpecifier
 */
function _validate_path_specifier(
    Logger $logger,
    PathTest $parent, _ParsedPathSpecifier $unvalidated, array $stack = array())
{
    $valid = false;
    $validated = null;

    $name = $unvalidated->path;
    if (isset($parent->tests[$name]))
    {
        $valid = true;
        $validated = new _ValidPathSpecifier($name);
        if ($unvalidated->specifiers)
        {
            $test = $parent->tests[$name];
            \assert($test instanceof PathTest);
            foreach ($unvalidated->specifiers as $specifier)
            {
                if ($specifier instanceof _ParsedPathSpecifier)
                {
                    $stack[] = array($validated, $test);
                    $specifier = namespace\_validate_path_specifier(
                        $logger, $test, $specifier, $stack);
                }
                elseif ($specifier instanceof _ParsedClassSpecifier)
                {
                    $specifier = namespace\_validate_class_specifier(
                        $logger, $test, $specifier);
                }
                else
                {
                    \assert($specifier instanceof _ParsedFunctionSpecifier);
                    $specifier = namespace\_validate_function_specifier(
                        $logger, $test, $specifier);
                }

                if ($specifier)
                {
                    $validated->specifiers[] = $specifier;
                }
                else
                {
                    $valid = false;
                }
            }
        }
        if ($unvalidated->runs)
        {
            $test = $parent->tests[$name];
            \assert($test instanceof PathTest);
            $stack[] = array($validated, $test);
            foreach ($unvalidated->runs as $run)
            {
                if (!namespace\_validate_run_specifier($logger, $stack, $run))
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

    \assert(!$valid || $validated);
    return $valid ? $validated : null;
}


/**
 * @param array<array{_ValidPathSpecifier, PathTest}> $stack
 * @return bool
 */
function _validate_run_specifier(
    Logger $logger, array $stack, _ParsedRunSpecifier $specifier)
{
    $valid = true;
    $names = $specifier->names;
    $i = \count($stack);
    while ($i)
    {
        list($parent_specifier, $parent_test) = $stack[--$i];
        if (!isset($parent_test->runs['']))
        {
            $name = (string)\end($names);
            if (!\strlen($name) || isset($parent_test->runs[$name]))
            {
                \array_pop($names);
            }
            else
            {
                $name = '';
            }

            if (!\strlen($name))
            {
                $parent_specifier->runs = null;
            }
            elseif (isset($parent_specifier->runs))
            {
                $parent_specifier->runs[] = $name;
            }
        }
        else
        {
            \assert(1 === \count($parent_test->runs));
        }
    }

    if ($names)
    {
        $logger->log_error($specifier->arg, 'Extra and/or invalid run names: ' . \implode(',', $specifier->names));
        $valid = false;
    }

    return $valid;
}


/**
 * @return ?_ValidFunctionSpecifier
 */
function _validate_function_specifier(
    Logger $logger,
    PathTest $parent, _ParsedFunctionSpecifier $unvalidated)
{
    $result = null;
    $name = "function {$unvalidated->function}";
    if (isset($parent->tests[$name]))
    {
        $result = new _ValidFunctionSpecifier($name);
    }
    else
    {
        $logger->log_error(
            $unvalidated->arg,
            "Function {$unvalidated->function} is not a test function"
        );
    }

    return $result;
}


/**
 * @return ?_ValidClassSpecifier
 */
function _validate_class_specifier(
    Logger $logger,
    PathTest $parent, _ParsedClassSpecifier $unvalidated)
{
    $valid = false;
    $validated = null;
    $name = "class {$unvalidated->class}";
    if (isset($parent->tests[$name]))
    {
        $valid = true;
        $validated = new _ValidClassSpecifier($name);
        if ($unvalidated->specifiers)
        {
            $test = $parent->tests[$name];
            \assert($test instanceof ClassTest);
            foreach ($unvalidated->specifiers as $specifier)
            {
                $specifier = namespace\_validate_method_specifier($logger, $test, $specifier);
                if ($specifier)
                {
                    $validated->specifiers[] = $specifier;
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
    \assert(!$valid || $validated);
    return $valid ? $validated : null;
}


/**
 * @return ?_ValidFunctionSpecifier
 */
function _validate_method_specifier(
    Logger $logger,
    ClassTest $parent, _ParsedMethodSpecifier $unvalidated)
{
    $result = null;
    $name = $unvalidated->method;
    if (isset($parent->tests[$name]))
    {
        $result = new _ValidFunctionSpecifier($name);
    }
    else
    {
        $logger->log_error(
            $unvalidated->arg,
            "Method {$unvalidated->method} is not a test method"
        );
    }

    return $result;
}


final class _PathTarget
{
    /** @var string */
    public $name;

    /** @var _RunTarget[] */
    public $runs = array();

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}


final class _RunTarget
{
    /** @var string */
    public $name;

    /** @var ?array<_PathTarget|_ClassTarget|_FunctionTarget> */
    public $tests = array();

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}


final class _ClassTarget
{
    /** @var string */
    public $name;

    /** @var ?_FunctionTarget[] */
    public $tests = array();

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}


final class _FunctionTarget
{
    /** @var string */
    public $name;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}


/**
 * @todo Make minimization of test specifiers more robust
 * Minimization is when we determine all tests are specified, which means we
 * can eliminate the specifier and just run all the tests, e.g., if all methods
 * of a test class are specified, we can just test the entire class.
 *
 * Right now, this doesn't do any minimization of runs and only does trivial
 * minimization of other tests. "Trivial" minimization is when a user
 * explicitly specifies all tests, e.g., specifying a file but no tests within
 * the file. "Non-trivial" minimization is if a user ends up individually
 * specifying all tests. This seems unlikely, but handling this would seem to
 * be fairly easy if we just keep track of the number of "fully" specified
 * tests. A "fully" specified test is one that has all its tests specified. For
 * functions, this is just the test itself, but for classes and paths, this
 * would mean every test that comprises the class or path.
 *
 * @param _ValidPathSpecifier[] $specifiers
 * @return _PathTarget
 */
function _normalize_specifiers(PathTest $test, array $specifiers)
{
    \assert(\count($specifiers) > 0);
    $result = new _RunTarget('');
    foreach ($specifiers as $specifier)
    {
        namespace\_normalize_path_specifier($test, $specifier, $result);
    }

    \assert(isset($result->tests) && 1 === \count($result->tests));
    $result = \current($result->tests);
    \assert($result instanceof _PathTarget);
    return $result;
}


/**
 * @return void
 */
function _normalize_path_specifier(
    PathTest $test, _ValidPathSpecifier $specifier, _RunTarget $parent)
{
    \assert(isset($parent->tests));

    $name = $specifier->name;
    if (isset($parent->tests[$name]))
    {
        $to = $parent->tests[$name];
        \assert($to instanceof _PathTarget);
    }
    else
    {
        $to = new _PathTarget($name);
        $parent->tests[$name] = $to;
    }

    \assert($name === $test->name);
    $run_names = $specifier->runs ? $specifier->runs : \array_keys($test->runs);
    foreach ($run_names as $run_name)
    {
        if (isset($to->runs[$run_name]))
        {
            $run = $to->runs[$run_name];
        }
        else
        {
            $run = new _RunTarget($run_name);
            $to->runs[$run_name] = $run;
        }

        if (!$specifier->specifiers)
        {
            $run->tests = null;
        }
        elseif (isset($run->tests))
        {
            foreach ($specifier->specifiers as $child)
            {
                if ($child instanceof _ValidPathSpecifier)
                {
                    $child_test = $test->tests[$child->name];
                    \assert($child_test instanceof PathTest);
                    namespace\_normalize_path_specifier($child_test, $child, $run);
                }
                elseif ($child instanceof _ValidClassSpecifier)
                {
                    namespace\_normalize_class_specifier($child, $run);
                }
                else
                {
                    \assert($child instanceof _ValidFunctionSpecifier);
                    namespace\_normalize_function_specifier($child, $run);
                }
            }
        }
    }
}


/**
 * @return void
 */
function _normalize_class_specifier(_ValidClassSpecifier $specifier, _RunTarget $parent)
{
    \assert(isset($parent->tests));

    $name = $specifier->name;
    if (isset($parent->tests[$name]))
    {
        $to = $parent->tests[$name];
        \assert($to instanceof _ClassTarget);
    }
    else
    {
        $to = new _ClassTarget($name);
        $parent->tests[$name] = $to;
    }

    if (!$specifier->specifiers)
    {
        $to->tests = null;
    }
    elseif (isset($to->tests))
    {
        foreach ($specifier->specifiers as $child)
        {
            if (!isset($to->tests[$child->name]))
            {
                $to->tests[$child->name] = new _FunctionTarget($child->name);
            }
        }
    }
}


/**
 * @return void
 */
function _normalize_function_specifier(
    _ValidFunctionSpecifier $specifier, _RunTarget $parent)
{
    \assert(isset($parent->tests));

    $name = $specifier->name;
    if (!isset($parent->tests[$name]))
    {
        $parent->tests[$name] = new _FunctionTarget($name);
    }
}


/**
 * @return PathTest
 */
function _build_test_from_path_target(_PathTarget $targets, PathTest $tests)
{
    \assert($targets->name === $tests->name);
    $result = new PathTest;
    $result->name = $tests->name;
    $result->group = $tests->group;
    $result->setup = $tests->setup;
    $result->teardown = $tests->teardown;
    if ($targets->runs)
    {
        foreach ($targets->runs as $target)
        {
            $result->runs[] = namespace\_build_test_from_run_target(
                $target, $tests->runs[$target->name]);
        }
    }
    else
    {
        $result->runs = $tests->runs;
    }
    return $result;
}


/**
 * @return TestRun
 */
function _build_test_from_run_target(_RunTarget $targets, TestRun $tests)
{
    $result = new TestRun;
    $result->name = $tests->name;
    $result->run_info = $tests->run_info;
    if ($targets->tests)
    {
        foreach ($targets->tests as $target)
        {
            $test = $tests->tests[$target->name];
            if ($target instanceof _PathTarget)
            {
                \assert($test instanceof PathTest);
                $target = namespace\_build_test_from_path_target($target, $test);
            }
            elseif ($target instanceof _ClassTarget)
            {
                \assert($test instanceof ClassTest);
                $target = namespace\_build_test_from_class_target($target, $test);
            }
            else
            {
                \assert($target instanceof _FunctionTarget);
                \assert($test instanceof FunctionTest);
                $target = $tests->tests[$target->name];
            }
            $result->tests[] = $target;
        }
    }
    else
    {
        $result->tests = $tests->tests;
    }
    return $result;
}


/**
 * @return ClassTest
 */
function _build_test_from_class_target(_ClassTarget $targets, ClassTest $tests)
{
    $result = new ClassTest;
    $result->file = $tests->file;
    $result->group = $tests->group;
    $result->namespace = $tests->namespace;
    $result->name = $tests->name;
    $result->setup = $tests->setup;
    $result->teardown = $tests->teardown;
    if ($targets->tests)
    {
        foreach ($targets->tests as $target)
        {
            $result->tests[] = $tests->tests[$target->name];
        }
    }
    else
    {
        $result->tests = $tests->tests;
    }
    return $result;
}


/**
 * @param FunctionDependency[] $dependencies
 * @return PathTest
 */
function build_test_from_dependencies(State $state, PathTest $tests, array $dependencies)
{
    $result = new PathTest;
    $result->name = $tests->name;
    $result->group = $tests->group;
    $result->setup = $tests->setup;
    $result->teardown = $tests->teardown;

    foreach ($dependencies as $dependency)
    {
        foreach ($dependency->runs as $run)
        {
            namespace\_build_run_test_from_dependency(
                $state, $tests, $result, $dependency->test, $run->run, 1);
        }
    }

    return $result;
}


/**
 * @param int[] $run
 * @param int $run_index
 * @return void
 */
function _build_run_test_from_dependency(
    State $state, PathTest $tests,
    PathTest $test, FunctionTest $dependency, array $run, $run_index)
{
    if (isset($tests->runs['']))
    {
        if (!isset($test->runs['']))
        {
            $test_run = new TestRun;
            $test_run->name = $test->name;
            \assert(!isset($tests->runs['']->run_info));
            $test->runs[''] = $test_run;
        }
        namespace\_build_path_test_from_dependency(
            $state, $tests->runs[''], $test->runs[''], $dependency, $run, $run_index);
    }
    else
    {
        \assert(\count($run) > 0);
        $run_id = $run[$run_index++] - 1;
        $run_info = $state->runs[$run_id];
        $run_name = $run_info->name;
        if (!isset($test->runs[$run_name]))
        {
            $test_run = new TestRun;
            $test_run->name = $test->name;
            \assert($run_info === $tests->runs[$run_name]->run_info);
            $test_run->run_info = $run_info;
            $test->runs[$run_name] = $test_run;
        }
        namespace\_build_path_test_from_dependency(
            $state, $tests->runs[$run_name],
            $test->runs[$run_name], $dependency, $run, $run_index);
    }
}


/**
 * @param int[] $run
 * @param int $run_index
 * @return void
 */
function _build_path_test_from_dependency(
    State $state, TestRun $tests,
    TestRun $test, FunctionTest $dependency, array $run, $run_index)
{
    if ($dependency->file === $test->name)
    {
        if ($dependency->class)
        {
            $name = 'class ' . $dependency->class;
            $class = $tests->tests[$name];
            \assert($class instanceof ClassTest);
            $last = \end($test->tests);
            if ($last
                && ($last instanceof ClassTest)
                && ($last->name === $dependency->class))
            {
                $child = $last;
            }
            else
            {
                $child = new ClassTest;
                $child->file = $class->file;
                $child->group = $class->group;
                $child->namespace = $class->namespace;
                $child->name = $class->name;
                $child->setup = $class->setup;
                $child->teardown = $class->teardown;
                $test->tests[] = $child;
            }
            $child->tests[] = $class->tests[$dependency->function];
        }
        else
        {
            $name = 'function ' . $dependency->name;
            $test->tests[] = $tests->tests[$name];
        }
    }
    else
    {
        $pos = \strpos($dependency->file, \DIRECTORY_SEPARATOR, \strlen($test->name));
        if (false === $pos)
        {
            $path = $dependency->file;
        }
        else
        {
            $path = \substr($dependency->file, 0, $pos + 1);
        }

        $path = $tests->tests[$path];
        \assert($path instanceof PathTest);
        $last = $test->tests ? \end($test->tests) : null;
        if ($last
            && ($last instanceof PathTest)
            && ($last->name === $path->name))
        {
            $child = $last;
        }
        else
        {
            $child = new PathTest;
            $child->name = $path->name;
            $child->group = $path->group;
            $child->setup = $path->setup;
            $child->teardown = $path->teardown;
            $test->tests[] = $child;
        }
        namespace\_build_run_test_from_dependency(
            $state, $path, $child, $dependency, $run, $run_index);
    }
}
