<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


class _DirectoryFixture
{
    /** @var ?callable-string */
    public $setup = null;

    /** @var ?callable-string */
    public $teardown = null;

    /** @var ?RunInfo[] */
    public $runs = null;
}


/**
 * @param string $path
 * @param int $group
 * @return null|DirectoryTest|TestRunGroup
 */
function discover_directory(State $state, BufferingLogger $logger, $path, $group)
{
    \assert(\DIRECTORY_SEPARATOR === \substr($path, -1));

    $tests = array();
    $setup = array();
    foreach (new \DirectoryIterator($path) as $file)
    {
        $basename = $file->getBasename();
        $pathname = $file->getPathname();
        $type = $file->getType();

        if ('file' === $type)
        {
            if (0 === \strcasecmp($basename, 'setup.php'))
            {
                $setup[] = $pathname;
            }
            elseif ((0 === \substr_compare($basename, 'test', 0, 4, true))
                && (0 === \strcasecmp($file->getExtension(), 'php')))
            {
                $tests[$pathname] = namespace\TYPE_FILE;
            }
        }
        elseif ('dir' === $type)
        {
            if (0 === \substr_compare($basename, 'test', 0, 4, true))
            {
                // Ensure directory names end with a directory separator to
                // ensure we can only match against full directory names
                $pathname .= \DIRECTORY_SEPARATOR;
                $tests[$pathname] = namespace\TYPE_DIRECTORY;
            }
        }
    }

    $valid = true;
    if (\count($setup) > 1)
    {
        namespace\_log_fixture_error($logger, $path, $setup);
        $valid = false;
    }
    if (!$tests)
    {
        // @todo How should we handle a test directory with no tests?
        $valid = false;
    }

    $fixture = null;
    if ($valid)
    {
        if ($setup)
        {
            $fixture = namespace\_discover_directory_setup($state, $logger, $setup[0]);
            $valid = (bool)$fixture;
        }
        else
        {
            $fixture = new _DirectoryFixture;
        }
    }

    $result = null;
    if ($valid)
    {
        \assert($fixture instanceof _DirectoryFixture);

        $directory = new DirectoryTest;
        $directory->name = $path;
        $directory->group = $group;
        $directory->setup = $fixture->setup;
        $directory->teardown = $fixture->teardown;

        if ($fixture->runs)
        {
            $group = namespace\_new_run_group($state, $group);
        }
        foreach ($tests as $name => $type)
        {
            if (namespace\TYPE_DIRECTORY === $type)
            {
                $test = namespace\discover_directory($state, $logger, $name, $group);
            }
            else
            {
                \assert(namespace\TYPE_FILE === $type);
                $test = namespace\_discover_file($state, $logger, $name, $group);
            }

            if ($test)
            {
                $directory->tests[$name] = $test;
            }
        }

        if ($directory->tests)
        {
            if ($fixture->runs)
            {
                $result = new TestRunGroup;
                $result->path = $directory->name;
                $result->tests = $directory;
                foreach ($fixture->runs as $run_info)
                {
                    $run = new TestRun;
                    $run->info = $run_info;
                    $run->tests = $directory;
                    $result->runs[$run_info->name] = $run;
                }
            }
            else
            {
                $result = $directory;
            }
        }
    }

    return $result;
}


/**
 * @param string $filepath
 * @param int $group
 * @return null|FileTest|TestRunGroup
 */
function _discover_file(State $state, BufferingLogger $logger, $filepath, $group)
{
    $result = null;
    $iterator = namespace\_new_token_iterator($logger, $filepath);
    if (!$iterator)
    {
        return $result;
    }

    $namespace = '';
    $input = array(
        'runs' => array(),
        'setup_file' => array(),
        'teardown_file' => array(),
        'setup_function' => array(),
        'setup_function_name' => array(),
        'teardown_function' => array(),
        'teardown_function_name' => array(),
        'tests' => array(),
    );
    $valid = true;
    while ($token = namespace\_next_token($iterator))
    {
        // @todo Use reflection to ensure we discovered an identifier
        // We know we've discovered an identifier if it exists and it was
        // defined in the current file and we haven't already discovered it.
        // We'll need to use reflection anyway to support attributes.
        //
        // It's worth keeping in mind that identifiers may be conditionally
        // defined at runtime, so just because we got a token back doesn't mean
        // we've found an actual definition. Similarly, we may also come upon
        // the same identifier multiple times, either in the same file or
        // across multiple files, and everything will parse as long as at most
        // one of them is executed and defined. To handle these situations, we
        // need to check that any identifer we find actually exists and that we
        // haven't already discovered it. The only case we don't handle is if
        // client code has included a file that we haven't parsed and it
        // defines an identifier that we then subsequently discover in a
        // different file and end up caring about. It seems the only way for us
        // to handle this would be if we could somehow hook into the include
        // process (autoloaders don't count, since they don't allow us to
        // detect explicit includes), however, this seems like enough of an
        // edge case that it's probably not worth worrying about.
        //
        // It's also worth noting that class names and function names are
        // separately namespaced, i.e., a function and class may have identical
        // names, so we need to keep the two types of identifiers distinct.
        if ($token instanceof _ClassToken)
        {
            $class_name = "{$namespace}{$token->name}";
            $test_name = "class {$class_name}";
            if (!isset($state->seen[$test_name])
                && \class_exists($class_name)
                && (0 === \substr_compare($token->name, 'test', 0, 4, true)))
            {
                $state->seen[$test_name] = true;

                $info = new TestInfo();
                $info->type = namespace\TYPE_CLASS;
                $info->filename = $filepath;
                $info->namespace = $namespace;
                $info->name = $class_name;
                $input['tests'][$test_name] = $info;
            }
        }
        elseif ($token instanceof _FunctionToken)
        {
            $function_name = "{$namespace}{$token->name}";
            $test_name = "function {$function_name}";
            if (!isset($state->seen[$test_name])
                // To make PHPStan happy, use is_callable instead of function_exists
                && \is_callable($function_name))
            {
                if (0 === \substr_compare($token->name, 'test', 0, 4, true))
                {
                    $state->seen[$test_name] = true;

                    $info = new TestInfo();
                    $info->type = namespace\TYPE_FUNCTION;
                    $info->filename = $filepath;
                    $info->namespace = $namespace;
                    $info->name = $function_name;
                    $input['tests'][$test_name] = $info;
                }
                elseif (\preg_match('~^(setup|teardown)_?(file|run)?_?(.*)$~i', $token->name, $matches))
                {
                    $state->seen[$test_name] = true;

                    if (0 === \strcasecmp('setup', $matches[1]))
                    {
                        if (0 === \strlen($matches[2]))
                        {
                            $input['setup_function'][] = $function_name;
                            $input['setup_function_name'][] = $token->name;
                        }
                        elseif (0 === \strcasecmp('file', $matches[2]))
                        {
                            $input['setup_file'][] = $function_name;
                        }
                        else
                        {
                            $name = $matches[3];
                            if (0 === \strlen($name))
                            {
                                $message = "Unable to determine run name from setup run function '$function_name'";
                                $logger->log_error($filepath, $message);
                                $valid = false;
                            }
                            else
                            {
                                $run = \strtolower($name);
                                $input['runs'][$run]['name'] = $name;
                                $input['runs'][$run]['setup'][] = $function_name;
                            }
                        }
                    }
                    else
                    {
                        if (0 == strlen($matches[2]))
                        {
                            $input['teardown_function'][] = $function_name;
                            $input['teardown_function_name'][] = $token->name;
                        }
                        elseif (0 === \strcasecmp('file', $matches[2]))
                        {
                            $input['teardown_file'][] = $function_name;
                        }
                        else
                        {
                            $name = $matches[3];
                            if (0 === \strlen($name))
                            {
                                $message = "Unable to determine run name from teardown run function '$function_name'";
                                $logger->log_error($filepath, $message);
                                $valid = false;
                            }
                            else
                            {
                                $run = \strtolower($name);
                                $input['runs'][$run]['teardown'][] = $function_name;
                            }
                        }
                    }
                }
            }
        }
        elseif ($token instanceof _NamespaceToken)
        {
            $namespace = $token->name;
        }
    }

    $output = array(
        'runs' => array(),
        'setup_file' => null,
        'teardown_file' => null,
        'setup_function' => null,
        'setup_function_name' => null,
        'teardown_function' => null,
        'teardown_function_name' => null,
        'tests' => array(),
    );

    if ($input['setup_file'])
    {
        if (\count($input['setup_file']) > 1)
        {
            namespace\_log_fixture_error($logger, $filepath, $input['setup_file']);
            $valid = false;
        }
        else
        {
            $output['setup_file'] = $input['setup_file'][0];
        }
    }

    if ($input['setup_function'])
    {
        if (\count($input['setup_function']) > 1)
        {
            namespace\_log_fixture_error($logger, $filepath, $input['setup_function']);
            $valid = false;
        }
        else
        {
            $output['setup_function'] = $input['setup_function'][0];
            $output['setup_function_name'] = $input['setup_function_name'][0];
        }
    }

    if ($input['teardown_file'])
    {
        if (\count($input['teardown_file']) > 1)
        {
            namespace\_log_fixture_error($logger, $filepath, $input['teardown_file']);
            $valid = false;
        }
        else
        {
            $output['teardown_file'] = $input['teardown_file'][0];
        }
    }

    if ($input['teardown_function'])
    {
        if (\count($input['teardown_function']) > 1)
        {
            namespace\_log_fixture_error($logger, $filepath, $input['teardown_function']);
            $valid = false;
        }
        else
        {
            $output['teardown_function'] = $input['teardown_function'][0];
            $output['teardown_function_name'] = $input['teardown_function_name'][0];
        }
    }

    $output['runs'] = namespace\_validate_runs($state, $logger, $filepath, $input['runs']);

    if ($input['tests'])
    {
        $output['tests'] = $input['tests'];
    }
    else
    {
        // @todo How should we handle a test file with no tests?
        $valid = false;
    }

    if ($valid && false !== $output['runs'])
    {
        $file = new FileTest;
        $file->name = $filepath;
        $file->group = $group;
        $file->setup = $output['setup_file'];
        $file->teardown = $output['teardown_file'];

        if ($output['runs'])
        {
            $group = namespace\_new_run_group($state, $group);
        }

        foreach ($output['tests'] as $name => $info)
        {
            if (namespace\TYPE_CLASS === $info->type)
            {
                $test = namespace\discover_class($state, $logger, $info, $group);
            }
            else
            {
                \assert(namespace\TYPE_FUNCTION === $info->type);
                $test = new FunctionTest();
                $test->group = $group;
                $test->file = $info->filename;
                $test->namespace = $info->namespace;
                $test->function = $info->name;
                $test->name = $info->name;
                \assert(\is_callable($info->name));
                $test->test = $info->name;
                if ($output['setup_function'])
                {
                    $test->setup_name = $output['setup_function_name'];
                    $test->setup = $output['setup_function'];
                }
                if ($output['teardown_function'])
                {
                    $test->teardown_name = $output['teardown_function_name'];
                    $test->teardown = $output['teardown_function'];
                }
            }

            if ($test)
            {
                $file->tests[$name] = $test;
            }
        }

        if ($file->tests)
        {
            if ($output['runs'])
            {
                $result = new TestRunGroup;
                $result->path = $file->name;
                $result->tests = $file;
                foreach ($output['runs'] as $run_info)
                {
                    $run = new TestRun;
                    $run->info = $run_info;
                    $run->tests = $file;
                    $result->runs[$run_info->name] = $run;
                }
            }
            else
            {
                $result = $file;
            }
        }
    }

    return $result;
}


/**
 * @param int $group
 * @return ClassTest|false
 */
function discover_class(State $state, Logger $logger, TestInfo $info, $group)
{
    $classname = $info->name;
    \assert(\class_exists($classname));

    $tests = array();
    $setup_object = array();
    $teardown_object = array();
    $setup_function = null;
    $teardown_function = null;

    foreach (\get_class_methods($classname) as $method)
    {
        if (0 === \substr_compare($method, 'test', 0, 4, true))
        {
            $tests[] = $method;
        }
        elseif (\preg_match('~^(setup|teardown)_?(object)?$~i', $method, $matches))
        {
            if (0 === \strcasecmp('setup', $matches[1]))
            {
                if (isset($matches[2]))
                {
                    $setup_object[] = $method;
                }
                else
                {
                    $setup_function = $method;
                }
            }
            else
            {
                if (isset($matches[2]))
                {
                    $teardown_object[] = $method;
                }
                else
                {
                    $teardown_function = $method;
                }
            }
        }
    }

    $valid = true;
    if (\count($setup_object) > 1)
    {
        namespace\_log_fixture_error($logger, $classname, $setup_object);
        $valid = false;
    }
    if (\count($teardown_object) > 1)
    {
        namespace\_log_fixture_error($logger, $classname, $teardown_object);
        $valid = false;
    }
    if (!$tests)
    {
        // @todo How should we handle a test class with no tests?
        $valid = false;
    }

    $class = false;
    if ($valid)
    {
        $class = new ClassTest();
        $class->group = $group;
        $class->file = $info->filename;
        $class->namespace = $info->namespace;
        $class->name = $classname;
        $class->setup = $setup_object ? $setup_object[0] : null;
        $class->teardown = $teardown_object ? $teardown_object[0] : null;

        foreach ($tests as $name)
        {
            $test = new FunctionTest();
            $test->group = $group;
            $test->file = $class->file;
            $test->namespace = $class->namespace;
            $test->class = $class->name;
            $test->function = $name;
            $test->name = "{$class->name}::{$name}";
            if ($setup_function)
            {
                $test->setup_name = $setup_function;
            }
            if ($teardown_function)
            {
                $test->teardown_name = $teardown_function;
            }

            $class->tests[$name] = $test;
        }
    }

    return $class;
}


/**
 * @param State $state
 * @param BufferingLogger $logger
 * @param string $filepath
 * @return ?_DirectoryFixture
 */
function _discover_directory_setup(State $state, BufferingLogger $logger, $filepath)
{
    $iterator = namespace\_new_token_iterator($logger, $filepath);
    if (!$iterator)
    {
        return null;
    }

    $namespace = '';
    $input = array(
        'runs'=> array(),
        'setup_directory' => array(),
        'teardown_directory' => array(),
    );
    $valid = true;
    while ($token = namespace\_next_token($iterator))
    {
        // @todo Use reflection to ensure we discovered an identifier
        // We know we've discovered an identifier if it exists and it was
        // defined in the current file and we haven't already discovered it.
        // We'll need to use reflection anyway to support attributes.
        //
        // It's worth keeping in mind that identifiers may be conditionally
        // defined at runtime, so just because we got a token back doesn't mean
        // we've found an actual definition. Similarly, we may also come upon
        // the same identifier multiple times, either in the same file or
        // across multiple files, and everything will parse as long as at most
        // one of them is executed and defined. To handle these situations, we
        // need to check that any identifer we find actually exists and that we
        // haven't already discovered it. The only case we don't handle is if
        // client code has included a file that we haven't parsed and it
        // defines an identifier that we then subsequently discover in a
        // different file and end up caring about. It seems the only way for us
        // to handle this would be if we could somehow hook into the include
        // process (autoloaders don't count, since they don't allow us to
        // detect explicit includes), however, this seems like enough of an
        // edge case that it's probably not worth worrying about.
        //
        // It's also worth noting that class names and function names are
        // separately namespaced, i.e., a function and class may have identical
        // names, so we need to keep the two types of identifiers distinct.
        if ($token instanceof _FunctionToken)
        {
            $function_name = "{$namespace}{$token->name}";
            $id = "function {$function_name}";
            if (!isset($state->seen[$id])
                // To make PHPStan happy, use is_callable instead of function_exists
                && \is_callable($function_name)
                && \preg_match('~^(setup|teardown)_?(run)?_?(.*)$~i', $token->name, $matches))
            {
                $state->seen[$id] = true;

                if (0 === \strcasecmp('setup', $matches[1]))
                {
                    if (0 === \strlen($matches[2]))
                    {
                        $input['setup_directory'][] = $function_name;
                    }
                    else
                    {
                        $name = $matches[3];
                        if (0 === \strlen($name))
                        {
                            $message = "Unable to determine run name from setup run function '$function_name'";
                            $logger->log_error($filepath, $message);
                            $valid = false;
                        }
                        else
                        {
                            $run = \strtolower($name);
                            $input['runs'][$run]['name'] = $name;
                            $input['runs'][$run]['setup'][] = $function_name;
                        }
                    }
                }
                else
                {
                    if (0 === \strlen($matches[2]))
                    {
                        $input['teardown_directory'][] = $function_name;
                    }
                    else
                    {
                        $name = $matches[3];
                        if (0 === \strlen($name))
                        {
                            $message = "Unable to determine run name from teardown run function '$function_name'";
                            $logger->log_error($filepath, $message);
                            $valid = false;
                        }
                        else
                        {
                            $run = \strtolower($name);
                            $input['runs'][$run]['teardown'][] = $function_name;
                        }
                    }
                }
            }
        }
        elseif ($token instanceof _NamespaceToken)
        {
            $namespace = $token->name;
        }
    }

    // @fixme $valid is set above but never checked. Presumably this should happen here?
    $output = array(
        'runs'=> namespace\_validate_runs($state, $logger, $filepath, $input['runs']),
        'setup_directory' => namespace\_validate_fixture($logger, $filepath, $input['setup_directory']),
        'teardown_directory' => namespace\_validate_fixture($logger, $filepath, $input['teardown_directory']),
    );

    $fixture = null;
    if ((false !== $output['runs'])
        && (false !== $output['setup_directory'])
        && (false !== $output['teardown_directory']))
    {
        $fixture = new _DirectoryFixture;
        $fixture->setup = $output['setup_directory'];
        $fixture->teardown = $output['teardown_directory'];
        $fixture->runs = $output['runs'];
    }
    return $fixture;
}


/**
 * @param BufferingLogger $logger
 * @param string $filepath
 * @param ?callable-string[] $fixtures
 * @return false|null|callable-string
 */
function _validate_fixture(BufferingLogger $logger, $filepath, $fixtures)
{
    $result = null;
    if ($fixtures)
    {
        if (\count($fixtures) > 1)
        {
            namespace\_log_fixture_error($logger, $filepath, $fixtures);
            $result = false;
        }
        else
        {
            $result = $fixtures[0];
        }
    }
    return $result;
}


/**
 * @param State $state
 * @param BufferingLogger $logger
 * @param string $filepath
 * @param array{'name'?: string,
 *              'setup'?: callable-string[],
 *              'teardown'?: callable-string[]}[] $runs
 * @return false|RunInfo[]
 */
function _validate_runs(State $state, BufferingLogger $logger, $filepath, $runs)
{
    $valid = true;
    $result = array();
    if ($runs)
    {
        foreach ($runs as $run_info)
        {
            $teardown = null;
            if (isset($run_info['teardown']))
            {
                $teardowns = $run_info['teardown'];
                if (\count($teardowns) > 1)
                {
                    namespace\_log_fixture_error($logger, $filepath, $teardowns);
                    $valid = false;
                }
                elseif (!isset($run_info['setup']))
                {
                    $message = \sprintf(
                        "Teardown run function '%s' has no matching setup run function",
                        $teardowns[0]
                    );
                    $logger->log_error($filepath, $message);
                    $valid = false;
                }
                else
                {
                    $teardown = $teardowns[0];
                }
            }
            if (isset($run_info['setup']))
            {
                \assert(isset($run_info['name']));
                $setups = $run_info['setup'];
                if (\count($setups) > 1)
                {
                    namespace\_log_fixture_error($logger, $filepath, $setups);
                    $valid = false;
                }
                else
                {
                    $result[] = namespace\_new_run(
                        $state,
                        $run_info['name'], $run_info['setup'][0], $teardown);
                }
            }
        }
    }

    return $valid ? $result : false;
}


/**
 * @param State $state
 * @param int $parent_group_id
 * @return int
 */
function _new_run_group(State $state, $parent_group_id)
{
    $id = \count($state->groups);
    $groups = $state->groups[$parent_group_id];
    $groups[] = $id;
    $state->groups[$id] = $groups;
    return $id;
}


/**
 * @param string $name
 * @param callable-string $setup
 * @param ?callable-string $teardown
 * @return RunInfo
 */
function _new_run(State $state, $name, $setup, $teardown)
{
    $run = new RunInfo;
    $run->id = \count($state->runs) + 1;
    $run->name = $name;
    $run->setup = $setup;
    $run->teardown = $teardown;
    $state->runs[] = $run;
    return $run;
}


final class _TokenIterator extends struct {
    /** @var array<string|array{int, string, int}> */
    public $tokens;

    /** @var int */
    public $count;

    /** @var int */
    public $pos;
}


interface _Token {}


final class _ClassToken extends struct implements _Token {
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


final class _FunctionToken extends struct implements _Token {
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


final class _NamespaceToken extends struct implements _Token {
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
 * @param BufferingLogger $logger
 * @param string $filepath
 * @return ?_TokenIterator
 */
function _new_token_iterator(BufferingLogger $logger, $filepath)
{
    $iterator = null;
    $source = namespace\_read_file($logger, $filepath);
    if ($source)
    {
        $iterator = new _TokenIterator;
        // @bc 5.6 Check if token_get_all accepts optional $flags parameter
        // @bc 7.4 Use token_get_all instead of PhpToken object interface(?)
        $iterator->tokens = \version_compare(\PHP_VERSION, '7.0', '<')
                          ? \token_get_all($source)
                          : \token_get_all($source, \TOKEN_PARSE);
        // Start with $i = 2 since all PHP code starts with '<?php' followed by
        // whitespace
        $iterator->pos = 2;
        $iterator->count = \count($iterator->tokens);
    }

    return $iterator;
}


/**
 * @param _TokenIterator $iterator
 * @return ?_Token
 */
function _next_token(_TokenIterator $iterator)
{
    $result = null;
    while (!$result && $iterator->pos < $iterator->count)
    {
        $token = $iterator->tokens[$iterator->pos++];
        if (!\is_array($token))
        {
            continue;
        }

        if (\T_CLASS === $token[0])
        {
            $name = namespace\_parse_identifier($iterator);
            if ($name)
            {
                $result = new _ClassToken($name);
            }
        }
        elseif (\T_FUNCTION === $token[0])
        {
            $name = namespace\_parse_identifier($iterator);
            if ($name)
            {
                $result = new _FunctionToken($name);
            }
        }
        elseif (\T_NAMESPACE === $token[0])
        {
            $name = namespace\_parse_namespace($iterator);
            if (null !== $name)
            {
                $result = new _NamespaceToken($name);
            }
        }
        elseif (\T_USE === $token[0])
        {
            // don't discover 'use function <function name>', etc.
            namespace\_consume_statement($iterator);
        }
        elseif (
            (\T_INTERFACE === $token[0]) || (\T_ABSTRACT === $token[0])
            // @bc 5.3 Check if T_TRAIT is defined
            || (\defined('T_TRAIT') && (\T_TRAIT === $token[0]))
            // @bc 8.0 Check if T_ENUM is defined
            || (\defined('T_ENUM') && (\T_ENUM === $token[0])))
        {
            // don't discover non-instantiable classes or the functions of
            // non-class definitions
            namespace\_consume_definition($iterator);
        }
    }

    return $result;
}


/**
 * @param string $filepath
 * @return string|false
 */
function _read_file(BufferingLogger $logger, $filepath)
{
    $source = false;

    // First include the file to ensure it parses correctly
    namespace\start_buffering($logger, $filepath);
    $included = namespace\_include_file($logger, $filepath);
    namespace\end_buffering($logger);

    if ($included)
    {
        try
        {
            $source = \file_get_contents($filepath);
            if (false === $source)
            {
                // file_get_contents() can return false if it fails. Presumably
                // an error/exception would have been generated, so we would
                // never get here, but the documentation isn't explicit
                $logger->log_error($filepath, 'Unable to read file');
            }
        }
        // @bc 5.6 Catch Exception
        catch (\Exception $e)
        {
            $logger->log_error($filepath, 'Unable to read file: ' . $e->getMessage());
        }
        catch (\Throwable $e)
        {
            $logger->log_error($filepath, 'Unable to read file: ' . $e->getMessage());
        }
    }

    return $source;
}


/**
 * @param string $file
 * @return bool
 */
function _include_file(Logger $logger, $file)
{
    $included = false;
    try
    {
        namespace\_guard_include($file);
        $included = true;
    }
    // @bc 5.6 Catch Exception
    catch (\Exception $e)
    {
        $logger->log_error($file, $e);
    }
    catch (\Throwable $e)
    {
        $logger->log_error($file, $e);
    }

    return $included;
}


/**
 * @param string $file
 * @return void
 */
function _guard_include($file)
{
    // Isolate included files to prevent them from meddling with local state
    include_once $file;
}


/**
 * @param _TokenIterator $iterator
 * @return ?string
 */
function _parse_identifier(_TokenIterator $iterator)
{
    $identifier = null;
    // $iterator->pos = whitespace after the keyword identifying the type of
    // identifer ('class', 'function', etc.)
    while (true)
    {
        $token = $iterator->tokens[++$iterator->pos];
        if ('{' === $token)
        {
            break;
        }
        if (\is_array($token) && \T_STRING === $token[0])
        {
            $identifier = $token[1];
            break;
        }
    }

    namespace\_consume_definition($iterator);
    return $identifier;
}


/**
 * @param _TokenIterator $iterator
 * @return ?string
 */
function _parse_namespace(_TokenIterator $iterator)
{
    // There are two options:
    //
    // 1) This is a namespace declaration, which takes two forms:
    //      namespace identifier;
    //      namespace identifier { ... }
    //    In the second case, the identifier is optional
    //
    // 2) This is a use of the namespace operator, which takes the form:
    //      namespace\identifier
    //
    // Consequently, if the namespace separator '\' is the first non-whitespace
    // token found after the 'namespace' keyword, this isn't a namespace
    // declaration. Otherwise, everything until the terminating ';' or '{'
    // constitutes the identifier.
    $namespace = '';
    while (true)
    {
        $token = $iterator->tokens[$iterator->pos++];
        if ((';' === $token) || ('{' === $token))
        {
            break;
        }
        if (!\is_array($token))
        {
            continue;
        }

        // @bc 7.4 Build namespace name from its individual tokens
        if (\version_compare(\PHP_VERSION, '8.0', '<'))
        {
            // PHP < 8 tokenizes namespace declarations as one or more
            // identifiers (T_STRING) separated by the namespace separator
            // (T_NS_SEPARATOR)
            if (\T_NS_SEPARATOR === $token[0])
            {
                if (0 === \strlen($namespace))
                {
                    $namespace = null;
                    break;
                }
                else
                {
                    $namespace .= $token[1];
                }
            }
            elseif (\T_STRING === $token[0])
            {
                $namespace .= $token[1];
            }
        }
        else
        {
            // PHP >= 8 tokenizes namespace declarations as one token, either
            // T_STRING if it's just a single identifier, or T_NAME_QUALIFIED
            // if the declaration includes subnamespaces. Consequently, we
            // should only ever see a namespace separator (T_NS_SEPARATOR) if
            // this is the namespace operator
            if ((\T_NAME_QUALIFIED === $token[0]) || (\T_STRING === $token[0]))
            {
                \assert(0 === \strlen($namespace));
                $namespace = $token[1];
            }
            elseif (\T_NS_SEPARATOR === $token[0])
            {
                \assert(0 === \strlen($namespace));
                $namespace = null;
                break;
            }
        }
    }

    if ($namespace)
    {
        $namespace .= '\\';
    }
    return $namespace;
}


/**
 * @param _TokenIterator $iterator
 * @return void
 */
function _consume_definition(_TokenIterator $iterator)
{
    while ('{' !== $iterator->tokens[$iterator->pos++]);
    $scope = 1;
    while ($scope)
    {
        $token = $iterator->tokens[$iterator->pos++];
        if ('{' === $token)
        {
            ++$scope;
        }
        elseif ('}' === $token)
        {
            --$scope;
        }
    }
}


/**
 * @param _TokenIterator $iterator
 * @return void
 */
function _consume_statement(_TokenIterator $iterator)
{
    while (';' !== $iterator->tokens[$iterator->pos++]);
}



/**
 * @param string $source
 * @param string[] $fixtures
 * @return void
 */
function _log_fixture_error(Logger $logger, $source, $fixtures)
{
    $message = 'Multiple conflicting fixtures found:';
    foreach ($fixtures as $i => $fixture)
    {
        ++$i;
        $message .= "\n    {$i}) {$fixture}";
    }
    $logger->log_error($source, $message);
}
