<?php

declare(strict_types=1);

namespace QueryPathTests;

use function func_get_args;

/**
 * Simple utility object for use with the TestEventHandler.
 *
 * @ingroup querypath_tests
 * @group   CSS
 */
class TestEvent
{
	public const ELEMENT_ID = 0;
	public const ELEMENT = 1;
	public const ELEMENT_NS = 2;
	public const ANY_ELEMENT = 3;
	public const ELEMENT_CLASS = 4;
	public const ATTRIBUTE = 5;
	public const ATTRIBUTE_NS = 6;
	public const PSEUDO_CLASS = 7;
	public const PSEUDO_ELEMENT = 8;
	public const DIRECT_DESCENDANT = 9;
	public const ADJACENT = 10;
	public const ANOTHER_SELECTOR = 11;
	public const SIBLING = 12;
	public const ANY_ELEMENT_IN_NS = 13;
	public const ANY_DESCENDANT = 14;

	/**
	 * @var self::ELEMENT_ID|self::ELEMENT|self::ELEMENT_NS|self::ANY_ELEMENT|self::ELEMENT_CLASS|self::ATTRIBUTE|self::ATTRIBUTE_NS|self::PSEUDO_CLASS|self::PSEUDO_ELEMENT|self::DIRECT_DESCENDANT|self::ADJACENT|self::ANOTHER_SELECTOR|self::SIBLING|self::ANY_ELEMENT_IN_NS|self::ANY_DESCENDANT
	 */
	public int $type;

	/** @var list<string|int|null> */
	public array $params;

	/**
	 * @param self::ELEMENT_ID|self::ELEMENT|self::ELEMENT_NS|self::ANY_ELEMENT|self::ELEMENT_CLASS|self::ATTRIBUTE|self::ATTRIBUTE_NS|self::PSEUDO_CLASS|self::PSEUDO_ELEMENT|self::DIRECT_DESCENDANT|self::ADJACENT|self::ANOTHER_SELECTOR|self::SIBLING|self::ANY_ELEMENT_IN_NS|self::ANY_DESCENDANT $event_type
	 */
	public function __construct(int $event_type, string|int|null ...$args)
	{
		$this->type = $event_type;

		/** @var list<string|int|null> */
		$this->params = $args;
	}

	public function compare(self $against) : bool
	{
		return
			$this->type === $against->type
			&& $this->params === $against->params
		;
	}
}
