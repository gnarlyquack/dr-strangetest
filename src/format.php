<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


const FORMAT_INDENT = '    ';


/**
 * @api
 * @param ?string $assertion
 * @param ?string $description
 * @param ?string $detail
 * @return string
 */
function format_failure_message($assertion, $description = null, $detail = null)
{
    $message = array();
    if ($assertion)
    {
        $message[] = $assertion;
    }
    if ($description)
    {
        $message[] = $description;
    }
    if (!$message)
    {
        $message[] = 'Assertion failed';
    }
    if ($detail)
    {
        $message[] = '';
        $message[] = $detail;
    }
    return \implode("\n", $message);
}



// @todo Add "loose" as a parameter to format_variable?
// format_variable assumed $loose is false, meaning there is no way to format
// an object using this function that contains an object id
// @todo Add an option to return formatted variable as an array instead of string?
// This would allow further manipulation of the format output (e.g., see
// split_line_first) without having to manually pull the string apart again
/**
 * Format a string representation of a variable.
 *
 * This provides more readable(?) formatting of variables than PHP's built-in
 * variable-printing functions (print_r(), var_dump(), var_export()) and also
 * handles recursive references.
 *
 * @api
 * @param mixed $variable The variable to format. $variable is received as a
 *                        reference in order to detect self-referencing values.
 * @return string A string representation of $var
 */
function format_variable(&$variable)
{
    $name = \is_object($variable) ? \get_class($variable) : \gettype($variable);
    $seen = array('byval' => array(), 'byref' => array());
    // We'd really like to make this a constant static variable, but PHP won't
    // let us do that with an object instance. As a mitigation, we'll just
    // create the sentinels once at the start and then pass it around
    $sentinels = array('byref' => null, 'byval' => new \stdClass());
    return namespace\_format_recursive_variable($variable, $name, false, $seen, $sentinels, '');
}


/**
 * @param mixed $var
 * @param string $name
 * @param bool $loose
 * @param array{'byval': mixed[], 'byref': mixed[]} $seen
 * @param array{'byref': null, 'byval': \stdClass} $sentinels
 * @param string $indent
 * @return string
 */
function _format_recursive_variable(&$var, $name, $loose, &$seen, $sentinels, $indent)
{
    $reference = namespace\check_reference($var, $name, $seen, $sentinels);
    if ($reference)
    {
        return $reference;
    }
    if (\is_scalar($var) || null === $var)
    {
        return namespace\format_scalar($var);
    }
    if (\is_resource($var))
    {
        return namespace\format_resource($var);
    }
    if (\is_array($var))
    {
        return namespace\format_array($var, $name, $loose, $seen, $sentinels, $indent);
    }
    if (\is_object($var))
    {
        return namespace\format_object($var, $name, $loose, $seen, $sentinels, $indent);
    }
    throw new \Exception(
        \sprintf('Unexpected/unknown variable type: %s', \gettype($var))
    );
}


/**
 * @param int $indent_level
 * @return string
 */
function format_indent($indent_level)
{
    return \str_repeat(namespace\FORMAT_INDENT, $indent_level);
}


/**
 * @param scalar $var
 * @return string
 */
function format_scalar(&$var)
{
    return \var_export($var, true);
}


/**
 * @param resource $var
 * @return string
 */
function format_resource(&$var)
{
    return \sprintf(
        '%s of type "%s"',
        \print_r($var, true),
        \get_resource_type($var)
    );
}


/**
 * @template T
 * @param T[] $var
 * @param string $name
 * @param bool $loose
 * @param array{'byval': mixed[], 'byref': mixed[]} $seen
 * @param array{'byref': null, 'byval': \stdClass} $sentinels
 * @param string $padding
 * @return string
 */
function format_array(array &$var, $name, $loose, &$seen, $sentinels, $padding)
{
    $indent = $padding . namespace\FORMAT_INDENT;
    $out = '';

    if ($var)
    {
        $show_key = !\array_is_list($var);
        foreach ($var as $key => &$value)
        {
            $key = \var_export($key, true);
            $key_format = $show_key ? "$key => " : '';
            $out .= \sprintf(
                "\n%s%s%s,",
                $indent,
                $key_format,
                namespace\_format_recursive_variable(
                    $value,
                    \sprintf('%s[%s]', $name, $key),
                    $loose,
                    $seen,
                    $sentinels,
                    $indent
                )
            );
        }
        $out .= "\n$padding";
    }
    return "array($out)";
}


/**
 * @param object $var
 * @param string $name
 * @param bool $loose
 * @param array{'byval': mixed[], 'byref': mixed[]} $seen
 * @param array{'byref': null, 'byval': \stdClass} $sentinels
 * @param string $padding
 * @return string
 */
function format_object(&$var, $name, $loose, &$seen, $sentinels, $padding)
{
    $indent = $padding . namespace\FORMAT_INDENT;
    $out = '';

    $start = namespace\format_object_start($var, $loose, $class);
    $values = (array)$var;
    if ($values)
    {
        foreach ($values as $key => &$value)
        {
            // Object properties are cast to array keys as follows:
            //     public    $property -> "property"
            //     protected $property -> "\0*\0property"
            //     private   $property -> "\0class\0property"
            //         where "class" is the name of the class where the
            //         property is declared
            $key = \explode("\0", $key);
            $property = '$' . \array_pop($key);
            if ($key && $key[1] !== '*' && $key[1] !== $class)
            {
                $property = "$key[1]::$property";
            }
            $out .= \sprintf(
                "\n%s%s = %s;",
                $indent,
                $property,
                namespace\_format_recursive_variable(
                    $value,
                    \sprintf('%s->%s', $name, $property),
                    $loose,
                    $seen,
                    $sentinels,
                    $indent
                )
            );
        }
        $out .= "\n$padding";
    }
    $end = namespace\format_object_end();
    return "{$start}{$out}{$end}";
}


/**
 * @param object $object
 * @param bool $loose
 * @param ?string $class
 * @return string
 */
function format_object_start(&$object, $loose, &$class = null)
{
    $class = \get_class($object);
    if (!$loose)
    {
        // @bc 7.1 use spl_object_hash instead of spl_object_id
        $id = \function_exists('spl_object_id')
            ? \spl_object_id($object)
            : \spl_object_hash($object);
        return "$class #$id {";
    }
    return "$class {";
}


/**
 * @return string
 */
function format_object_end()
{
    return '}';
}
