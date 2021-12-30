<?php

declare(strict_types=1);
/**
 * @file
 *
 * Options management.
 */

namespace QueryPath;

use function array_key_exists;

/**
 * Manage default options.
 *
 * This class stores the default options for QueryPath. When a new
 * QueryPath object is constructed, options specified here will be
 * used.
 *
 * <b>Details</b>
 * This class defines no options of its own. Instead, it provides a
 * central tool for developers to override options set by QueryPath.
 * When a QueryPath object is created, it will evaluate options in the
 * following order:
 *
 * - Options passed into qp() have highest priority.
 * - Options in QueryPath::Options (this class) have the next highest priority.
 * - If the option is not specified elsewhere, QueryPath will use its own defaults.
 *
 * @see     qp()
 * @see     QueryPath::Options::set()
 * @ingroup querypath_util
 */
class Options
{
	/**
	 * This is the static options array.
	 *
	 * Use the {@link set()}, {@link get()}, and {@link merge()} to
	 * modify this array.
	 *
	 * @var array{
	 *	parser_flags?: int|null,
	 *	omit_xml_declaration?: bool,
	 *	replace_entities?: bool,
	 *	exception_level?: int,
	 *	ignore_parser_warnings?: bool,
	 *	escape_xhtml_js_css_sections?: string,
	 *	convert_from_encoding?: string,
	 *	convert_to_encoding?: string
	 * }
	 */
	public static array $options = [];

	/**
	 * Set the default options.
	 *
	 * The passed-in array will be used as the default options list.
	 *
	 * @param array{
	 *	QueryPath_class?:class-string<DOMQuery>
	 * } $array
	 *  An associative array of options
	 */
	public static function set(array $array) : void
	{
		self::$options = $array;
	}

	/**
	 * Get the default options.
	 *
	 * Get all options currently set as default.
	 *
	 * @return array{
	 *	parser_flags?: int|null,
	 *	omit_xml_declaration?: bool,
	 *	replace_entities?: bool,
	 *	exception_level?: int,
	 *	ignore_parser_warnings?: bool,
	 *	escape_xhtml_js_css_sections?: string,
	 *	convert_from_encoding?: string,
	 *	convert_to_encoding?: string
	 * }
	 *  An array of options. Note that only explicitly set options are
	 *  returned. {@link QueryPath} defines default options which are not
	 *  stored in this object.
	 */
	public static function get() : array
	{
		return self::$options;
	}

	/**
	 * Merge the provided array with existing options.
	 *
	 * On duplicate keys, the value in $array will overwrite the
	 * value stored in the options.
	 *
	 * @param array{
	 *	parser_flags?: int|null,
	 *	omit_xml_declaration?: bool,
	 *	replace_entities?: bool,
	 *	exception_level?: int,
	 *	ignore_parser_warnings?: bool,
	 *	escape_xhtml_js_css_sections?: string,
	 *	convert_from_encoding?: string,
	 *	convert_to_encoding?: string
	 * } $array
	 *  Associative array of options to merge into the existing options
	 */
	public static function merge($array) : void
	{
		self::$options = $array + self::$options;
	}

	/**
	 * Returns true of the specified key is already overridden in this object.
	 *
	 * @param string $key
	 *  The key to search for
	 */
	public static function has(string $key) : bool
	{
		return array_key_exists($key, self::$options);
	}
}
