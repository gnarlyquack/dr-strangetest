<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


function discover_tests(BufferingLogger $logger, $dirpath, array $targets) {
    $state = new State();
    $directory = namespace\discover_directory($state, $logger, $dirpath);
    if (!$directory) {
        return;
    }

    namespace\run_test($state, $logger, $directory, null, null, $targets);
    while ($state->depends) {
        $dependencies = namespace\resolve_dependencies($state, $logger);
        if (!$dependencies) {
            break;
        }
        $targets = namespace\build_targets_from_dependencies($dependencies);
        $state->depends = array();
        namespace\run_test($state, $logger, $directory, null, null, $targets);
    }
}


function discover_directory(State $state, BufferingLogger $logger, $path) {
    if (isset($state->directories[$path])) {
        return $state->directories[$path];
    }

    $directory = new DirectoryTest();
    $directory->name = $path;
    $error = false;
    foreach (new \DirectoryIterator($path) as $file) {
        $basename = $file->getBasename();
        $pathname = $file->getPathname();
        $type = $file->getType();

        if ('file' === $type) {
            if (0 === \strcasecmp($basename, 'setup.php')) {
                if ($directory->setup) {
                    // Note the error but continue iterating so we can identify
                    // all errors
                    $error = true;
                }
                $directory->setup[] = $pathname;
                continue;
            }
            if ($error) {
                continue;
            }
            if (0 === \substr_compare($basename, 'test', 0, 4, true)
                && 0 === \strcasecmp($file->getExtension(), 'php'))
            {
                $directory->tests[$pathname] = namespace\TYPE_FILE;
            }
            continue;
        }

        if ($error) {
            continue;
        }

        if ('dir' === $type) {
            if (0 === \substr_compare($basename, 'test', 0, 4, true)) {
                // Ensure directory names end with a directory separator to
                // ensure we can only match against full directory names
                $pathname .= \DIRECTORY_SEPARATOR;
                $directory->tests[$pathname] = namespace\TYPE_DIRECTORY;
            }
            continue;
        }
    }

    if ($error) {
        $logger->log_error(
            $path,
            \sprintf(
                "Multiple setup files found:\n\t%s",
                \implode("\n\t", $directory->setup)
            )
        );
        $directory = false;
    }
    elseif (!$directory->tests) {
        // Should this be logged/reported?
        $directory = false;
    }
    elseif ($directory->setup) {
        $setup = $directory->setup[0];
        $directory->setup = null;
        $directory = namespace\_discover_directory_setup(
            $logger, $directory, $setup, $state->seen
        );
    }

    $state->directories[$path] = $directory;
    return $directory;
}


function _discover_directory_setup(
    BufferingLogger $logger, DirectoryTest $directory, $filepath, array &$seen
) {
    $error = 0;
    $checks = array(
        \T_FUNCTION => function($namespace, $function, $fullname) use ($directory, &$error) {
            return namespace\_is_directory_setup_function(
                $directory, $error, $namespace, $function, $fullname
            );
        },
    );
    if (!namespace\_parse_file($logger, $filepath, $checks, $seen)) {
        return false;
    }

    if ($error) {
        if ($error & namespace\ERROR_SETUP) {
            $logger->log_error(
                $filepath,
                \sprintf(
                    "Multiple setup fixtures found:\n\t%s",
                    \implode("\n\t", $directory->setup)
                )
            );
        }
        if ($error & namespace\ERROR_TEARDOWN) {
            $logger->log_error(
                $filepath,
                \sprintf(
                    "Multiple teardown fixtures found:\n\t%s",
                    \implode("\n\t", $directory->teardown)
                )
            );
        }
        if ($error & namespace\ERROR_TEARDOWN_RUN) {
            $logger->log_error(
                $filepath,
                \sprintf(
                    "Multiple teardown fixtures found:\n\t%s",
                    \implode("\n\t", $directory->teardown_run)
                )
            );
        }
        return false;
    }

    $directory->setup = $directory->setup ? $directory->setup[0] : null;
    $directory->teardown = $directory->teardown ? $directory->teardown[0] : null;
    $directory->teardown_run
        = $directory->teardown_run ? $directory->teardown_run[0] : null;
    return $directory;
}


function _is_directory_setup_function(
    DirectoryTest $directory, &$error, $namespace, $function, $fullname
) {
    if (\preg_match('~^(setup|teardown)_?(run)?~i', $function, $matches)) {
        if (0 === \strcasecmp('setup', $matches[1])) {
            if ($directory->setup) {
                $error |= namespace\ERROR_SETUP;
            }
            $directory->setup[] = $fullname;
        }
        else {
            if (isset($matches[2])) {
                if ($directory->teardown_run) {
                    $error |= namespace\ERROR_TEARDOWN_RUN;
                }
                $directory->teardown_run[] = $fullname;
            }
            else {
                if ($directory->teardown) {
                    $error |= namespace\ERROR_TEARDOWN;
                }
                $directory->teardown[] = $fullname;
            }
        }
        return true;
    }
    return false;
}


function _parse_file(BufferingLogger $logger, $filepath, array $checks, array &$seen) {
    $source = namespace\_read_file($logger, $filepath);
    if (!$source) {
        return false;
    }

    $ns = '';
    $tokens = \token_get_all($source);
    // Start with $i = 2 since all PHP code starts with '<?php' followed by
    // whitespace
    for ($i = 2, $c = \count($tokens); $i < $c; ++$i) {
        if (!\is_array($tokens[$i])) {
            continue;
        }

        list($token_type, $token_name, ) = $tokens[$i];
        switch ($token_type) {
        case \T_CLASS:
        case \T_FUNCTION:
            // Always parse the identifier so we only match top-level
            // identifiers and not class methods, anonymous objects, etc.
            list($name, $i) = namespace\_parse_identifier($tokens, $i);
            if (!$name) {
                // anonymous object, although this test might be unnecessary?
                break;
            }
            if (!isset($checks[$token_type])) {
                // whatever we're parsing doesn't care about this identifier
                break;
            }

            $fullname = "$ns$name";
            // functions and classes with identical names can coexist!
            $seenname = "$token_name $fullname";
            $exists = "{$token_name}_exists";
            // Handle conditionally-defined identifiers, i.e., we found the
            // identifier in the file, but it wasn't actually defined due to
            // some failed conditional. The identifier could have still been
            // defined previously -- e.g., perhaps the file has multiple
            // configuration-based implementations -- so we also need to
            // ensure we don't "rediscover" already-defined identifiers. Our
            // implementation here isn't necessarily foolproof, because we
            // only keep track of identifiers we've seen in files we've parsed,
            // however it seems like somebody would have to be doing something
            // very bizarre for us to have a false positive here
            if (isset($seen[$seenname]) || !$exists($fullname)) {
                break;
            }

            if ($checks[$token_type]($ns, $name, $fullname)) {
                // whatever we're parsing cared about this identifier, so add
                // it to the "seen" list
                $seen[$seenname] = true;
            }
            break;

        case \T_NAMESPACE:
            list($ns, $i) = namespace\_parse_namespace($tokens, $i, $ns);
            break;
        }
    }
    return true;
}


function _read_file(BufferingLogger $logger, $filepath) {
    // First include the file to ensure it parses correctly
    namespace\start_buffering($logger, $filepath);
    $success = namespace\_include_file($logger, $filepath);
    namespace\end_buffering($logger);
    if (!$success) {
        return false;
    }

    try {
        $source = \file_get_contents($filepath);
    }
    catch (\Throwable $e) {
        $logger->log_error($filepath, $e);
        return false;
    }
    // #(BC 5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($filepath, $e);
        return false;
    }

    if (false === $source) {
        // file_get_contents() can return false if it fails. Presumably an
        // error would have been generated and already handled above, but the
        // documentation isn't explicit
        $logger->log_error($filepath, "Failed to read file (no error was raised)");
    }
    return $source;
}


function _include_file(Logger $logger, $file) {
    try {
        namespace\_guard_include($file);
        return true;
    }
    catch (\Throwable $e) {
        $logger->log_error($file, $e);
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($file, $e);
    }
    return false;
}


function _guard_include($file) {
    // Isolate included files to prevent them from meddling with local state
    include $file;
}


function _parse_identifier($tokens, $i) {
    $identifier = null;
    // $i = keyword identifying the type of identifer ('class', 'function',
    // etc.) and $i+1 = whitespace
    $i += 2;
    while (true) {
        $token = $tokens[$i];
        if ('{' === $token) {
            break;
        }
        ++$i;
        if (\is_array($token) && \T_STRING === $token[0]) {
            $identifier = $token[1];
            break;
        }
    }

    // advance token index to the end of the definition
    while ('{' !== $tokens[$i++]);
    $scope = 1;
    while ($scope) {
        $token = $tokens[$i++];
        if ('{' === $token) {
            ++$scope;
        }
        elseif ('}' === $token) {
            --$scope;
        }
    }

    return array($identifier, $i);
}


function _parse_namespace($tokens, $i, $current_ns) {
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
    $ns = array();
    while (++$i) {
        if ($tokens[$i] === ';' || $tokens[$i] === '{') {
            return array($ns ? (\implode('', $ns) . '\\') : '', $i);
        }

        if (!\is_array($tokens[$i])) {
            continue;
        }

        switch ($tokens[$i][0]) {
        case \T_NS_SEPARATOR:
            if (!$ns) {
                return array($current_ns, $i);
            }
            $ns[] = $tokens[$i][1];
            break;

        case \T_STRING:
            $ns[] = $tokens[$i][1];
            break;
        }
    }
}


function discover_file(State $state, BufferingLogger $logger, $filepath) {
    if (isset($state->files[$filepath])) {
        return $state->files[$filepath];
    }

    $file = new FileTest();
    $file->name = $filepath;
    $error = 0;
    $checks = array(
        \T_CLASS => function($namespace, $class, $fullname) use ($file) {
            return namespace\_is_test_class($file, $namespace, $class, $fullname);
        },
        \T_FUNCTION => function($namespace, $function, $fullname) use ($file, &$error) {
            return namespace\_is_test_function($file, $error, $namespace, $function, $fullname);
        },
    );
    if (!namespace\_parse_file($logger, $filepath, $checks, $state->seen)) {
        $file = false;
    }
    elseif ($error) {
        if ($error & namespace\ERROR_SETUP) {
            namespace\_log_fixture_error($logger, $filepath, $file->setup);
        }
        if ($error & namespace\ERROR_SETUP_FUNCTION) {
            namespace\_log_fixture_error($logger, $filepath, $file->setup_function);
        }
        if ($error & namespace\ERROR_TEARDOWN) {
            namespace\_log_fixture_error($logger, $filepath, $file->teardown);
        }
        if ($error & namespace\ERROR_TEARDOWN_RUN) {
            namespace\_log_fixture_error($logger, $filepath, $file->teardown_run);
        }
        if ($error & namespace\ERROR_TEARDOWN_FUNCTION) {
            namespace\_log_fixture_error($logger, $filepath, $file->teardown_function);
        }
        $file = false;
    }
    elseif (!$file->tests) {
        // Should this be logged/reported?
        $file = false;
    }
    else {
        $file->setup = $file->setup ? $file->setup[0] : null;
        $file->teardown = $file->teardown ? $file->teardown[0] : null;
        $file->teardown_run
            = $file->teardown_run ? $file->teardown_run[0] : null;
        $file->setup_function
            = $file->setup_function ? $file->setup_function[0] : null;
        $file->teardown_function
            = $file->teardown_function ? $file->teardown_function[0] : null;
    }

    $state->files[$filepath] = $file;
    return $file;
}


function _log_fixture_error(Logger $logger, $source, $fixtures) {
    $message = 'Multiple conflicting fixture functions found:';
    foreach ($fixtures as $i => $fixture) {
        ++$i;
        $message .= "\n    {$i}) {$fixture}";
    }
    $logger->log_error($source, $message);
}


function _is_test_class(FileTest $file, $namespace, $class, $fullname) {
    if (0 === \substr_compare($class, 'test', 0, 4, true)) {
        $info = new TestInfo();
        $info->type = namespace\TYPE_CLASS;
        $info->filename = $file->name;
        $info->namespace = $namespace;
        $info->name = $fullname;
        $file->tests["class $fullname"] = $info;
        return true;
    }
    return false;
}


function _is_test_function(FileTest $file, &$error, $namespace, $function, $fullname) {
    if (0 === \substr_compare($function, 'test', 0, 4, true)) {
        $info = new TestInfo();
        $info->type = namespace\TYPE_FUNCTION;
        $info->filename = $file->name;
        $info->namespace = $namespace;
        $info->name = $fullname;
        $file->tests["function $fullname"] = $info;
        return true;
    }

    if (\preg_match('~^(setup|teardown)_?(file|run)?~i', $function, $matches)) {
        if (0 === \strcasecmp('setup', $matches[1])) {
            if (isset($matches[2]) && 0 === \strcasecmp('file', $matches[2])) {
                if ($file->setup) {
                    $error |= namespace\ERROR_SETUP;
                }
                $file->setup[] = $fullname;
            }
            else {
                if ($file->setup_function) {
                    $error |= namespace\ERROR_SETUP_FUNCTION;
                }
                $file->setup_function[] = $fullname;
                $file->setup_function_name = $function;
            }
        }
        else {
            if (!isset($matches[2])) {
                if ($file->teardown_function) {
                    $error |= namespace\ERROR_TEARDOWN_FUNCTION;
                }
                $file->teardown_function[] = $fullname;
                $file->teardown_function_name = $function;
            }
            elseif (0 === \strcasecmp('file', $matches[2])) {
                if ($file->teardown) {
                    $error |= namespace\ERROR_TEARDOWN;
                }
                $file->teardown[] = $fullname;
            }
            else {
                if ($file->teardown_run) {
                    $error |= namespace\ERROR_TEARDOWN_RUN;
                }
                $file->teardown_run[] = $fullname;
            }
        }
        return true;
    }
    return false;
}


function discover_class(State $state, Logger $logger, TestInfo $info) {
    $classname = $info->name;
    if (isset($state->classes[$classname])) {
        return $state->classes[$classname];
    }

    $class = new ClassTest();
    $class->file = $info->filename;
    $class->namespace = $info->namespace;
    $class->name = $classname;
    $error = 0;
    foreach (\get_class_methods($classname) as $method) {
        if (0 === \substr_compare($method, 'test', 0, 4, true)) {
            $class->tests[] = $method;
            continue;
        }

        if(\preg_match('~^(setup|teardown)(?:_?object)?$~i', $method, $matches)) {
            if (0 === \strcasecmp('setup', $matches[1])) {
                if ($matches[0] === $matches[1]) {
                    $class->setup_function = $method;
                }
                else {
                    if ($class->setup) {
                        $error |= namespace\ERROR_SETUP;
                    }
                    $class->setup[] = $method;
                }
            }
            else {
                if ($matches[0] === $matches[1]) {
                    $class->teardown_function = $method;
                }
                else {
                    if ($class->teardown) {
                        $error |= namespace\ERROR_TEARDOWN;
                    }
                    $class->teardown[] = $method;
                }
            }
            continue;
        }
    }

    if ($error) {
        if ($error & namespace\ERROR_SETUP) {
            $logger->log_error(
                $classname,
                \sprintf(
                    "Multiple setup fixtures found:\n\t%s",
                    \implode("\n\t", $class->setup)
                )
            );
        }
        if ($error & namespace\ERROR_TEARDOWN) {
            $logger->log_error(
                $classname,
                \sprintf(
                    "Multiple teardown fixtures found:\n\t%s",
                    \implode("\n\t", $class->teardown)
                )
            );
        }
        $class = false;
    }
    elseif (!$class->tests) {
        // Should this be logged/reported?
        $class = false;
    }
    else {
        $class->setup = $class->setup ? $class->setup[0] : null;
        $class->teardown = $class->teardown ? $class->teardown[0] : null;
    }

    $state->classes[$classname] = $class;
    return $class;
}
