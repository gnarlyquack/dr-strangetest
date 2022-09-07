<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


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
 * @param int $nesting_level
 * @param bool $show_object_id
 * @param bool $as_array
 * @return ($as_array is true ? string[] : string) A string representation of $var
 */
function format_variable(&$variable, $nesting_level = 0, $show_object_id = true, $as_array = false)
{
    $name = \is_object($variable) ? \get_class($variable) : \gettype($variable);

    $formatter = new VariableFormatter($show_object_id);

    $result = new FormatResult;
    $prefix = $formatter->format_indent($nesting_level);
    $formatter->format_variable($result, $variable, $name, $nesting_level, $prefix);

    if ($as_array)
    {
        return $result->formatted;
    }
    else
    {
        return \implode("\n", $result->formatted);
    }
}


/**
 * @api
 * @param int $nesting_level
 * @return string
 */
function format_indent($nesting_level)
{
    return \str_repeat(' ', VariableFormatter::DEFAULT_INDENT_WIDTH * $nesting_level);
}


/**
 * @api
 * @param string|int $index;
 * @param ?string $formatted
 * @return string
 */
function format_array_index($index, &$formatted = null)
{
    $formatted = \var_export($index, true);

    $result = $formatted . ' => ';
    return $result;
}


/**
 * @api
 * @param string|int $property
 * @param string $class
 * @param ?string $formatted
 * @return string
 */
function format_property($property, $class, &$formatted = null)
{
    // Object properties are cast to array keys as follows:
    //     public    $property -> "property"
    //     protected $property -> "\0*\0property"
    //     private   $property -> "\0class\0property"
    //         where "class" is the name of the class where the
    //         property is declared
    $parts = \explode("\0", (string)$property);

    $result = '$' . \array_pop($parts);
    if ($parts && $parts[1] !== '*' && $parts[1] !== $class)
    {
        $result = $parts[1] . '::' . $result;
    }

    $formatted = $result;
    $result .= ' = ';
    return $result;
}


/**
 * @api
 * @param object $object
 * @param bool $show_object_id
 * @param ?string $class
 * @return string
 */
function format_object_start(&$object, $show_object_id, &$class = null)
{
    $result = \get_class($object);
    $class = $result;

    if ($show_object_id)
    {
        // @bc 7.1 use spl_object_hash instead of spl_object_id
        $id = \function_exists('spl_object_id')
            ? \spl_object_id($object)
            : \spl_object_hash($object);
        $result .= " #$id";
    }

    $result .= ' {';
    return $result;
}


/**
 * @api
 */
final class FormatResult extends struct
{
    /** @var string[] */
    public $formatted = array();
}


/**
 * @api
 */
final class VariableFormatter extends struct
{
    const DEFAULT_INDENT_WIDTH = 4;

    const ARRAY_START = 'array(';
    const ARRAY_END = ')';
    const ARRAY_ELEMENT_SEPARATOR = ',';

    const OBJECT_END = '}';
    const PROPERTY_TERMINATOR = ';';

    const STRING_MIDDLE =   0;
    const STRING_START  = 0x1;
    const STRING_END    = 0x2;
    // @bc 5.5 hard-code constant value instead of using a constant expression
    const STRING_WHOLE  = 0x3; // bitwise or of STRING_START and STRING_END

    /**
     * We'd really like to make this a constant, but PHP won't let us do that
     * with an object instance, so we'll just have to initialize it every time
     * we instantiate a new class. :-(
     * @var array{'byref': null, 'byval': \stdClass}
     */
    private $sentinels;

    /** @var array{'byval': mixed[], 'byref': mixed[]} */
    private $seen = array('byval' => array(), 'byref' => array());

    /** @var int */
    private $indent_width;

    /** @var bool */
    private $show_object_id;


    /**
     * @param bool $show_object_id
     * @param int $indent_width
     */
    public function __construct($show_object_id, $indent_width = self::DEFAULT_INDENT_WIDTH)
    {
        $this->sentinels = array('byref' => null, 'byval' => new \stdClass);
        $this->show_object_id = $show_object_id;
        $this->indent_width = $indent_width;
    }


    /**
     * @param mixed $var
     * @param string $name
     * @param int $nesting_level
     * @param string $prefix
     * @param string $suffix
     * @return void
     */
    public function format_variable(FormatResult $result, &$var, $name, $nesting_level, $prefix = '', $suffix = '')
    {
        $reference = namespace\check_reference($var, $name, $this->seen, $this->sentinels);
        if ($reference)
        {
            $result->formatted[] = $prefix . $reference . $suffix;
        }
        elseif(\is_string($var))
        {
            $this->format_string($result, $var, $prefix, $suffix);
        }
        elseif (\is_scalar($var) || null === $var)
        {
            $this->format_scalar($result, $var, $prefix, $suffix);
        }
        elseif (\is_resource($var))
        {
            $this->format_resource($result, $var, $prefix, $suffix);
        }
        elseif (\is_array($var))
        {
            $this->format_array($result, $var, $name, $nesting_level, $prefix, $suffix);
        }
        elseif (\is_object($var))
        {
            $this->format_object($result, $var, $name, $nesting_level, $prefix, $suffix);
        }
        else
        {
            throw new \Exception(
                \sprintf('Unexpected/unknown variable type: %s', \gettype($var))
            );
        }
    }


    /**
     * @param scalar $var
     * @param string $prefix
     * @param string $suffix
     * @return void
     */
    public function format_scalar(FormatResult $result, &$var, $prefix = '', $suffix = '')
    {
        $result->formatted[] = $prefix . \var_export($var, true) . $suffix;
    }


    /**
     * @param string $var
     * @param string $prefix
     * @param string $suffix
     * @param self::STRING_* $part
     * @return void
     */
    public function format_string(FormatResult $result, &$var, $prefix = '', $suffix = '', $part = self::STRING_WHOLE)
    {
        $formatted = \str_replace(array('\\', "'",), array('\\\\', "\\'",), $var);

        if ($part & self::STRING_START)
        {
            $formatted = $prefix . "'" . $formatted;
        }
        if ($part & self::STRING_END)
        {
            $formatted .= "'" . $suffix;
        }

        $start = 0;
        while (false !== ($end = \strpos($formatted, "\n", $start)))
        {
            $len = $end - $start;
            $result->formatted[] = \substr($formatted, $start, $len);
            $start = $end + 1;
        }
        $result->formatted[] = \substr($formatted, $start);
    }


    /**
     * @param resource $var
     * @param string $prefix
     * @param string $suffix
     * @return void
     */
    public function format_resource(FormatResult $result, &$var, $prefix = '', $suffix = '')
    {
        $result->formatted[] = $prefix . \sprintf(
            '%s of type "%s"',
            \print_r($var, true),
            \get_resource_type($var)
        ) . $suffix;
    }


    /**
     * @param mixed[] $var
     * @param string $name
     * @param int $nesting_level
     * @param string $prefix
     * @param string $suffix
     * @return void
     */
    public function format_array(FormatResult $result, array &$var, $name, $nesting_level, $prefix = '', $suffix = '')
    {
        $start = self::ARRAY_START;
        $end = self::ARRAY_END;

        if ($var)
        {
            $padding = $this->format_indent($nesting_level);
            ++$nesting_level;
            $indent = $this->format_indent($nesting_level);
            $show_key = !\array_is_list($var);

            $result->formatted[] = $prefix . $start;

            foreach ($var as $key => &$value)
            {
                $index = namespace\format_array_index($key, $formatted);

                $prefix = $indent;
                if ($show_key)
                {
                    $prefix .= $index;
                }

                $this->format_variable(
                    $result,
                    $value,
                    \sprintf('%s[%s]', $name, $formatted),
                    $nesting_level,
                    $prefix,
                    self::ARRAY_ELEMENT_SEPARATOR);
            }

            $result->formatted[] = $padding . $end . $suffix;
        }
        else
        {
            $result->formatted[] = $prefix . $start . $end . $suffix;
        }
    }


    /**
     * @param object $var
     * @param string $name
     * @param int $nesting_level
     * @param string $prefix
     * @param string $suffix
     * @return void
     */
    public function format_object(FormatResult $result, &$var, $name, $nesting_level, $prefix = '', $suffix = '')
    {
        $start = namespace\format_object_start($var, $this->show_object_id, $class);
        $end = self::OBJECT_END;

        $values = (array)$var;
        if ($values)
        {
            $padding = $this->format_indent($nesting_level);
            ++$nesting_level;
            $indent = $this->format_indent($nesting_level);

            $result->formatted[] = $prefix . $start;

            foreach ($values as $key => &$value)
            {
                $property = namespace\format_property($key, $class, $formatted);
                $prefix = $indent . $property;
                $this->format_variable(
                    $result,
                    $value,
                    \sprintf('%s->%s', $name, $formatted),
                    $nesting_level,
                    $prefix,
                    self::PROPERTY_TERMINATOR);
            }

            $result->formatted[] = $padding . $end . $suffix;
        }
        else
        {
            $result->formatted[] = $prefix . $start . $end . $suffix;
        }
    }


    /**
     * @param int $nesting_level
     * @return string
     */
    public function format_indent($nesting_level)
    {
        return \str_repeat(' ' , $this->indent_width * $nesting_level);
    }
}
