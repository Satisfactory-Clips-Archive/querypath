<?php

declare(strict_types=1);

namespace QueryPathTests;

use QueryPath\Query;

class DummyExtension implements \QueryPath\Extension
{
	public function __construct(Query $_qp)
	{
	}

	public function grrrrrrr() : bool
	{
		return true;
	}
}
