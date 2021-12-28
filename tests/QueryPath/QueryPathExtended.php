<?php

declare(strict_types=1);

namespace QueryPathTests;

use QueryPath\DOMQuery;

/**
 * A simple mock for testing qp()'s abstract factory.
 *
 * @ingroup querypath_tests
 */
class QueryPathExtended extends DOMQuery
{
	public function foonator() : bool
	{
		return true;
	}
}
