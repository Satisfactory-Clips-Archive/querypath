<?php

declare(strict_types=1);
/**
 * @file
 *
 * Utilities for DOM traversal.
 */

namespace QueryPath\CSS\DOMTraverser;

use DOMElement;
use function in_array;
use function is_null;
use QueryPath\CSS\EventHandler;
use function strlen;

/**
 * Utilities for DOM Traversal.
 */
class Util
{
	/**
	 * Check whether the given DOMElement has the given attribute.
	 */
	public static function matchesAttribute(
		DOMElement $node,
		string $name,
		string $value = null,
		?int $operation = EventHandler::IS_EXACTLY
	) : bool
	{
		if ( ! $node->hasAttribute($name)) {
			return false;
		}

		if (null === $value) {
			return true;
		}

		return self::matchesAttributeValue($value, $node->getAttribute($name), $operation);
	}

	/**
	 * Check whether the given DOMElement has the given namespaced attribute.
	 */
	public static function matchesAttributeNS(
		DOMElement $node,
		string $name,
		?string $nsuri,
		string $value = null,
		?int $operation = EventHandler::IS_EXACTLY
	) : bool
	{
		if (is_null($nsuri)) {
			return self::matchesAttribute($node, $name, $value, $operation);
		}

		if ( ! $node->hasAttributeNS($nsuri, $name)) {
			return false;
		}

		if (is_null($value)) {
			return true;
		}

		return self::matchesAttributeValue($value, $node->getAttributeNS($nsuri, $name), $operation);
	}

	/**
	 * Check for attr value matches based on an operation.
	 */
	public static function matchesAttributeValue(
		string $needle,
		string $haystack,
		?int $operation
	) : bool
	{
		if (strlen($haystack) < strlen($needle)) {
			return false;
		}

		// According to the spec:
		// "The case-sensitivity of attribute names in selectors depends on the document language."
		// (6.3.2)
		// To which I say, "huh?". We assume case sensitivity.
		switch ($operation) {
			case EventHandler::IS_EXACTLY:
				return $needle == $haystack;
			case EventHandler::CONTAINS_WITH_SPACE:
				// XXX: This needs testing!
				return 1 == preg_match('/\b/', $haystack);
			//return in_array($needle, explode(' ', $haystack));
			case EventHandler::CONTAINS_WITH_HYPHEN:
				return in_array($needle, explode('-', $haystack), true);
			case EventHandler::CONTAINS_IN_STRING:
				return false !== strpos($haystack, $needle);
			case EventHandler::BEGINS_WITH:
				return 0 === strpos($haystack, $needle);
			case EventHandler::ENDS_WITH:
				//return strrpos($haystack, $needle) === strlen($needle) - 1;
				return 1 == preg_match('/' . $needle . '$/', $haystack);
		}

		return false; // Shouldn't be able to get here.
	}

	/**
	 * Remove leading and trailing quotes.
	 */
	public static function removeQuotes(string $str) : string
	{
		$f = mb_substr($str, 0, 1);
		$l = mb_substr($str, -1);
		if ($f === $l && ('"' === $f || "'" === $f)) {
			$str = mb_substr($str, 1, -1);
		}

		return $str;
	}

	/**
	 * Parse an an+b rule for CSS pseudo-classes.
	 *
	 * Invalid rules return `array(0, 0)`. This is per the spec.
	 *
	 * @param string|int|null $rule
	 *  Some rule in the an+b format
	 * @return array{0:int, 1:int}
	 *  `array($aVal, $bVal)` of the two values.
	 */
	public static function parseAnB(string|int|null $rule) : array
	{
		if ('even' === $rule) {
			return [2, 0];
		}

		if ('odd' === $rule) {
			return [2, 1];
		}

		if ('n' === $rule) {
			return [1, 0];
		}

		if (is_numeric($rule)) {
			return [0, (int) $rule];
		}

		$regex = '/^\s*([+\-]?[0-9]*)n\s*([+\-]?)\s*([0-9]*)\s*$/';
		$matches = [];
		$res = preg_match($regex, $rule ?? '', $matches);

		// If it doesn't parse, return 0, 0.
		if ( ! $res) {
			return [0, 0];
		}

		$aVal = $matches[1] ?? 1;
		if ('-' === $aVal) {
			$aVal = -1;
		} else {
			$aVal = (int) $aVal;
		}

		$bVal = 0;
		if (isset($matches[3])) {
			$bVal = (int) $matches[3];
			if (isset($matches[2]) && '-' === $matches[2]) {
				$bVal *= -1;
			}
		}

		return [$aVal, $bVal];
	}
}
