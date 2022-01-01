<?php

declare(strict_types=1);

namespace QueryPathTests;

use QueryPath\Extension;
use QueryPath\ExtensionRegistry;

/**
 * Create a stub extension.
 *
 * @ingroup querypath_tests
 */
class StubExtensionThree implements Extension
{
	public function __construct(
		private \QueryPath\Query $qp
	)
	{
	}

	public function stuble(string $arg1, string $arg2) : string
	{
		return $arg1 . $arg2;
	}
}

//ExtensionRegistry::extend('StubExtensionOne');
//ExtensionRegistry::extend('StubExtensionTwo');
