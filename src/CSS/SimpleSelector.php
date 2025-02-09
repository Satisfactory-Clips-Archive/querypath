<?php

declare(strict_types=1);
/** @file
 *
 * A simple selector.
 */

namespace QueryPath\CSS;

use Exception;

/**
 * Models a simple selector.
 *
 * CSS Selectors are composed of one or more simple selectors, where
 * each simple selector may have any of the following components:
 *
 * - An element name (or wildcard *)
 * - An ID (#foo)
 * - One or more classes (.foo.bar)
 * - One or more attribute matchers ([foo=bar])
 * - One or more pseudo-classes (:foo)
 * - One or more pseudo-elements (::first)
 *
 * For performance reasons, this object has been kept as sparse as
 * possible.
 *
 * @since  QueryPath 3.x
 *
 * @author M Butcher
 */
class SimpleSelector
{
	public const ADJACENT = 0x1;
	public const DIRECT_DESCENDANT = 0x2;
	public const ANOTHER_SELECTOR = 0x4;
	public const SIBLING = 0x8;
	public const ANY_DESCENDANT = 0x10;

	public ?string $element = null;
	public ?string $ns = null;
	public ?string $id = null;

	/** @var string[] */
	public array $classes = [];

	/** @var array{ns?:string, name:string, value:string|null, op:int|null}[] */
	public array $attributes = [];

	/** @var array{name:string, value?:string|null}[] */
	public array $pseudoClasses = [];

	/** @var string[] */
	public array $pseudoElements = [];
	public ?int $combinator = null;

	public function __construct()
	{
	}

	public function __toString()
	{
		$buffer = [];
		try {
			if ( ! empty($this->ns)) {
				$buffer[] = $this->ns;
				$buffer[] = '|';
			}
			if ( ! empty($this->element)) {
				$buffer[] = $this->element;
			}
			if ( ! empty($this->id)) {
				$buffer[] = '#' . $this->id;
			}
			if ( ! empty($this->attributes)) {
				foreach ($this->attributes as $attr) {
					$buffer[] = '[';
					if ( ! empty($attr['ns'])) {
						$buffer[] = $attr['ns'] . '|';
					}
					$buffer[] = $attr['name'];
					if ( ! empty($attr['value'])) {
						$buffer[] = self::attributeOperator($attr['op']);
						$buffer[] = $attr['value'];
					}
					$buffer[] = ']';
				}
			}
			if ( ! empty($this->pseudoClasses)) {
				foreach ($this->pseudoClasses as $ps) {
					$buffer[] = ':' . $ps['name'];
					if (isset($ps['value'])) {
						$buffer[] = '(' . $ps['value'] . ')';
					}
				}
			}
			foreach ($this->pseudoElements as $pe) {
				$buffer[] = '::' . $pe;
			}

			if ( ! empty($this->combinator)) {
				$buffer[] = self::combinatorOperator($this->combinator);
			}
		} catch (Exception $e) {
			return $e->getMessage();
		}

		return implode('', $buffer);
	}

	public static function attributeOperator(?int $code) : string
	{
		switch ($code) {
			case EventHandler::CONTAINS_WITH_SPACE:
				return '~=';
			case EventHandler::CONTAINS_WITH_HYPHEN:
				return '|=';
			case EventHandler::CONTAINS_IN_STRING:
				return '*=';
			case EventHandler::BEGINS_WITH:
				return '^=';
			case EventHandler::ENDS_WITH:
				return '$=';
			default:
				return '=';
		}
	}

	public static function combinatorOperator(int $code) : string
	{
		switch ($code) {
			case self::ADJACENT:
				return '+';
			case self::DIRECT_DESCENDANT:
				return '>';
			case self::SIBLING:
				return '~';
			case self::ANOTHER_SELECTOR:
				return ', ';
			case self::ANY_DESCENDANT:
				return '   ';
			default:
				return '';
		}
	}

	public function notEmpty() : bool
	{
		return ! empty($this->element)
			&& ! empty($this->id)
			&& ! empty($this->classes)
			&& ! empty($this->combinator)
			&& ! empty($this->attributes)
			&& ! empty($this->pseudoClasses)
			&& ! empty($this->pseudoElements);
	}
}
