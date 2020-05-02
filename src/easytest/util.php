<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


// Generate a unified diff between two strings
//
// This is a basic implementation of the longest common subsequence algorithm.

function diff($from, $to, $from_name, $to_name) {
    $diff = namespace\_diff_array(
        '' === $from ? array() : \explode("\n", $from),
        '' === $to   ? array() : \explode("\n", $to)
    );
    $diff = \implode("\n", $diff);
    return "- $from_name\n+ $to_name\n\n$diff";
}


function _diff_array($from, $to) {
    $flen = \count($from);
    $tlen = \count($to);
    $m = array();

    for ($i = 0; $i <= $flen; ++$i) {
        for ($j = 0; $j <= $tlen; ++$j) {
            if (0 === $i || 0 === $j) {
                $m[$i][$j] = 0;
            }
            elseif ($from[$i-1] === $to[$j-1]) {
                $m[$i][$j] = $m[$i-1][$j-1] + 1;
            }
            else {
                $m[$i][$j] = \max($m[$i][$j-1], $m[$i-1][$j]);
            }
        }
    }

    $i = $flen;
    $j = $tlen;
    $diff = array();
    while ($i > 0 || $j > 0) {
        if ($i > 0 && $j > 0 && $from[$i-1] === $to[$j-1]) {
            --$i;
            --$j;
            \array_unshift($diff, '  ' . $to[$j]);
        }
        elseif ($j > 0 && (0 === $i || $m[$i][$j-1] >= $m[$i-1][$j])) {
            --$j;
            \array_unshift($diff, '+ ' . $to[$j]);
        }
        elseif ($i > 0 && (0 === $j || $m[$i][$j-1] < $m[$i-1][$j])) {
            --$i;
            \array_unshift($diff, '- ' . $from[$i]);
        }
        else {
            throw new \Exception('Reached unexpected branch');
        }
    }

    return $diff;
}


function format_failure_message($assertion, $description, $detail = null) {
    $message = array();
    // $assertion and $detail are provided by the framework, so we don't need
    // to "normalize" them. $description will have been passed through from
    // client code, so we'll trim off any extraneous whitespace
    if ('' !== $assertion) {
        $message[] = $assertion;
    }
    $description = \trim($description);
    if ('' !== $description) {
        $message[] = $description;
    }
    if (!$message) {
        $message[] = 'Assertion failed';
    }
    if (isset($detail)) {
        $message[] = '';
        $message[] = $detail;
    }
    return \implode("\n", $message);
}



// Format a variable for display.
//
// This provides more readable(?) formatting of variables than PHP's built-in
// variable-printing functions (print_r(), var_dump(), var_export()) and also
// handles recursive references.

function format_variable(&$var) {
    $name = \is_object($var) ? \get_class($var) : \gettype($var);
    $seen = array('byval' => array(), 'byref' => array());
    // We'd really like to make this a constant static variable, but PHP won't
    // let us do that with an object instance. As a mitigation, we'll just
    // create the sentinels once at the start and then pass it around
    $sentinels = array('byref' => null, 'byval' => new \stdClass());
    return namespace\_format_recursive_variable($var, $name, $seen, $sentinels);
}


function _format_recursive_variable(&$var, $name, &$seen, $sentinels, $indent = 0) {
    $reference = namespace\_check_reference($var, $name, $seen, $sentinels);
    if ($reference) {
        return $reference;
    }
    if (\is_scalar($var) || null === $var) {
        return namespace\_format_scalar($var);
    }
    if (\is_resource($var)) {
        return namespace\_format_resource($var);
    }
    if (\is_array($var)) {
        return namespace\_format_array($var, $name, $seen, $sentinels, $indent);
    }
    if (\is_object($var)) {
        return namespace\_format_object($var, $name, $seen, $sentinels, $indent);
    }
    throw new \Exception(
        \sprintf('Unexpected/unknown variable type: %s', \gettype($var))
    );
}


function _format_scalar(&$var) {
    return \var_export($var, true);
}


function _format_resource(&$var) {
    return \sprintf(
        '%s of type "%s"',
        \print_r($var, true),
        \get_resource_type($var)
    );
}


function _format_array(&$var, $name, &$seen, $sentinels, $indent) {
    $baseline = \str_repeat(' ', $indent);
    $indent += 4;
    $padding = \str_repeat(' ', $indent);
    $out = '';

    if ($var) {
        foreach ($var as $key => &$value) {
            $key = \var_export($key, true);
            $out .= \sprintf(
                "\n%s%s => %s,",
                $padding,
                $key,
                namespace\_format_recursive_variable(
                    $value,
                    \sprintf('%s[%s]', $name, $key),
                    $seen,
                    $sentinels,
                    $indent
                )
            );
        }
        $out .= "\n$baseline";
    }
    return "array($out)";
}


function _format_object(&$var, $name, &$seen, $sentinels, $indent) {
    $baseline = \str_repeat(' ', $indent);
    $indent += 4;
    $padding = \str_repeat(' ', $indent);
    $out = '';

    $class = \get_class($var);
    $values = (array)$var;
    if ($values) {
        foreach ($values as $key => &$value) {
            // Object properties are cast to array keys as follows:
            //     public    $property -> "property"
            //     protected $property -> "\0*\0property"
            //     private   $property -> "\0class\0property"
            //         where "class" is the name of the class where the
            //         property is declared
            $key = \explode("\0", $key);
            $property = '$' . \array_pop($key);
            if ($key && $key[1] !== '*' && $key[1] !== $class) {
                $property = "$key[1]::$property";
            }
            $out .= \sprintf(
                "\n%s%s = %s;",
                $padding,
                $property,
                namespace\_format_recursive_variable(
                    $value,
                    \sprintf('%s->%s', $name, $property),
                    $seen,
                    $sentinels,
                    $indent
                )
            );
        }
        $out .= "\n$baseline";
    }
    return "$class {{$out}}";
}


// Check if $var is a reference to another value in $seen.
//
// If $var is normally pass-by-value, then it can only be an explicit
// reference. If it's normally pass-by-reference, then it can either be an
// object reference or an explicit reference. Explicit references are
// marked with the reference operator, i.e., '&'.
//
// Since PHP has no built-in way to determine if a variable is a reference,
// references are identified using jank wherein $var is changed and $seen
// is checked for an equivalent change.
function _check_reference(&$var, $name, &$seen, $sentinels) {
    if (\is_scalar($var) || \is_array($var) || null === $var) {
        $copy = $var;
        $var = $sentinels['byval'];
        $reference = \array_search($var, $seen['byval'], true);
        if (false === $reference) {
            $seen['byval'][$name] = &$var;
        }
        else {
            $reference = "&$reference";
        }
        $var = $copy;
    }
    else {
        $reference = \array_search($var, $seen['byref'], true);
        if (false === $reference) {
            $seen['byref'][$name] = &$var;
        }
        else {
            $copy = $var;
            $var = $sentinels['byref'];
            if ($var === $seen['byref'][$reference]) {
                $reference = "&$reference";
            }
            $var = $copy;
        }
    }
    return $reference;
}



function ksort_recursive(&$array, &$seen = array()) {
    if (!\is_array($array)) {
        return;
    }

    /* Prevent infinite recursion for arrays with recursive references. */
    $temp = $array;
    $array = null;
    $sorted = \in_array($array, $seen, true);
    $array = $temp;
    unset($temp);

    if (false !== $sorted) {
        return;
    }
    $seen[] = &$array;
    \ksort($array);
    foreach ($array as &$value) {
        namespace\ksort_recursive($value, $seen);
    }
}
