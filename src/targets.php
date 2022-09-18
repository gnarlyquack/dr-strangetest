<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


const _SPECIFIER_PATTERN = '~^--(class|function|run)=(.*)$~';
\define('strangetest\\_CLASS_SPECIFIER_LEN', \strlen('--class='));
\define('strangetest\\_FUNCTION_SPECIFER_LEN', \strlen('--function='));
\define('strangetest\\_RUN_SPECIFIER_LEN', \strlen('--run='));


/**
 * @param TestRunGroup|DirectoryTest $tests
 * @param string[] $args
 * @return null|TestRunGroup|DirectoryTest
 */
function process_specifiers(Logger $logger, $tests, array $args)
{
    \assert(\count($args) > 0);

    $specifiers = namespace\_parse_specifiers($logger, $tests, $args);
    if ($specifiers->had_errors)
    {
        $result = null;
    }
    else
    {
        $result = namespace\_build_tests_from_specifiers($tests, $specifiers->specifiers);
    }
    return $result;
}


final class _SpecifierIterator extends struct
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


final class _ParsedSpecifiers extends struct
{
    /** @var _PathSpecifier[] */
    public $specifiers = array();

    /** @var bool */
    public $had_errors = false;
}


final class _PathSpecifier extends struct
{
    /** @var DirectoryTest|FileTest */
    public $test;

    /** @var array<_PathSpecifier|_ClassSpecifier|_FunctionSpecifier> */
    public $specifiers = array();

    /** @var ?TestRunGroup */
    public $group;

    /** @var array<string[]> */
    public $runs = array();
}


final class _FunctionSpecifier extends struct
{
    /** @var FunctionTest */
    public $test;

    /**
     * @param FunctionTest $test
     */
    public function __construct($test)
    {
        $this->test = $test;
    }
}


final class _ClassSpecifier extends struct
{
    /** @var ClassTest */
    public $test;

    /** @var _MethodSpecifier[] */
    public $specifiers = array();
}


final class _MethodSpecifier extends struct
{
    /** @var MethodTest */
    public $test;

    /**
     * @param MethodTest $test
     */
    public function __construct($test)
    {
        $this->test = $test;
    }
}


/**
 * @param TestRunGroup|DirectoryTest $reference
 * @param string[] $args
 * @return _ParsedSpecifiers
 */
function _parse_specifiers(Logger $logger, $reference, array $args)
{
    $parsed = new _ParsedSpecifiers;
    $iter = new _SpecifierIterator($args);
    // @todo Ensure all identifiers are trimmed
    while ($iter->index < $iter->count)
    {
        $specifier = namespace\_parse_path_specifier($logger, $reference, $iter);
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
 * @param TestRunGroup|DirectoryTest $reference
 * @return ?_PathSpecifier
 */
function _parse_path_specifier(
    Logger $logger,
    $reference,
    _SpecifierIterator $iter)
{
    $valid = false;
    $specifier = $leaf = null;
    $runs = array();
    $root = ($reference instanceof TestRunGroup) ? $reference->path : $reference->name;

    $arg = $iter->args[$iter->index++];
    $target = (\DIRECTORY_SEPARATOR === \substr($arg, 0, 1)) ? $arg : ($root . $arg);
    $target = \realpath($target);
    if (false === $target)
    {
        $logger->log_error(new ErrorEvent($arg, 'This path does not exist'));
    }
    else
    {
        if (\is_dir($target))
        {
            \assert(\DIRECTORY_SEPARATOR !== \substr($target, -1));
            $target .= \DIRECTORY_SEPARATOR;
        }

        if (0 === \substr_compare($target, $root, 0, \strlen($root)))
        {
            $valid = true;
            $specifier = $leaf = new _PathSpecifier;

            for (;;)
            {
                if ($reference instanceof TestRunGroup)
                {
                    $leaf->group = $reference;
                    $runs[] = $leaf;
                    $reference = $reference->tests;
                }
                $leaf->test = $reference;

                $offset = \strlen($reference->name);
                $pos = \strpos($target, \DIRECTORY_SEPARATOR, $offset);
                if (false === $pos)
                {
                    $path = $target;
                }
                else
                {
                    $path = \substr($target, 0, $pos + 1);
                }

                if ($offset < \strlen($path))
                {
                    \assert($reference instanceof DirectoryTest);
                    if (isset($reference->tests[$path]))
                    {
                        $reference = $reference->tests[$path];
                        $temp = new _PathSpecifier;
                        $leaf->specifiers[] = $temp;
                        $leaf = $temp;
                    }
                    else
                    {
                        $logger->log_error(new ErrorEvent(
                            $arg,
                            "This path is not a test path"));
                        $valid = false;
                        break;
                    }
                }
                else
                {
                    break;
                }
            }
        }
        else
        {
            $logger->log_error(new ErrorEvent($arg, "This path is outside the test root directory $root"));
        }
    }

    if ($valid)
    {
        \assert(isset($specifier));
        \assert(isset($leaf));
        \assert($reference === $leaf->test);
        \assert($reference->name === $target);

        if ($reference instanceof FileTest)
        {
            $valid = namespace\_parse_file_specifiers(
                $logger, $reference, $runs, $specifier, $leaf, $iter);
        }
        else
        {
            \assert($reference instanceof DirectoryTest);
            $valid = namespace\_parse_directory_specifiers($logger, $runs, $specifier, $iter);
        }
    }
    else
    {
        while (
            ($iter->index < $iter->count)
            && \preg_match(namespace\_SPECIFIER_PATTERN, $iter->args[$iter->index]))
        {
            // Consume specifiers that apply to an invalid path
            ++$iter->index;
        }
    }

    \assert(!$valid || isset($specifier));
    return $valid ? $specifier : null;
}


/**
 * @param _PathSpecifier[] $runs
 * @return bool
 */
function _parse_directory_specifiers(
    Logger $logger,
    array $runs,
    _PathSpecifier $directory,
    _SpecifierIterator $iter)
{
    $valid = true;
    while (
        ($iter->index < $iter->count)
        && \preg_match(namespace\_SPECIFIER_PATTERN, $iter->args[$iter->index], $matches))
    {
        if ('class' === $matches[1])
        {
            $logger->log_error(new ErrorEvent(
                $iter->args[$iter->index++],
                'Classes can only be specified for a file'));
            $valid = false;
        }
        elseif ('function' === $matches[1])
        {
            $logger->log_error(new ErrorEvent(
                $iter->args[$iter->index++],
                'Functions can only be specified for a file'));
            $valid = false;
        }
        else
        {
            \assert('run' === $matches[1]);
            if (!namespace\_parse_run_specifier($logger, $runs, $directory, $iter))
            {
                $valid = false;
            }
        }
    }

    return $valid;
}


/**
 * @param _PathSpecifier[] $runs
 * @return bool
 */
function _parse_file_specifiers(
    Logger $logger,
    FileTest $reference,
    array $runs,
    _PathSpecifier $specifier,
    _PathSpecifier $file,
    _SpecifierIterator $iter)
{
    $valid = true;
    while (
        ($iter->index < $iter->count)
        && \preg_match(namespace\_SPECIFIER_PATTERN, $iter->args[$iter->index], $matches))
    {
        if ('class' === $matches[1])
        {
            if (!namespace\_parse_class_specifier($logger, $reference, $file, $iter))
            {
                $valid = false;
            }
        }
        elseif ('function' === $matches[1])
        {
            if (!namespace\_parse_function_specifier($logger, $reference, $file, $iter))
            {
                $valid = false;
            }
        }
        else
        {
            \assert('run' === $matches[1]);
            if (!namespace\_parse_run_specifier($logger, $runs, $specifier, $iter))
            {
                $valid = false;
            }
        }
    }

    return $valid;
}


/**
 * @param _PathSpecifier[] $references
 * @return bool
 */
function _parse_run_specifier(
    Logger $logger,
    array $references,
    _PathSpecifier $specifier,
    _SpecifierIterator $iter)
{
    $valid = true;
    $run_count = \count($references);
    $arg = $iter->args[$iter->index++];
    $runs = \substr($arg, namespace\_RUN_SPECIFIER_LEN);
    foreach (\explode(';', $runs) as $run)
    {
        $specified = array();

        $names = \explode(',', $run);
        $name_count = \count($names);

        $name_index = 0;
        $run_index = 0;
        $blank = true;
        while (($name_index < $name_count) && ($run_index < $run_count))
        {
            $reference = $references[$run_index++]->group;
            \assert(isset($reference));

            $name = $names[$name_index];
            if (strlen($name))
            {
                if (isset($reference->runs[$name]))
                {
                    ++$name_index;
                }
                elseif ('*' === $name)
                {
                    $name = '';
                    ++$name_index;
                }
                else
                {
                    $name = '';
                }
            }
            else
            {
                $logger->log_error(new ErrorEvent($arg, "Run specifier '{$run}' is missing a run name"));
                $valid = false;
                break;
            }

            $specified[] = $name;
        }

        if ($valid)
        {
            if ($name_index < $name_count)
            {
                $extra = \implode(',', \array_slice($names, $name_index));
                $logger->log_error(new ErrorEvent(
                    $arg,
                    "Run specifier '{$run}' had extra and/or invalid run names: {$extra}"));
                $valid = false;
            }
            else
            {
                $specifier->runs[] = $specified;
            }
        }
    }

    return $valid;
}


/**
 * @return bool
 */
function _parse_class_specifier(
    Logger $logger,
    FileTest $reference,
    _PathSpecifier $parent,
    _SpecifierIterator $iter)
{
    $valid = true;
    $arg = $iter->args[$iter->index++];
    $classes = \substr($arg, namespace\_CLASS_SPECIFIER_LEN);
    foreach (\explode(';', $classes) as $c => $class)
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
            $name = 'class ' . $class;
            if (isset($reference->tests[$name]))
            {
                $test = $reference->tests[$name];
                \assert($test instanceof ClassTest);
                $specifier = new _ClassSpecifier;
                $specifier->test = $test;
                $parent->specifiers[] = $specifier;

                foreach ($methods as $m => $method)
                {
                    if (\strlen($method))
                    {
                        if (isset($test->tests[$method]))
                        {
                            $method = $test->tests[$method];
                            $specifier->specifiers[] = new _MethodSpecifier($method);
                        }
                        else
                        {
                            $logger->log_error(new ErrorEvent(
                                $arg,
                                "Method '{$method}' is not a test method in {$test->test->name}"));
                            $valid = false;
                        }
                    }
                    else
                    {
                        $logger->log_error(new ErrorEvent(
                            $arg,
                            \sprintf(
                                'Method specifier %d for class \'%s\' is missing a name',
                                $m + 1, $test->test->name)));
                        $valid = false;
                    }
                }
            }
            else
            {
                $logger->log_error(new ErrorEvent(
                    $arg,
                    "'{$class}' is not a test class in {$reference->name}"));
                $valid = false;
            }
        }
        else
        {
            $logger->log_error(new ErrorEvent(
                $arg,
                \sprintf('Class specifier %d is missing a name', $c + 1)));
            $valid = false;
        }
    }

    return $valid;
}


/**
 * @return bool
 */
function _parse_function_specifier(
    Logger $logger,
    FileTest $reference,
    _PathSpecifier $parent,
    _SpecifierIterator $iter)
{
    $valid = true;
    $arg = $iter->args[$iter->index++];
    $functions = \substr($arg, namespace\_FUNCTION_SPECIFER_LEN);
    foreach (\explode(',', $functions) as $f => $function)
    {
        if (\strlen($function))
        {
            $name = 'function ' . $function;
            if (isset($reference->tests[$name]))
            {
                $function = $reference->tests[$name];
                \assert($function instanceof FunctionTest);
                $parent->specifiers[] = new _FunctionSpecifier($function);
            }
            else
            {
                $logger->log_error(new ErrorEvent(
                    $arg,
                    "Function '{$function}' is not a test function in {$reference->name}"));
                $valid = false;
            }
        }
        else
        {
            $logger->log_error(new ErrorEvent(
                $arg,
                \sprintf('Function specifier %d is missing a name', $f + 1)));
            $valid = false;
        }
    }

    return $valid;
}


final class _RunSpecifierIterator extends struct
{
    /** @var array<string[]> */
    private $specifiers;

    /** @var int */
    private $specifier_index;

    /** @var string[] */
    private $names;

    /** @var int */
    public $name_index;

    /**
     * @param array<string[]> $specifiers
     */
    public function __construct(array $specifiers)
    {
        $this->specifiers = $specifiers;
        $this->specifier_index = 0;

        $this->names = $specifiers ? $specifiers[0] : array();
        $this->name_index = 0;
    }

    /**
     * @return bool
     */
    public function advance_specifier()
    {
        ++$this->specifier_index;
        $this->name_index = 0;

        $result = $this->specifier_index < \count($this->specifiers);

        if ($result)
        {
            $this->names = $this->specifiers[$this->specifier_index];
        }
        else
        {
            $this->names = array();
        }

        return $result;
    }


    /**
     * @return string[]
     */
    public function next_run()
    {
        $result = array();

        if ($this->name_index < \count($this->names))
        {
            $name = $this->names[$this->name_index];
            if (\strlen($name))
            {
                $result[] = $name;
            }
        }
        $this->name_index++;

        return $result;
    }
}

final class _RunGroupTarget extends struct
{
    /** @var TestRunGroup */
    public $group;

    /** @var array<_DirectoryTarget|_FileTarget> */
    public $targets = array();

    /** @var int */
    public $count;

    /** @var int */
    public $total;
}


final class _DirectoryTarget extends struct
{
    /** @var DirectoryTest */
    public $test;

    /** @var array<_RunGroupTarget|_DirectoryTarget|_FileTarget> */
    public $targets = array();

    /** @var int */
    public $count;

    /** @var int */
    public $total;
}


final class _FileTarget extends struct
{
    /** @var FileTest */
    public $test;

    /** @var _ClassTarget[] */
    public $targets = array();

    /** @var int */
    public $count;

    /** @var int */
    public $total;
}


final class _ClassTarget extends struct
{
    /** @var ClassTest */
    public $test;

    /** @var int */
    public $count;

    /** @var int */
    public $total;
}


/**
 * @param TestRunGroup|DirectoryTest $reference
 * @param _PathSpecifier[] $specifiers
 * @return TestRunGroup|DirectoryTest
 */
function _build_tests_from_specifiers($reference, array $specifiers)
{
    if ($reference instanceof TestRunGroup)
    {
        $target = namespace\_create_empty_run_group($reference);
        foreach ($specifiers as $specifier)
        {
            $iter = new _RunSpecifierIterator($specifier->runs);
            do
            {
                namespace\_add_runs_from_specifier($target, $specifier, $iter);
            } while ($iter->advance_specifier() && ($target->count < $target->total));

            if ($target->count === $target->total)
            {
                break;
            }
        }
        $result = $target->group;
    }
    else
    {
        $target = namespace\_create_empty_directory_test($reference);
        foreach ($specifiers as $specifier)
        {
            $iter = new _RunSpecifierIterator($specifier->runs);
            do
            {
                namespace\_add_directory_tests_from_specifier($target, $specifier, $iter);
            } while ($iter->advance_specifier() && ($target->count < $target->total));

            if ($target->count === $target->total)
            {
                break;
            }
        }
        $result = $target->test;
    }

    return $result;
}


/**
 * @return _RunGroupTarget
 */
function _create_empty_run_group(TestRunGroup $source)
{
    $group = new TestRunGroup;
    $group->id = $source->id;
    $group->path = $source->path;

    $target = new _RunGroupTarget;
    $target->group = $group;
    $target->count = 0;
    $target->total = \count($source->runs);

    return $target;
}


/**
 * @return void
 */
function _add_run_group_from_specifier(
    _DirectoryTarget $parent_target, _PathSpecifier $child_specifier, _RunSpecifierIterator $iter)
{
    \assert(isset($child_specifier->group));

    $child_name = $child_specifier->test->name;
    $parent = $parent_target->test;
    if (isset($parent->tests[$child_name]))
    {
        $child_target = $parent_target->targets[$child_name];
        \assert($child_target instanceof _RunGroupTarget);
    }
    else
    {
        $child_target = namespace\_create_empty_run_group($child_specifier->group);
        $parent_target->targets[$child_name] = $child_target;
        $parent->tests[$child_name] = $child_target->group;
    }

    if ($child_target->count < $child_target->total)
    {
        namespace\_add_runs_from_specifier($child_target, $child_specifier, $iter);
        if ($child_target->count === $child_target->total)
        {
            ++$parent_target->count;
        }
    }
}


/**
 * @return void
 */
function _add_runs_from_specifier(
    _RunGroupTarget $target, _PathSpecifier $specifier, _RunSpecifierIterator $iter)
{
    \assert($target->count < $target->total);

    $reference = $specifier->group;
    \assert(isset($reference));

    $run_names = $iter->next_run();
    if (!$run_names)
    {
        $run_names = \array_keys($reference->runs);
    }

    $run_index = $iter->name_index;
    $group = $target->group;
    foreach ($run_names as $run_name)
    {
        if (isset($group->runs[$run_name]))
        {
            $child_target = $target->targets[$run_name];
        }
        else
        {
            if ($specifier->test instanceof DirectoryTest)
            {
                $child_target = namespace\_create_empty_directory_test($specifier->test);
            }
            else
            {
                $child_target = namespace\_create_empty_file_test($specifier->test);
            }
            $target->targets[$run_name] = $child_target;

            $run = new TestRun;
            $run->id = $reference->runs[$run_name]->id;
            $run->name = $reference->runs[$run_name]->name;
            $run->setup = $reference->runs[$run_name]->setup;
            $run->teardown = $reference->runs[$run_name]->teardown;
            $run->tests = $child_target->test;
            $group->runs[$run_name] = $run;
        }

        if ($child_target->count < $child_target->total)
        {
            if ($child_target instanceof _DirectoryTarget)
            {
                namespace\_add_directory_tests_from_specifier($child_target, $specifier, $iter);
            }
            else
            {
                namespace\_add_file_tests_from_specifier($child_target, $specifier);
            }

            if ($child_target->count === $child_target->total)
            {
                ++$target->count;
            }
        }

        $iter->name_index = $run_index;
    }
}


/**
 * @return _DirectoryTarget
 */
function _create_empty_directory_test(DirectoryTest $source)
{
    $directory = new DirectoryTest;
    $directory->name = $source->name;
    $directory->setup = $source->setup;
    $directory->teardown = $source->teardown;

    $target = new _DirectoryTarget;
    $target->test = $directory;
    $target->count = 0;
    $target->total = \count($source->tests);

    return $target;
}


/**
 * @return void
 */
function _add_directory_test_from_specifier(
    _DirectoryTarget $parent_target, _PathSpecifier $child_specifier, _RunSpecifierIterator $iter)
{
    $child_name = $child_specifier->test->name;
    $parent = $parent_target->test;
    if (isset($parent->tests[$child_name]))
    {
        $child_target = $parent_target->targets[$child_name];
        \assert($child_target instanceof _DirectoryTarget);
    }
    else
    {
        \assert($child_specifier->test instanceof DirectoryTest);
        $child_target = namespace\_create_empty_directory_test($child_specifier->test);
        $parent_target->targets[$child_name] = $child_target;
        $parent->tests[$child_name] = $child_target->test;
    }

    if ($child_target->count < $child_target->total)
    {
        namespace\_add_directory_tests_from_specifier($child_target, $child_specifier, $iter);
        if ($child_target->count === $child_target->total)
        {
            ++$parent_target->count;
        }
    }
}


/**
 * @return void
 */
function _add_directory_tests_from_specifier(
    _DirectoryTarget $target, _PathSpecifier $specifier, _RunSpecifierIterator $iter)
{
    \assert($target->count < $target->total);
    \assert($target->test->name === $specifier->test->name);

    if ($specifier->specifiers)
    {
        foreach ($specifier->specifiers as $child)
        {
            \assert($child instanceof _PathSpecifier);
            if ($child->group)
            {
                namespace\_add_run_group_from_specifier($target, $child, $iter);
            }
            elseif ($child->test instanceof DirectoryTest)
            {
                namespace\_add_directory_test_from_specifier($target, $child, $iter);
            }
            else
            {
                namespace\_add_file_test_from_specifier($target, $child);
            }
        }
    }
    else
    {
        \assert($specifier->test instanceof DirectoryTest);
        $target->test->tests = $specifier->test->tests;
        $target->count = $target->total;
    }
}



/**
 * @return _FileTarget
 */
function _create_empty_file_test(FileTest $source)
{
    $file = new FileTest;
    $file->name = $source->name;
    $file->setup_file = $source->setup_file;
    $file->teardown_file = $source->teardown_file;
    $file->setup_function = $source->setup_function;
    $file->teardown_function = $source->teardown_function;

    $target = new _FileTarget;
    $target->test = $file;
    $target->count = 0;
    $target->total = \count($source->tests);

    return $target;
}


/**
 * @return void
 */
function _add_file_test_from_specifier(
    _DirectoryTarget $parent_target, _PathSpecifier $child_specifier)
{
    $child_name = $child_specifier->test->name;
    $parent = $parent_target->test;
    if (isset($parent->tests[$child_name]))
    {
        $child_target = $parent_target->targets[$child_name];
        \assert($child_target instanceof _FileTarget);
    }
    else
    {
        \assert($child_specifier->test instanceof FileTest);
        $child_target = namespace\_create_empty_file_test($child_specifier->test);
        $parent_target->targets[$child_name] = $child_target;
        $parent->tests[$child_name] = $child_target->test;
    }

    if ($child_target->count < $child_target->total)
    {
        namespace\_add_file_tests_from_specifier($child_target, $child_specifier);
        if ($child_target->count === $child_target->total)
        {
            ++$parent_target->count;
        }
    }
}


/**
 * @return void
 */
function _add_file_tests_from_specifier(
    _FileTarget $target, _PathSpecifier $specifier)
{
    \assert($target->count< $target->total);
    \assert($target->test->name === $specifier->test->name);

    if ($specifier->specifiers)
    {
        foreach ($specifier->specifiers as $child)
        {
            if ($child instanceof _ClassSpecifier)
            {
                namespace\_add_class_test_from_specifier($target, $child);
            }
            else
            {
                \assert($child instanceof _FunctionSpecifier);
                $file = $target->test;
                $test = $child->test;
                $name = 'function ' . $test->name;
                if (!isset($file->tests[$name]))
                {
                    $file->tests[$name] = $test;
                    ++$target->count;
                }
            }
        }
    }
    else
    {
        \assert($specifier->test instanceof FileTest);
        $target->test->tests = $specifier->test->tests;
        $target->count = $target->total;
    }
}


/**
 * @return void
 */
function _add_class_test_from_specifier(
    _FileTarget $parent_target, _ClassSpecifier $child_specifier)
{
    $child_name = 'class ' . $child_specifier->test->test->name;
    $parent = $parent_target->test;
    if (isset($parent->tests[$child_name]))
    {
        $child_target = $parent_target->targets[$child_name];
    }
    else
    {
        $child = new ClassTest;
        $child->test = $child_specifier->test->test;
        $child->setup_object = $child_specifier->test->setup_object;
        $child->teardown_object = $child_specifier->test->teardown_object;
        $child->setup_method = $child_specifier->test->setup_method;
        $child->teardown_method = $child_specifier->test->teardown_method;

        $child_target = new _ClassTarget;
        $child_target->test = $child;
        $child_target->count = 0;
        $child_target->total = \count($child_specifier->test->tests);

        $parent_target->targets[$child_name] = $child_target;
        $parent->tests[$child_name] = $child;
    }

    if ($child_target->count < $child_target->total)
    {
        if ($child_specifier->specifiers)
        {
            $child = $child_target->test;
            foreach ($child_specifier->specifiers as $method)
            {
                $name = $method->test->test->name;
                if (!isset($child->tests[$name]))
                {
                    $child->tests[$name] = $method->test;
                    ++$child_target->count;
                }
            }
        }
        else
        {
            $child_target->test->tests = $child_specifier->test->tests;
            $child_target->count = $child_target->total;
        }

        if ($child_target->count === $child_target->total)
        {
            ++$parent_target->count;
        }
    }
}
