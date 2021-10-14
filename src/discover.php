<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


/**
 * @param string $dirpath
 * @param Target[] $targets
 * @return void
 */
function discover_tests(BufferingLogger $logger, $dirpath, array $targets)
{
    $state = new State();
    $directory = namespace\discover_directory($state, $logger, $dirpath);
    if (!$directory)
    {
        return;
    }

    namespace\run_directory_tests($state, $logger, $directory, null, null, $targets);
    while ($state->depends)
    {
        $dependencies = namespace\resolve_dependencies($state, $logger);
        if (!$dependencies)
        {
            break;
        }
        $targets = namespace\build_targets_from_dependencies($dependencies);
        $state->depends = array();
        namespace\run_directory_tests($state, $logger, $directory, null, null, $targets);
    }
}


/**
 * @param string $path
 * @return DirectoryTest|false
 */
function discover_directory(State $state, BufferingLogger $logger, $path)
{
    if (isset($state->directories[$path]))
    {
        return $state->directories[$path];
    }

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

    $directory = false;
    if ($valid)
    {
        $directory = new DirectoryTest();
        $directory->name = $path;
        $directory->tests = $tests;
        if ($setup)
        {
            $directory = namespace\_discover_directory_setup(
                $state, $logger, $directory, $setup[0]);
        }
        else
        {
            $directory->setup = null;
            $directory->teardown = null;
            $directory->setup_runs = null;
            $directory->teardown_runs = null;
        }
    }

    $state->directories[$path] = $directory;
    return $directory;
}


/**
 * @param string $filepath
 * @return FileTest|false
 */
function discover_file(State $state, BufferingLogger $logger, $filepath)
{
    if (isset($state->files[$filepath]))
    {
        return $state->files[$filepath];
    }

    $iterator = namespace\_new_token_iterator($logger, $filepath);
    if (!$iterator)
    {
        return false;
    }

    $namespace = '';
    $tests = array();
    $setup_function = array();
    $setup_function_name = array();
    $setup_file = array();
    $setup_runs = array();
    $teardown_function = array();
    $teardown_function_name = array();
    $teardown_file = array();
    $teardown_runs = array();

    while ($token = namespace\_next_token($iterator))
    {
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
                $tests[$test_name] = $info;
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
                    $tests[$test_name] = $info;
                }
                elseif (\preg_match('~^(setup|teardown)_?(file|runs)?~i', $token->name, $matches))
                {
                    $state->seen[$test_name] = true;

                    if (0 === \strcasecmp('setup', $matches[1]))
                    {
                        if (!isset($matches[2]))
                        {
                            $setup_function[] = $function_name;
                            $setup_function_name[] = $token->name;
                        }
                        elseif (0 === \strcasecmp('runs', $matches[2]))
                        {
                            $setup_runs[] = $function_name;
                        }
                        else
                        {
                            $setup_file[] = $function_name;
                        }
                    }
                    else
                    {
                        if (!isset($matches[2]))
                        {
                            $teardown_function[] = $function_name;
                            $teardown_function_name[] = $token->name;
                        }
                        elseif (0 === \strcasecmp('runs', $matches[2]))
                        {
                            $teardown_runs[] = $function_name;
                        }
                        else
                        {
                            $teardown_file[] = $function_name;
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

    $valid = true;
    if (\count($setup_file) > 1)
    {
        namespace\_log_fixture_error($logger, $filepath, $setup_file);
        $valid = false;
    }
    if (\count($setup_runs) > 1)
    {
        namespace\_log_fixture_error($logger, $filepath, $setup_runs);
        $valid = false;
    }
    if (\count($setup_function) > 1)
    {
        namespace\_log_fixture_error($logger, $filepath, $setup_function);
        $valid = false;
    }
    if (\count($teardown_file) > 1)
    {
        namespace\_log_fixture_error($logger, $filepath, $teardown_file);
        $valid = false;
    }
    if (\count($teardown_runs) > 1)
    {
        namespace\_log_fixture_error($logger, $filepath, $teardown_runs);
        $valid = false;
    }
    if (\count($teardown_function) > 1)
    {
        namespace\_log_fixture_error($logger, $filepath, $teardown_function);
        $valid = false;
    }
    if (!$tests)
    {
        // @todo How should we handle a test file with no tests?
        $valid = false;
    }

    $file = false;
    if ($valid)
    {
        $file = new FileTest();
        $file->name = $filepath;
        $file->tests = $tests;
        $file->setup_function = $setup_function ? $setup_function[0] : null;
        $file->setup_function_name = $setup_function_name ? $setup_function_name[0] : null;
        $file->teardown_function = $teardown_function ? $teardown_function[0] : null;
        $file->teardown_function_name = $teardown_function_name ? $teardown_function_name[0] : null;
        $file->setup = $setup_file ? $setup_file[0] : null;
        $file->teardown = $teardown_file ? $teardown_file[0] : null;
        $file->setup_runs = $setup_runs ? $setup_runs[0] : null;
        $file->teardown_runs = $teardown_runs ? $teardown_runs[0] : null;
    }

    $state->files[$filepath] = $file;
    return $file;
}


/**
 * @return ClassTest|false
 */
function discover_class(State $state, Logger $logger, TestInfo $info)
{
    $classname = $info->name;
    \assert(\class_exists($classname));
    if (isset($state->classes[$classname]))
    {
        return $state->classes[$classname];
    }

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
        $class->file = $info->filename;
        $class->namespace = $info->namespace;
        $class->name = $classname;
        $class->tests = $tests;
        $class->setup = $setup_object ? $setup_object[0] : null;
        $class->teardown = $teardown_object ? $teardown_object[0] : null;
        $class->setup_function = $setup_function;
        $class->teardown_function = $teardown_function;
    }

    $state->classes[$classname] = $class;
    return $class;
}


/**
 * @param State $state
 * @param BufferingLogger $logger
 * @param DirectoryTest $directory
 * @param string $filepath
 * @return DirectoryTest|false
 */
function _discover_directory_setup(
    State $state, BufferingLogger $logger,
    DirectoryTest $directory, $filepath)
{
    $iterator = namespace\_new_token_iterator($logger, $filepath);
    if (!$iterator)
    {
        return false;
    }

    $namespace = '';
    $setup_directory = array();
    $setup_run = array();
    $teardown_directory = array();
    $teardown_run = array();

    while ($token = namespace\_next_token($iterator))
    {
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
            $test_name = "function {$function_name}";
            if (!isset($state->seen[$test_name])
                // To make PHPStan happy, use is_callable instead of function_exists
                && \is_callable($function_name)
                && \preg_match('~^(setup|teardown)_?(runs)?~i', $token->name, $matches))
            {
                $state->seen[$test_name] = true;

                if (0 === \strcasecmp('setup', $matches[1]))
                {
                    if (isset($matches[2]))
                    {
                        $setup_run[] = $function_name;
                    }
                    else
                    {
                        $setup_directory[] = $function_name;
                    }
                }
                else
                {
                    if (isset($matches[2]))
                    {
                        $teardown_run[] = $function_name;
                    }
                    else
                    {
                        $teardown_directory[] = $function_name;
                    }
                }
            }
        }
        elseif ($token instanceof _NamespaceToken)
        {
            $namespace = $token->name;
        }
    }

    $valid = true;
    if (\count($setup_directory) > 1)
    {
        namespace\_log_fixture_error($logger, $filepath, $setup_directory);
        $valid = false;
    }
    if (\count($teardown_directory) > 1)
    {
        namespace\_log_fixture_error($logger, $filepath, $teardown_directory);
        $valid = false;
    }
    if (\count($setup_run) > 1)
    {
        namespace\_log_fixture_error($logger, $filepath, $setup_run);
        $valid = false;
    }
    if (\count($teardown_run) > 1)
    {
        namespace\_log_fixture_error($logger, $filepath, $teardown_run);
        $valid = false;
    }

    if ($valid)
    {
        $directory->setup = $setup_directory ? $setup_directory[0] : null;
        $directory->teardown = $teardown_directory ? $teardown_directory[0] : null;
        $directory->setup_runs = $setup_run ? $setup_run[0] : null;
        $directory->teardown_runs = $teardown_run ? $teardown_run[0] : null;
        return $directory;
    }
    else
    {
        return false;
    }
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
        // @bc 7.4 Use token_get_all instead of PhpToken object interface(?)
        $iterator->tokens = \token_get_all($source);
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
            || (\defined('T_TRAIT') && (\T_TRAIT === $token[0])))
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
    include $file;
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
function _log_fixture_error(Logger $logger, $source, $fixtures) {
    $message = 'Multiple conflicting fixtures found:';
    foreach ($fixtures as $i => $fixture) {
        ++$i;
        $message .= "\n    {$i}) {$fixture}";
    }
    $logger->log_error($source, $message);
}
