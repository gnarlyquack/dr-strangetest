<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


const RESULT_PASS     = 0x0;
const RESULT_FAIL     = 0x1;
const RESULT_POSTPONE = 0x2;


final class Context {
    private $state;
    private $logger;
    private $test;
    private $run;
    private $result = namespace\RESULT_PASS;
    private $teardowns = array();

    public function __construct(State $state, Logger $logger,
        FunctionTest $test, $run
    ) {
        $this->state = $state;
        $this->logger = $logger;
        $this->test = $test;
        $this->run = $run;
    }


    public function __call($name, $args) {
        // This works, and will automatically pick up new assertions, but it'd
        // probably be best to implement actual class methods
        if ('assert' === \substr($name, 0, 6)
            && \function_exists("easytest\\$name"))
        {
            try {
                // #BC(5.5): Use proxy function for argument unpacking
                $value = namespace\_unpack_function("easytest\\$name", $args);
                return array(namespace\RESULT_PASS, $value);
            }
            catch (\AssertionError $e) {
                $this->logger->log_failure($this->test->name, $e);
            }
            // #BC(5.6): Catch Failure
            catch (Failure $e) {
                $this->logger->log_failure($this->test->name, $e);
            }
            $this->result = namespace\RESULT_FAIL;
            return array(namespace\RESULT_FAIL, null);
        }

        throw new \Exception(
            \sprintf('Call to undefined method %s::%s()', __CLASS__, $name)
        );
    }


    public function subtest($callable) {
        try {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            $value = \call_user_func($callable);
            return array(namespace\RESULT_PASS, $value);
        }
        catch (\AssertionError $e) {
            $this->logger->log_failure($this->test->name, $e);
        }
        // #BC(5.6): Catch Failure
        catch (Failure $e) {
            $this->logger->log_failure($this->test->name, $e);
        }
        $this->result = namespace\RESULT_FAIL;
        return array(namespace\RESULT_FAIL, null);
    }


    public function teardown($callable) {
        $this->teardowns[] = $callable;
    }


    public function depends($name) {
        $dependees = array();
        // #BC(5.5): Use func_get_args instead of argument unpacking
        foreach (\func_get_args() as $name) {
            list($name, $run) = $this->normalize_name($name);

            if (!isset($this->state->results[$name][$run])) {
                $dependees[] = $name;
            }
            elseif (!$this->state->results[$name][$run]) {
                throw new Skip("This test depends on '{$name}{$run}', which did not pass");
            }
        }

        if ($dependees) {
            if (!isset($this->state->depends[$this->test->name])) {
                $dependency = new Dependency();
                $dependency->file = $this->test->file;
                $dependency->class = $this->test->class;
                $dependency->function = $this->test->function;
                $this->state->depends[$this->test->name] = $dependency;
            }
            else {
                $dependency = $this->state->depends[$this->test->name];
            }

            foreach ($dependees as $dependee) {
                if (!isset($dependency->dependees[$dependee])) {
                    $dependency->dependees[$dependee] = array();
                }
                $dependency->dependees[$dependee][] = $run;
            }

            throw new Postpone();
        }
    }


    public function set($value) {
        $this->state->fixture[$this->test->name][$this->run] = $value;
    }


    public function get($name) {
        list($name, $run) = $this->normalize_name($name);
        return $this->state->fixture[$name][$run];
    }


    public function result() {
        return $this->result;
    }


    public function teardowns() {
        return $this->teardowns;
    }


    private function normalize_name($name) {
        if (
            !\preg_match(
                '~^(\\\\?(?:\\w+\\\\)*)?(\\w*::)?(\\w+)\\s*(\\((.*)\\))?$~',
                $name,
                $matches
            )
        ) {
            \trigger_error("Invalid test name: $name");
        }

        list(, $namespace, $class, $function) = $matches;

        if (!$namespace) {
            if (!$class && $this->test->class) {
                // the namespace is already included in the class name
                $class = $this->test->class;
            }
            else {
                $namespace = $this->test->namespace;
                if ($class) {
                    $class = \rtrim($class, ':');
                }
            }
        }
        else {
            $namespace = \ltrim($namespace, '\\');
        }

        if ($class) {
            $class .= '::';
        }

        $name = $namespace . $class . $function;

        if (isset($matches[4])) {
            $run = ('' === $matches[5]) ? $matches[5] : " {$matches[4]}";
        }
        else {
            $run = $this->run;
        }

        return array($name, $run);
    }
}


function run_test(
    State $state, BufferingLogger $logger, $test,
    $args = null, array $run_id = null, array $targets = null
) {
    if ($targets) {
        $type = \get_class($test);
        switch ($type) {
        case 'easytest\\DirectoryTest':
            $targets = namespace\find_directory_targets($logger, $test, $targets);
            break;

        case 'easytest\\FileTest':
            $targets = namespace\find_file_targets($logger, $test, $targets);
            break;

        case 'easytest\\ClassTest':
            $targets = namespace\find_class_targets($logger, $test, $targets);
            break;

        default:
            // #BC(5.4): Omit description from assert
            \assert(false); // "Test type '$type' can't have targets"
            break;
        }
        list($error, $targets) = $targets;
        if (!$targets && $error) {
            return;
        }
    }

    $run_name = $run_id ? \sprintf(' (%s)', \implode(', ', $run_id)) : '';
    list($result, $args) = $test->setup($logger, $args, $run_name);
    if (namespace\RESULT_PASS !== $result) {
        return;
    }

    $update_run = false;
    if ($args instanceof ArgumentLists) {
        $arglists = $args->arglists();
        $update_run = true;
    }
    else {
        $arglists = array($args);
    }

    foreach ($arglists as $i => $arglist) {
        if ($arglist && !\is_iterable($arglist)) {
            // $arglists can only be invalid if $params is an instance of
            // ArgumentLists
            $type = \is_object($arglist)
                ? \sprintf('instance of %s', \get_class($arglist) )
                : \sprintf(
                    '%s %s',
                    \gettype($arglist), \var_export($arglist, true));
            $logger->log_error(
                $params->source,
                "Each argument list must be iterable, instead got $type"
            );
            continue;
        }

        $this_run_id = $run_id;
        if ($update_run) {
            $this_run_id[] = $i;
        }

        $test->run($state, $logger, $arglist, $this_run_id, $targets);
        $test->teardown_run($logger, $arglist, $this_run_id);
    }

    $test->teardown($state, $logger, $args, $run_name);
}


function run_directory_setup(
    BufferingLogger $logger,
    DirectoryTest $directory,
    $args = null,
    $run = null
) {
    \assert(
        null === $args
        || \is_array($args)
        || ($args instanceof ArgumentLists)
    );

    if ($directory->setup) {
        $name = "{$directory->setup}{$run}";
        namespace\start_buffering($logger, $name);
        $result = namespace\_run_setup($logger, $name, $directory->setup, $args);
        namespace\end_buffering($logger);
    }
    else {
        $result = array(namespace\RESULT_PASS, $args);
    }
    return $result;
}


function run_directory_tests(
    State $state, BufferingLogger $logger, DirectoryTest $directory,
    array $arglist = null, array $run_id = null, array $targets = null
) {
    $run_name = $run_id ? \sprintf(' (%s)', \implode(', ', $run_id)) : '';

    if ($targets) {
        foreach ($targets as $target) {
            namespace\_run_directory_test(
                $state, $logger, $directory, $target->name(), $arglist, $run_id, $target->subtargets()
            );
        }
    }
    else {
        foreach ($directory->tests as $test => $_) {
            namespace\_run_directory_test(
                $state, $logger, $directory, $test, $arglist, $run_id
            );
        }
    }
}


function _run_directory_test(
    State $state, BufferingLogger $logger, DirectoryTest $directory, $test,
    $arglist = null, array $run_id = null, array $targets = null
) {
    $type = $directory->tests[$test];
    switch ($type) {
    case namespace\TYPE_DIRECTORY:
        $test = namespace\discover_directory($state, $logger, $test);
        break;

    case namespace\TYPE_FILE:
        $test = namespace\discover_file($state, $logger, $test);
        break;

    default:
        // #BC(5.4): Omit description from assert
        \assert(false); // "Unknown directory test type: $type"
        break;
    }

    if ($test) {
        namespace\run_test($state, $logger, $test, $arglist, $run_id, $targets);
    }
}


function run_directory_teardown_run(
    BufferingLogger $logger,
    DirectoryTest $directory,
    $args = null,
    $run = null
) {
    \assert(null === $args || \is_array($args));

    if ($directory->teardown_run) {
        $name = "{$directory->teardown_run}{$run}";
        namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $directory->teardown_run, $args);
        namespace\end_buffering($logger);
    }
}


function run_directory_teardown(
    BufferingLogger $logger,
    DirectoryTest $directory,
    $args = null,
    $run = null
) {
    \assert(
        null === $args
        || \is_array($args)
        || ($args instanceof ArgumentLists)
    );

    if ($directory->teardown) {
        $name = "{$directory->teardown}{$run}";
        namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $directory->teardown, $args);
        namespace\end_buffering($logger);
    }
}


function run_file_setup(
    BufferingLogger $logger,
    FileTest $file,
    array $args = null,
    $run = null
) {
    if ($file->setup) {
        $name = "{$file->setup}{$run}";
        namespace\start_buffering($logger, $name);
        $result = namespace\_run_setup($logger, $name, $file->setup, $args);
        namespace\end_buffering($logger);
    }
    else {
        $result = array(namespace\RESULT_PASS, $args);
    }
    return $result;
}


function run_file_tests(
    State $state, BufferingLogger $logger, FileTest $file,
    array $arglist = null, array $run_id = null, array $targets = null
) {
    $run_name = $run_id ? \sprintf(' (%s)', \implode(', ', $run_id)) : '';

    if ($targets) {
        foreach ($targets as $target) {
            namespace\_run_file_test(
                $state, $logger, $file, $target->name(), $arglist, $run_id, $target->subtargets()
            );
        }
    }
    else {
        foreach ($file->tests as $test => $_) {
            namespace\_run_file_test(
                $state, $logger, $file, $test, $arglist, $run_id
            );
        }
    }
}


function _run_file_test(
    State $state, BufferingLogger $logger, FileTest $file, $test,
    $arglist = null, array $run_id = null, array $targets = null
) {
    $info = $file->tests[$test];
    switch ($info->type) {
    case namespace\TYPE_CLASS:
        $test = namespace\discover_class($state, $logger, $info);
        break;

    case namespace\TYPE_FUNCTION:
        $test = new FunctionTest();
        $test->file = $info->filename;
        $test->namespace = $info->namespace;
        $test->function = $info->name;
        $test->name = $info->name;
        $test->test = $info->name;
        if ($file->setup_function) {
            $test->setup_name = $file->setup_function_name;
            $test->setup = $file->setup_function;
        }
        if ($file->teardown_function) {
            $test->teardown_name = $file->teardown_function_name;
            $test->teardown = $file->teardown_function;
        }
        break;

    default:
        // #BC(5.4): Omit description from assert
        \assert(false); // "Unknown file test type: $type"
        break;
    }

    if ($test) {
        namespace\run_test(
            $state, $logger, $test, $arglist, $run_id, $targets
        );
    }
}


function run_file_teardown_run(
    BufferingLogger $logger,
    FileTest $file,
    $args = null,
    $run = null
) {
    \assert(null === $args || \is_array($args));

    if ($file->teardown_run) {
        $name = "{$file->teardown_run}{$run}";
        namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $file->teardown_run, $args);
        namespace\end_buffering($logger);
    }
}


function run_file_teardown(
    BufferingLogger $logger,
    FileTest $file,
    $args = null,
    $run = null
) {
    \assert(
        null === $args
        || \is_array($args)
        || ($args instanceof ArgumentLists)
    );

    if ($file->teardown) {
        $name = "{$file->teardown}{$run}";
        namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $file->teardown, $args);
        namespace\end_buffering($logger);
    }
}


function run_class_setup(
    BufferingLogger $logger,
    ClassTest $class,
    array $args = null,
    $run = null
) {
    namespace\start_buffering($logger, $class->name);
    $class->object = namespace\_instantiate_test($logger, $class->name, $args);
    namespace\end_buffering($logger);
    if (!$class->object) {
        return array(namespace\RESULT_FAIL, null);
    }

    $result = array(namespace\RESULT_PASS, null);
    if ($class->setup) {
        $name = "{$class->name}::{$class->setup}{$run}";
        $method = array($class->object, $class->setup);
        namespace\start_buffering($logger, $name);
        list($result[0],) = namespace\_run_setup($logger, $name, $method);
        namespace\end_buffering($logger);
    }
    return $result;
}


function run_class_tests(
    State $state, BufferingLogger $logger, ClassTest $class,
    array $arglist = null, array $run_id = null, array $targets = null
) {
    if ($targets) {
        foreach ($targets as $test) {
            namespace\_run_class_test($state, $logger, $class, $test, $run_id);
        }
    }
    else {
        foreach ($class->tests as $test) {
            namespace\_run_class_test($state, $logger, $class, $test, $run_id);
        }
    }
}


function _run_class_test(
    State $state, BufferingLogger $logger, ClassTest $class, $method,
    array $run_id = null, array $targets = null
) {
    $test = new FunctionTest();
    $test->file = $class->file;
    $test->namespace = $class->namespace;
    $test->class = $class->name;
    $test->function =  $method;
    $test->name = "{$class->name}::{$method}";
    $test->test = array($class->object, $method);
    if ($class->setup_function) {
        $test->setup = array($class->object, $class->setup_function);
        $test->setup_name = $class->setup_function;
    }
    if ($class->teardown_function) {
        $test->teardown = array($class->object, $class->teardown_function);
        $test->teardown_name = $class->teardown_function;
    }
    namespace\run_test($state, $logger, $test, null, $run_id);
}


function _instantiate_test(Logger $logger, $class, $args) {
    try {
        if ($args) {
            // #BC(5.5): Use proxy function for argument unpacking
            return namespace\_unpack_construct($class, $args);
        }
        else {
            return new $class();
        }
    }
    catch (\Throwable $e) {
        $logger->log_error($class, $e);
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($class, $e);
    }
    return false;
}


function run_class_teardown(BufferingLogger $logger, ClassTest $class, $run) {
    if ($class->teardown) {
        $name = "{$class->name}::{$class->teardown}{$run}";
        $method = array($class->object, $class->teardown);
        namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $method);
        namespace\end_buffering($logger);
    }
}


function run_function_setup(
    BufferingLogger $logger,
    FunctionTest $test,
    array $args = null,
    $run = null
) {
    if ($test->setup) {
        $name = "{$test->setup_name} for {$test->name}{$run}";
        namespace\start_buffering($logger, $name);
        $result = namespace\_run_setup($logger, $name, $test->setup, $args);
        if (namespace\RESULT_PASS !== $result[0]) {
            namespace\end_buffering($logger);
        }
    }
    else {
        $result = array(namespace\RESULT_PASS, $args);
    }
    $test->result = $result[0];
    return $result;
}


function run_function_test(
    State $state, BufferingLogger $logger, FunctionTest $test,
    array $arglist = null, array $run_id = null, array $targets = null
) {
    // #BC(5.4): Omit description from assert
    \assert(!$targets); // function tests can't have targets
    \assert($test->result === namespace\RESULT_PASS);

    $run_name = $run_id ? \sprintf(' (%s)', \implode(', ', $run_id)) : '';
    $test_name = "{$test->name}{$run_name}";

    namespace\start_buffering($logger, $test_name);
    $context = new Context($state, $logger, $test, $run_name);
    $test->result = namespace\_run_test_function(
        $logger, $test_name, $test->test, $context, $arglist
    );

    foreach($context->teardowns() as $teardown) {
        $test->result |= namespace\_run_teardown($logger, $test_name, $teardown);
    }
}


function run_function_teardown(
    State $state,
    BufferingLogger $logger,
    FunctionTest $test,
    array $args = null,
    $run = null
) {
    $test_name = "{$test->name}{$run}";

    if ($test->teardown) {
        $name = "{$test->teardown_name} for {$test_name}";
        namespace\start_buffering($logger, $name);
        $test->result |= namespace\_run_teardown($logger, $name, $test->teardown, $args);
    }
    namespace\end_buffering($logger);

    if (namespace\RESULT_POSTPONE === $test->result) {
        return;
    }
    if (!isset($state->results[$test->name])) {
        $state->results[$test->name] = array('' => true);
    }
    if (namespace\RESULT_PASS === $test->result) {
        $logger->log_pass($test_name);
        $state->results[$test->name][$run] = true;
        $state->results[$test->name][''] = $state->results[$test->name][''];
    }
    elseif (namespace\RESULT_FAIL & $test->result) {
        $state->results[$test->name][$run] = false;
        $state->results[$test->name][''] = false;
        if (namespace\RESULT_POSTPONE & $test->result) {
            unset($state->depends[$test->name]);
        }
    }
}


function _run_setup(Logger $logger, $name, $callable, array $args = null) {
    try {
        if ($args) {
            // #BC(5.5): Use proxy function for argument unpacking
            $result = namespace\_unpack_function($callable, $args);
        }
        else {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            $result = \call_user_func($callable);
        }
        return array(namespace\RESULT_PASS, $result);
    }
    catch (Skip $e) {
        $logger->log_skip($name, $e);
    }
    catch (\Throwable $e) {
        $logger->log_error($name, $e);
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($name, $e);
    }
    return array(namespace\RESULT_FAIL, null);
}


function _run_test_function(
    Logger $logger, $name, $callable, Context $context, array $args = null
) {
    try {
        if ($args) {
            $args[] = $context;
            // #BC(5.5): Use proxy function for argument unpacking
            namespace\_unpack_function($callable, $args);
        }
        else {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            \call_user_func($callable, $context);
        }
        $result = $context->result();
    }
    catch (\AssertionError $e) {
        $logger->log_failure($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    // #BC(5.6): Catch Failure
    catch (Failure $e) {
        $logger->log_failure($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    catch (Skip $e) {
        $logger->log_skip($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    catch (Postpone $_) {
        $result = namespace\RESULT_POSTPONE;
    }
    catch (\Throwable $e) {
        $logger->log_error($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    return $result;
}


function _run_teardown(Logger $logger, $name, $callable, $args = null) {
    try {
        if (!$args) {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            \call_user_func($callable);
        }
        elseif ($args instanceof ArgumentLists) {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            \call_user_func($callable, $args->arglists());
        }
        else {
            // #BC(5.5): Use proxy function for argument unpacking
            namespace\_unpack_function($callable, $args);
        }
        return namespace\RESULT_PASS;
    }
    catch (\Throwable $e) {
        $logger->log_error($name, $e);
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($name, $e);
    }
    return namespace\RESULT_FAIL;
}
