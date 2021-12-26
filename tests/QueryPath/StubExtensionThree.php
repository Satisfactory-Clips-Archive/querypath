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
	private $qp;

	public function __construct(\QueryPath\Query $qp)
	{
		$this->qp = $qp;
	}

	public function stuble($arg1, $arg2)
	{
		return $arg1 . $arg2;
	}
}

//ExtensionRegistry::extend('StubExtensionOne');
//ExtensionRegistry::extend('StubExtensionTwo');
