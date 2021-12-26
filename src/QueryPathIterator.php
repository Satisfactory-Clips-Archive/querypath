<?php

declare(strict_types=1);
/**
 * @file
 *
 * Utility iterator for QueryPath.
 */

namespace QueryPath;

use QueryPath\QueryPath;

/**
 * An iterator for QueryPath.
 *
 * This provides iterator support for QueryPath. You do not need to construct
 * a QueryPathIterator. QueryPath does this when its QueryPath::getIterator()
 * method is called.
 *
 * @ingroup querypath_util
 */
class QueryPathIterator extends \IteratorIterator
{

	public $options = [];
	private $qp;

	public function current() : mixed
	{
		if (!isset($this->qp)) {
			$this->qp = QueryPath::with(parent::current(), null, $this->options);
		} else {
			$splos = new \SplObjectStorage();
			$splos->attach(parent::current());
			$this->qp->setMatches($splos);
		}

		return $this->qp;
	}
}
