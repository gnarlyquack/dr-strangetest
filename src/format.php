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


/**
 * @api
 * @param int $indent_level
 * @return string
 */
function format_indent($indent_level)
{
    return \str_repeat(namespace\FORMAT_INDENT, $indent_level);
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
 * @param int $starting_indent_level
 * @param bool $show_object_id
 * @param bool $as_array
 * @return ($as_array is true ? string[] : string) A string representation of $var
 */
function format_variable(&$variable, $starting_indent_level = 0, $show_object_id = true, $as_array = false)
{
    $name = \is_object($variable) ? \get_class($variable) : \gettype($variable);
    $prefix = namespace\format_indent($starting_indent_level);

    $formatter = new Formatter($show_object_id);
    $formatter->format_variable($variable, $name, $starting_indent_level, $prefix, '');

    $result = $formatter->get_formatted();
    if (!$as_array)
    {
        $result = \implode("\n", $result);
    }
    return $result;
}


/**
 * @todo Make array start a constant?
 * @api
 * @return string
 */
function format_array_start()
{
    return 'array(';
}


/**
 * @todo Make array end a constant?
 * @api
 * @return string
 */
function format_array_end()
{
    return ')';
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
 * @todo Make object end a constant?
 * @api
 * @return string
 */
function format_object_end()
{
    return '}';
}


/**
 * @api
 */
final class Formatter extends struct
{
    /**
     * We'd really like to make this a constant, but PHP won't let us do that
     * with an object instance, so we'll just have to initialize it every time
     * we instantiate a new class. :-(
     * @var array{'byref': null, 'byval': \stdClass}
     */
    private $sentinels;

    /** @var array{'byval': mixed[], 'byref': mixed[]} */
    private $seen = array('byval' => array(), 'byref' => array());

    /** @var string */
    private $indent;

    /** @var bool */
    private $show_object_id;

    /** @var string[] */
    private $format = array();


    /**
     * @param bool $show_object_id
     * @param string $indent
     */
    public function __construct($show_object_id, $indent = namespace\FORMAT_INDENT)
    {
        $this->sentinels = array('byref' => null, 'byval' => new \stdClass);
        $this->show_object_id = $show_object_id;
        $this->indent = $indent;
    }


    /**
     * @todo should Formatter::get_formatted clear out the current state?
     * This could allow a formatter to be reused for multiple values. Maybe
     * paramaterize this as an option? Note that both the format array and the
     * seen array would have to be reset.
     *
     * @return string[]
     */
    public function get_formatted()
    {
        return $this->format;
    }


    /**
     * @param mixed $var
     * @param string $name
     * @param int $indent_level
     * @param string $prefix
     * @param string $suffix
     * @return void
     */
    public function format_variable(&$var, $name, $indent_level, $prefix = '', $suffix = '')
    {
        $reference = namespace\check_reference($var, $name, $this->seen, $this->sentinels);
        if ($reference)
        {
            $this->format[] = "{$prefix}{$reference}{$suffix}";
        }
        elseif (\is_scalar($var) || null === $var)
        {
            $this->format_scalar($var, $prefix, $suffix);
        }
        elseif (\is_resource($var))
        {
            $this->format_resource($var, $prefix, $suffix);
        }
        elseif (\is_array($var))
        {
            $this->format_array($var, $name, $indent_level, $prefix, $suffix);
        }
        elseif (\is_object($var))
        {
            $this->format_object($var, $name, $indent_level, $prefix, $suffix);
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
    public function format_scalar(&$var, $prefix = '', $suffix = '')
    {
        $this->format[] = $prefix . \var_export($var, true) . $suffix;
    }


    /**
     * @param resource $var
     * @param string $prefix
     * @param string $suffix
     * @return void
     */
    function format_resource(&$var, $prefix = '', $suffix = '')
    {
        $this->format[] = $prefix . \sprintf(
            '%s of type "%s"',
            \print_r($var, true),
            \get_resource_type($var)
        ) . $suffix;
    }


    /**
     * @param mixed[] $var
     * @param string $name
     * @param int $indent_level
     * @param string $prefix
     * @param string $suffix
     * @return void
     */
    public function format_array(array &$var, $name, $indent_level, $prefix = '', $suffix = '')
    {
        $start = namespace\format_array_start();
        $end = namespace\format_array_end();

        if ($var)
        {
            $padding = $this->format_indent($indent_level);
            ++$indent_level;
            $indent = $this->format_indent($indent_level);
            $show_key = !\array_is_list($var);

            $this->format[] = "{$prefix}{$start}";

            foreach ($var as $key => &$value)
            {
                $key = \var_export($key, true);

                $prefix = $indent;
                if ($show_key)
                {
                    $prefix .= "{$key} => ";
                }

                $this->format_variable(
                    $value,
                    \sprintf('%s[%s]', $name, $key),
                    $indent_level,
                    $prefix,
                    // @todo Make a constant for array line end?
                    ',');
            }

            $this->format[] = "{$padding}{$end}{$suffix}";
        }
        else
        {
            $this->format[] = "{$prefix}{$start}{$end}{$suffix}";
        }
    }


    /**
     * @param object $var
     * @param string $name
     * @param int $indent_level
     * @param string $prefix
     * @param string $suffix
     * @return void
     */
    public function format_object(&$var, $name, $indent_level, $prefix = '', $suffix = '')
    {
        $start = namespace\format_object_start($var, $this->show_object_id, $class);
        $end = namespace\format_object_end();

        $values = (array)$var;
        if ($values)
        {
            $padding = $this->format_indent($indent_level);
            ++$indent_level;
            $indent = $this->format_indent($indent_level);

            $this->format[] = "{$prefix}{$start}";

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

                $prefix = $indent . $property . ' = ';
                $this->format_variable(
                    $value,
                    \sprintf('%s->%s', $name, $property),
                    $indent_level,
                    $prefix,
                    // @todo Make a constant for object line end?
                    ';');
            }

            $this->format[] = "{$padding}{$end}{$suffix}";
        }
        else
        {
            $this->format[] = "{$prefix}{$start}{$end}{$suffix}";
        }
    }


    /**
     * @param int $indent_level
     * @return string
     */
    public function format_indent($indent_level)
    {
        return \str_repeat($this->indent, $indent_level);
    }
}
