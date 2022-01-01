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
	public function __construct(
		private \QueryPath\Query $qp
	)
	{
	}

	public function stubToe() : \QueryPath\Query
	{
		$this->qp->top()->append('<toe/>')->end();

		return $this->qp;
	}
}
