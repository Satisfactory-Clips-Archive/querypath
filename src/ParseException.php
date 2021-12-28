<?php

declare(strict_types=1);
/**
 * @file
 * Query path parsing exception.
 */

namespace QueryPath;

use Closure;
use const LIBXML_ERR_WARNING;

/**
 * Exception indicating that a parser has failed to parse a file.
 *
 * This will report parser warnings as well as parser errors. It should only be
 * thrown, though, under error conditions.
 *
 * @ingroup querypath_core
 */
class ParseException extends Exception
{
	public const ERR_MSG_FORMAT = 'Parse error in %s on line %d column %d: %s (%d)';
	public const WARN_MSG_FORMAT = 'Parser warning in %s on line %d column %d: %s (%d)';

	// trigger_error
	final public function __construct(string $msg = '', int $code = 0, string $file = null, int $line = null)
	{
		$msgs = [];
		foreach (libxml_get_errors() as $err) {
			$format = LIBXML_ERR_WARNING === $err->level ? self::WARN_MSG_FORMAT : self::ERR_MSG_FORMAT;
			$msgs[] = sprintf($format, $err->file, $err->line, $err->column, $err->message, $err->code);
		}
		$msg .= implode("\n", $msgs);

		if (isset($file)) {
			$msg .= ' (' . $file;
			if (isset($line)) {
				$msg .= ': ' . $line;
			}
			$msg .= ')';
		}

		parent::__construct($msg, $code);
	}

	/**
	 * @return Closure(int, string, string|null, int|null):bool
	 */
	public static function initializeFromError() : Closure
	{
		return static function ($code, $str, $file, $line) : bool {
			//printf("\n\nCODE: %s %s\n\n", $code, $str);
			throw new static($str, $code, $file, $line);
		};
	}
}
