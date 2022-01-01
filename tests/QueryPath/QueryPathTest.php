<?php

declare(strict_types=1);

namespace QueryPathTests;

use QueryPath\DOMQuery;
use QueryPath\QueryPath;

class QueryPathTest extends TestCase
{
	public function testWith() : void
	{
		$qp = QueryPath::with(QueryPath::XHTML_STUB);

		$this->assertInstanceOf(DOMQuery::class, $qp);
	}

	public function testWithHTML() : void
	{
		$qp = QueryPath::with(QueryPath::HTML_STUB);

		$this->assertInstanceOf(DOMQuery::class, $qp);
	}

	public function testWithHTML5() : void
	{
		$qp = QueryPath::withHTML5(QueryPath::HTML5_STUB);

		$this->assertInstanceOf(DOMQuery::class, $qp);
	}

	public function testWithXML() : void
	{
		$qp = QueryPath::with(QueryPath::XHTML_STUB);

		$this->assertInstanceOf(DOMQuery::class, $qp);
	}

	public function testEnable() : void
	{
		QueryPath::enable(DummyExtension::class);

		/** @var DOMQuery&DummyExtension */
		$qp = QueryPath::with(QueryPath::XHTML_STUB);

		$this->assertTrue($qp->grrrrrrr());
	}
}
