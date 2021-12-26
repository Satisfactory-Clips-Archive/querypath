<?php

declare(strict_types=1);

namespace QueryPathTests;

use QueryPath\Extension;

/**
 * Create a stub extension.
 *
 * @ingroup querypath_tests
 */
class StubExtensionTwo implements Extension
{
	private $qp = null;

	public function __construct(\QueryPath\Query $qp)
	{
		$this->qp = $qp;
	}

	public function stuble($arg1, $arg2)
	{
		return $arg1 . $arg2;
	}
}
