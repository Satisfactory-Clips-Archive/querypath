<?php

declare(strict_types=1);

namespace QueryPathTests;

class DummyExtension implements \QueryPath\Extension
{
	public function __construct(
		private \QueryPath\Query $qp
	) {
	}

	public function grrrrrrr() : bool
	{
		return true;
	}
}
