<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


/**
 * @param string $path
 * @return null|false|DirectoryTest|TestRunGroup    Returns a test if tests
 *                                                  were found. Returns null if
 *                                                  no tests were found.
 *                                                  Returns false is no tests
 *                                                  were found and an error
 *                                                  occurred during discovery.
 */
function discover_tests(State $state, BufferingLogger $logger, $path)
{
    return namespace\_discover_directory(new _DiscoveryState($state, $logger), $path, 0);
}


const _TEST_ATTRIBUTE = 'strangetest\\attribute\\Test';


final class _DiscoveryState extends struct
{
    /** @var array<string, true> */
    public $seen = array();

    /** @var State */
    public $global;

    /** @var BufferingLogger */
    public $logger;


    public function __construct(State $state, BufferingLogger $logger)
    {
        $this->global = $state;
        $this->logger = $logger;
    }
}


/**
 * @param string $dirpath
 * @param int $run_group_id
 * @return null|false|DirectoryTest|TestRunGroup    Returns a test if tests
 *                                                  were found. Returns null if
 *                                                  no tests were found.
 *                                                  Returns false is no tests
 *                                                  were found and an error
 *                                                  happened during discovery.
 */
function _discover_directory(_DiscoveryState $state, $dirpath, $run_group_id)
{
    \assert(\DIRECTORY_SEPARATOR === \substr($dirpath, -1));

    $directory = new DirectoryTest;
    $directory->name = $dirpath;
    $directory->run_group_id = $run_group_id;
    unset($run_group_id);

    $run_group = new TestRunGroup;
    $run_group->path = $directory->name;
    $run_group->tests = $directory;

    $setup_filename = null;
    $tests = array();
    $valid = true;
    foreach (new \DirectoryIterator($dirpath) as $file)
    {
        $filename = $file->getBasename();
        $filepath = $file->getPathname();

        // @todo Is explicitly checking for a symbolic link necessary?
        // DirectoryIterator isFile() is only supposed to return true if it's a
        // regular file (i.e., neither a directory nor a link), but it's not
        // clear if isDir() behaves similarly
        if ($file->isLink())
        {
            continue;
        }
        elseif ($file->isFile())
        {
            if (0 === \strcasecmp($filename, 'setup.php'))
            {
                if ($setup_filename === null)
                {
                    $setup_filename = $filename;
                    if (!namespace\_discover_directory_setup($state, $directory, $run_group, $filepath))
                    {
                        $valid = false;
                    }
                }
                else
                {
                    $valid = false;
                    $message = \sprintf(
                        'Found multiple directory setup files: %s and %s',
                        $setup_filename, $filename);
                    $state->logger->log_error($dirpath, $message);
                }
            }
            elseif ((0 === \substr_compare($filename, 'test', 0, 4, true))
                && (0 === \strcasecmp($file->getExtension(), 'php')))
            {
                $tests[$filepath] = true;
            }
        }
        elseif ($file->isDir())
        {
            if (0 === \substr_compare($filename, 'test', 0, 4, true))
            {
                // Ensure directory names end with a directory separator to
                // ensure we can only match against full directory names
                \assert(\DIRECTORY_SEPARATOR !== \substr($filepath, -1));
                $filepath .= \DIRECTORY_SEPARATOR;
                $tests[$filepath] = false;
            }
        }
    }
    unset($setup_filename, $filename, $filepath);

    $result = null;
    if ($valid)
    {
        foreach ($tests as $name => $is_file)
        {
            if ($is_file)
            {
                $test = namespace\_discover_file($state, $name, $directory->run_group_id);
            }
            else
            {
                $test = namespace\_discover_directory($state, $name, $directory->run_group_id);
            }

            if ($test)
            {
                $directory->tests[$name] = $test;
            }
            elseif ($test === false)
            {
                $valid = false;
            }
        }

        if ($directory->tests)
        {
            $result = $run_group->runs ? $run_group : $directory;
        }
        elseif ($valid)
        {
            $state->logger->log_error($dirpath, 'No tests were found in this directory');
        }
        else
        {
            $result = false;
        }
    }
    else
    {
        $result = false;
    }

    return $result;
}


/**
 * @param string $filepath
 * @return bool
 */
function _discover_directory_setup(_DiscoveryState $state, DirectoryTest $directory, TestRunGroup $run_group, $filepath)
{
    $iterator = namespace\_new_token_iterator($state->logger, $filepath);
    if (!$iterator)
    {
        return false;
    }

    $namespace = '';
    $valid = true;
    while ($token = namespace\_next_token($iterator))
    {
        if ($token instanceof _FunctionToken)
        {
            $function_name = "{$namespace}{$token->name}";
            // classes and functions can have identical names
            $function_index = "function {$function_name}";

            if (!isset($state->seen[$function_index]) && \is_callable($function_name))
            {
                $function = new \ReflectionFunction($function_name);
                if ($token->line === $function->getStartLine() && $filepath === $function->getFileName())
                {
                    $state->seen[$function_index] = true;

                    $lexer = new StringLexer($token->name);
                    if ($lexer->eat_string('setup'))
                    {
                        $lexer->eat_underscore();
                        if ($lexer->eat_string('run'))
                        {
                            $lexer->eat_underscore();
                            if (!namespace\_validate_run_fixture($state->logger, $filepath, $run_group, $function, $lexer->get_remainder(), true))
                            {
                                $valid = false;
                            }
                        }
                        else
                        {
                            if (!namespace\_validate_fixture($state->logger, $filepath, $function, $directory->setup))
                            {
                                $valid = false;
                            }
                        }
                    }
                    elseif ($lexer->eat_string('teardown'))
                    {
                        $lexer->eat_underscore();
                        if ($lexer->eat_string('run'))
                        {
                            $lexer->eat_underscore();
                            if (!namespace\_validate_run_fixture($state->logger, $filepath, $run_group, $function, $lexer->get_remainder(), false))
                            {
                               $valid = false;
                            }
                        }
                        else
                        {
                            if (!namespace\_validate_fixture($state->logger, $filepath, $function, $directory->teardown))
                            {
                                $valid = false;
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

    if (!namespace\_validate_runs($state, $run_group, $filepath))
    {
        $valid = false;
    }
    return $valid;
}


/**
 * @param string $filepath
 * @param int $run_group_id
 * @return null|false|FileTest|TestRunGroup Returns a test class if tests were
 *                                          found. Returns null if no tests
 *                                          were found. Returns false if no
 *                                          tests were found and an error
 *                                          occurred during discovery.
 */
function _discover_file(_DiscoveryState $state, $filepath, $run_group_id)
{
    $iterator = namespace\_new_token_iterator($state->logger, $filepath);
    if (!$iterator)
    {
        return false;
    }

    $file = new FileTest;
    $file->name = $filepath;
    $file->run_group_id = $run_group_id;
    unset($run_group_id);

    $run_group = new TestRunGroup;
    $run_group->path = $file->name;
    $run_group->tests = $file;

    $namespace = '';
    $tests = array();
    $valid = true;
    while ($token = namespace\_next_token($iterator))
    {
        if ($token instanceof _ClassToken)
        {
            $class_name = "{$namespace}{$token->name}";
            // classes and functions can have the same name!
            $test_index = "class {$class_name}";

            if (!isset($state->seen[$test_index]) && \class_exists($class_name))
            {
                $class = new \ReflectionClass($class_name);
                if ($token->line === $class->getStartLine() && $filepath === $class->getFileName())
                {
                    $state->seen[$test_index] = true;

                    if (0 === \substr_compare($token->name, 'test', 0, 4, true)
                        // @bc 7.4 Check that attributes exist
                        || (\version_compare(\PHP_VERSION, '8.0.0', '>=')
                            && $class->getAttributes(namespace\_TEST_ATTRIBUTE)))
                    {
                        $tests[$test_index] = $class;
                    }
                }
            }
        }
        elseif ($token instanceof _FunctionToken)
        {
            $function_name = "{$namespace}{$token->name}";
            // classes and functions can have the same name!
            $test_index = "function {$function_name}";

            if (!isset($state->seen[$test_index]) && \is_callable($function_name))
            {
                $function = new \ReflectionFunction($function_name);
                if ($token->line === $function->getStartLine() && $filepath === $function->getFileName())
                {
                    $state->seen[$test_index] = true;

                    $lexer = new StringLexer($token->name);
                    if ($lexer->eat_string('test')
                        // @bc 7.4 Check that attributes exist
                        || (\version_compare(\PHP_VERSION, '8.0.0', '>=')
                            && $function->getAttributes(namespace\_TEST_ATTRIBUTE)))
                    {
                        $tests[$test_index] = $function;
                    }
                    elseif ($lexer->eat_string('setup'))
                    {
                        $lexer->eat_underscore();
                        if ($lexer->eat_string('file'))
                        {
                            if (!namespace\_validate_fixture($state->logger, $filepath, $function, $file->setup_file))
                            {
                                $valid = false;
                            }
                        }
                        elseif($lexer->eat_string('run'))
                        {
                            $lexer->eat_underscore();
                            if (!namespace\_validate_run_fixture($state->logger, $filepath, $run_group, $function, $lexer->get_remainder(), true))
                            {
                                $valid = false;
                            }
                        }
                        else
                        {
                            if (!namespace\_validate_fixture($state->logger, $filepath, $function, $file->setup_function))
                            {
                                $valid = false;
                            }
                        }
                    }
                    elseif ($lexer->eat_string('teardown'))
                    {
                        $lexer->eat_underscore();
                        if ($lexer->eat_string('file'))
                        {
                            if (!namespace\_validate_fixture($state->logger, $filepath, $function, $file->teardown_file))
                            {
                                $valid = false;
                            }
                        }
                        elseif ($lexer->eat_string('run'))
                        {
                            $lexer->eat_underscore();
                            if (!namespace\_validate_run_fixture($state->logger, $filepath, $run_group, $function, $lexer->get_remainder(), false))
                            {
                                $valid = false;
                            }
                        }
                        else
                        {
                            if (!namespace\_validate_fixture($state->logger, $filepath, $function, $file->teardown_function))
                            {
                                $valid = false;
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
    unset($namespace, $class_name, $function_name, $test_index, $class, $function, $lexer);

    if (!namespace\_validate_runs($state, $run_group, $filepath))
    {
        $valid = false;
    }

    $result = null;
    if ($valid)
    {
        foreach ($tests as $test_index => $reflected_test)
        {
            if ($reflected_test instanceof \ReflectionClass)
            {
                $test = namespace\_discover_class($state->logger, $reflected_test, $file->run_group_id);
                if ($test)
                {
                    $file->tests[$test_index] = $test;
                }
                elseif ($test === false)
                {
                    $valid = false;
                }
            }
            else
            {
                $test = new FunctionTest;
                $test->name = $reflected_test->getName();
                $test->run_group_id = $file->run_group_id;
                $test->test = $reflected_test;
                $file->tests[$test_index] = $test;
            }
        }

        if ($file->tests)
        {
            $result = $run_group->runs ? $run_group : $file;
        }
        elseif ($valid)
        {
            $state->logger->log_error($filepath, 'No tests were found in this file');
        }
        else
        {
            $result = false;
        }
    }
    else
    {
        $result = false;
    }

    return $result;
}


/**
 * @param \ReflectionClass<object> $reflected_class
 * @param int $run_group_id
 * @return null|false|ClassTest Returns a ClassTest if tests were found.
 *                              Returns null if no tests were found. Returns
 *                              false if no tests were found but an error
 *                              occurred during discovery.
 */
function _discover_class(BufferingLogger $logger, \ReflectionClass $reflected_class, $run_group_id)
{
    $class = new ClassTest;
    $class->test = $reflected_class;
    $class->run_group_id = $run_group_id;
    unset($run_group_id, $reflected_class);

    $valid = true;
    foreach ($class->test->getMethods(\ReflectionMethod::IS_PUBLIC) as $method)
    {
        if ($method->isStatic())
        {
            continue;
        }

        $lexer = new StringLexer($method->getName());

        if ($lexer->eat_string('test')
            // @bc 7.4 Check that attributes exist
            || (\version_compare(\PHP_VERSION, '8.0.0', '>=')
                && $method->getAttributes(namespace\_TEST_ATTRIBUTE)))
        {
            $test = new MethodTest;
            $test->name = "{$class->test->name}::{$method->name}";
            $test->run_group_id = $class->run_group_id;
            $test->test = $method;
            $class->tests[$method->name] = $test;
        }
        elseif ($lexer->eat_string('setup'))
        {
            if ($lexer->eat_remainder(''))
            {
                if (!namespace\_validate_fixture($logger, $class->test->name, $method, $class->setup_method))
                {
                    $valid = false;
                }
            }
            else
            {
                $lexer->eat_underscore();
                if ($lexer->eat_remainder('object'))
                {
                    if (!namespace\_validate_fixture($logger, $class->test->name, $method, $class->setup_object))
                    {
                        $valid = false;
                    }
                }
            }
        }
        elseif ($lexer->eat_string('teardown'))
        {
            if ($lexer->eat_remainder(''))
            {
                if (!namespace\_validate_fixture($logger, $class->test->name, $method, $class->teardown_method))
                {
                    $valid = false;
                }
            }
            else
            {
                $lexer->eat_underscore();
                if ($lexer->eat_remainder('object'))
                {
                    if (!namespace\_validate_fixture($logger, $class->test->name, $method, $class->teardown_object))
                    {
                        $valid = false;
                    }
                }
            }
        }
    }

    $result = null;
    if ($valid)
    {
        if ($class->tests)
        {
            $result = $class;
        }
        else
        {
            $logger->log_error($class->test->name, 'No tests were found in this class');
        }
    }
    else
    {
        $result = false;
    }

    return $result;
}


/**
 * @template T of \ReflectionFunctionAbstract
 * @param string $source
 * @param T $new
 * @param ?T $old
 * @return bool
 */
function _validate_fixture(BufferingLogger $logger, $source, $new, &$old)
{
    $valid = true;

    if ($old)
    {
        $valid = false;
        // @todo Show lines numbers on which duplicate functions were defined
        $message = \sprintf(
            'Multiple fixtures found: %s and %s',
            $old->getName(), $new->getName());
        $logger->log_error($source, $message);
    }
    else
    {
        $old = $new;
    }

    return $valid;
}


/**
 * @param string $filepath
 * @param string $run_name
 * @param bool $setup
 * @return bool
 */
function _validate_run_fixture(BufferingLogger $logger, $filepath, TestRunGroup $run_group, \ReflectionFunction $function, $run_name, $setup)
{
    $valid = true;

    if (0 === \strlen($run_name))
    {
        $valid = false;
        $message = \sprintf(
            'Unable to determine run name from run fixture function %s',
            $function->getName());
        $logger->log_error($filepath, $message);
    }
    else
    {
        // @todo only ascii characters should be lowercased for function names
        $run_index = \strtolower($run_name);

        if (!isset($run_group->runs[$run_index]))
        {
            $run = new TestRun;
            $run->name = $run_name;
            $run_group->runs[$run_index] = $run;
        }
        $run = $run_group->runs[$run_index];

        if ($setup)
        {
            $valid = namespace\_validate_fixture($logger, $filepath, $function, $run->setup);
            }
        else
        {
            $valid = namespace\_validate_fixture($logger, $filepath, $function, $run->teardown);
        }
    }

    return $valid;
}


/**
 * @param string $filepath
 * @return bool
 */
function _validate_runs(_DiscoveryState $state, TestRunGroup $run_group, $filepath)
{
    $valid = true;
    if ($run_group->runs)
    {
        $run_group_id = \count($state->global->groups);
        $groups = $state->global->groups[$run_group->tests->run_group_id];
        $groups[] = $run_group_id;
        $state->global->groups[$run_group_id] = $groups;
        $run_group->tests->run_group_id = $run_group_id;

        foreach ($run_group->runs as $run)
        {
            if (!$run->setup)
            {
                \assert(isset($run->teardown));
                $valid = false;
                $message = \sprintf(
                    "Teardown run function '%s' has no matching setup run function",
                    $run->teardown->getName()
                );
                $state->logger->log_error($filepath, $message);
            }
            else
            {
                $run->id = \count($state->global->runs) + 1;
                $run->tests = $run_group->tests;

                $state->global->runs[] = $run;
            }
        }
    }

    return $valid;
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

    /** @var int */
    public $line;

    /**
     * @param string $name
     * @param int $line
     */
    public function __construct($name, $line)
    {
        $this->name = $name;
        $this->line = $line;
    }
}


final class _FunctionToken extends struct implements _Token {
    /** @var string */
    public $name;

    /** @var int */
    public $line;


    /**
     * @param string $name
     * @param int $line
     */
    public function __construct($name, $line)
    {
        $this->name = $name;
        $this->line = $line;
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
                $result = new _ClassToken($name, $token[2]);
            }
        }
        elseif (\T_FUNCTION === $token[0])
        {
            // @bc 5.6 A function begins on the line of its identifier
            $name = namespace\_parse_identifier($iterator, $line);
            if ($name)
            {
                if (\version_compare(\PHP_VERSION, '7.0.0', '>='))
                {
                   $line = $token[2];
                }
                $result = new _FunctionToken($name, $line);
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
 * @param int $line
 * @return ?string
 */
function _parse_identifier(_TokenIterator $iterator, &$line = null)
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
            // @bc 5.6 A function begins on the line of its identifier
            $line = $token[2];
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


final class StringLexer extends struct
{
    /** @var string */
    private $string;

    /** @var int */
    private $len;

    /** @var int */
    private $offset = 0;


    /**
     * @param string $string
     */
    public function __construct($string)
    {
        $this->string = $string;
        $this->len = \strlen($string);
    }

    /**
     * @param string $string
     * @return bool
     */
    public function eat_string($string)
    {
        $result = false;

        // @bc 7.3 Explicitly handle offset >= string length for substr_compare
        if ($this->offset >= $this->len)
        {
            $result = '' === $string;
        }
        else
        {
            $len = \strlen($string);
            if (0 === \substr_compare($this->string, $string, $this->offset, $len, true))
            {
                $result = true;
                $this->offset += $len;
            }
        }

        return $result;
    }

    /**
     * @return void
     */
    public function eat_underscore()
    {
        if (($this->offset < $this->len) && ('_' === $this->string[$this->offset]))
        {
            ++$this->offset;
        }
    }

    /**
     * @param string $string
     * @return bool
     */
    public function eat_remainder($string)
    {
        // @bc 7.3 Explicitly handle offset >= string length for substr_compare
        if ($this->offset >= $this->len)
        {
            $result = '' === $string;
        }
        else
        {
            // @bc 7.0 Calculate explicit length for substr_compare
            // PHP up through 5.5 throws an error if null is passed for
            // $length. PHP up through 7.0 doesn't throw an error, but silently
            // converts $length to 0, giving an incorrect result.
            $len = \max(\strlen($string), $this->len - $this->offset);
            if (0 === \substr_compare($this->string, $string, $this->offset, $len, true))
            {
                $this->offset = $this->len;
                $result = true;
            }
            else
            {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @return string
     */
    public function get_remainder()
    {
        // @bc 5.6 Ensure we always return a string from substr()
        return (string)\substr($this->string, $this->offset);
    }
}
