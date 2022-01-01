<?php

declare(strict_types=1);

namespace QueryPathTests;

use QueryPath\Extension;
use QueryPath\Query;

/**
 * Create a stub extension.
 *
 * @ingroup querypath_tests
 */
class StubExtensionTwo implements Extension
{
	public function __construct(Query $_qp)
	{
	}

	public function stuble(string $arg1, string $arg2) : string
	{
		return $arg1 . $arg2;
	}
}
