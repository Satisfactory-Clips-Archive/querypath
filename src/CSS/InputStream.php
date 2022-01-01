<?php

declare(strict_types=1);
/**
 * @file
 *
 * The CSS Input Stream abstraction.
 */

namespace QueryPath\CSS;

use function count;

/**
 * Simple wrapper to turn a string into an input stream.
 * This provides a standard interface on top of an array of
 * characters.
 */
class InputStream
{
	public int $position = 0;

	/** @var list<string> */
	protected array $stream;

	/**
	 * Build a new CSS input stream from a string.
	 *
	 * @param string $string
	 *  String to turn into an input stream
	 */
	public function __construct(string $string)
	{
		$this->stream = str_split($string);
	}

	/**
	 * Look ahead one character.
	 *
	 * @return string
	 *  Returns the next character, but does not remove it from
	 *  the stream
	 */
	public function peek() : string
	{
		return $this->stream[0];
	}

	/**
	 * Get the next unconsumed character in the stream.
	 * This will remove that character from the front of the
	 * stream and return it.
	 */
	public function consume() : ?string
	{
		$ret = array_shift($this->stream);
		if ( ! empty($ret)) {
			++$this->position;
		}

		return $ret;
	}

	/**
	 * Check if the stream is empty.
	 *
	 * @return bool
	 *   Returns TRUE when the stream is empty, FALSE otherwise
	 */
	public function isEmpty() : bool
	{
		return 0 === count($this->stream);
	}
}
