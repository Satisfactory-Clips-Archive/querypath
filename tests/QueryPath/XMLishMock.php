<?php

declare(strict_types=1);

namespace QueryPathTests;

/**
 * A testing class for XMLish tests.
 *
 * @ingroup querypath_tests
 */
class XMLishMock extends \QueryPath\DOMQuery
{
	public function exposedIsXMLish($str)
	{
		return $this->isXMLish($str);
	}
}
