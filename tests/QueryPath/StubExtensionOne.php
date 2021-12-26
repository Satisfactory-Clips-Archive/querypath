<?php

declare(strict_types=1);

namespace QueryPathTests;

use QueryPath\Extension;

// Create a stub extension:

/**
 * Create a stub extension.
 *
 * @ingroup querypath_tests
 */
class StubExtensionOne implements Extension
{
	private $qp = null;

	public function __construct(\QueryPath\Query $qp)
	{
		$this->qp = $qp;
	}

	public function stubToe()
	{
		$this->qp->top()->append('<toe/>')->end();

		return $this->qp;
	}
}
