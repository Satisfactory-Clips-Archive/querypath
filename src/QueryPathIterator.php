<?php

declare(strict_types=1);
/**
 * @file
 *
 * Utility iterator for QueryPath.
 */

namespace QueryPath;

use DOMNode;
use function is_null;
use IteratorIterator;
use SplObjectStorage;
use function sprintf;
use Traversable;
use UnexpectedValueException;

/**
 * An iterator for QueryPath.
 *
 * This provides iterator support for QueryPath. You do not need to construct
 * a QueryPathIterator. QueryPath does this when its QueryPath::getIterator()
 * method is called.
 *
 * @ingroup querypath_util
 */
class QueryPathIterator extends IteratorIterator
{
	/**
	 * @var array{
	 *	QueryPath_class?:class-string<DOMQuery>
	 * }
	 */
	public array $options = [];
	private ?DOMQuery $qp = null;

	/**
	 * @param SplObjectStorage<DOMNode|TextContent, mixed> $matches
	 */
	public function __construct(SplObjectStorage $matches)
	{
		parent::__construct($matches);
	}

	public function current() : DOMQuery
	{
		$parent_current = parent::current();

		assert(
			(
				($parent_current instanceof DOMNode)
				|| ($parent_current instanceof TextContent)
			),
			new UnexpectedValueException(sprintf(
				'Unsupported value found %s',
				gettype($parent_current)
			))
		);

		if ( ! isset($this->qp)) {
			$this->qp = QueryPath::with($parent_current, null, $this->options);
		} else {
			/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
			$splos = new SplObjectStorage();
			$splos->attach($parent_current);
			$this->qp->setMatches($splos);
		}

		return $this->qp;
	}
}
