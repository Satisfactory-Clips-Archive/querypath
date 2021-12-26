<?php

declare(strict_types=1);

namespace QueryPathTests\CSS;

use QueryPath\CSS\DOMTraverser\Util;
use QueryPathTests\TestCase;

/**
 * @ingroup querypath_tests
 * @group   CSS
 */
class UtilTest extends TestCase
{
	public function testRemoveQuotes()
	{
		$this->assertSame('foo', Util::removeQuotes('"foo"'));
		$this->assertSame('foo', Util::removeQuotes("'foo'"));
		$this->assertSame('"foo\'', Util::removeQuotes("\"foo'"));
		$this->assertSame('f"o"o', Util::removeQuotes('f"o"o'));
	}

	public function testParseAnB()
	{
		// even
		$this->assertSame([2, 0], Util::parseAnB('even'));
		// odd
		$this->assertSame([2, 1], Util::parseAnB('odd'));
		// 5
		$this->assertSame([0, 5], Util::parseAnB('5'));
		// +5
		$this->assertSame([0, 5], Util::parseAnB('+5'));
		// n
		$this->assertSame([1, 0], Util::parseAnB('n'));
		// 2n
		$this->assertSame([2, 0], Util::parseAnB('2n'));
		// -234n
		$this->assertSame([-234, 0], Util::parseAnB('-234n'));
		// -2n+1
		$this->assertSame([-2, 1], Util::parseAnB('-2n+1'));
		// -2n + 1
		$this->assertSame([-2, 1], Util::parseAnB(' -2n + 1   '));
		// +2n-1
		$this->assertSame([2, -1], Util::parseAnB('2n-1'));
		$this->assertSame([2, -1], Util::parseAnB('2n   -   1'));
		// -n + 3
		$this->assertSame([-1, 3], Util::parseAnB('-n+3'));

		// Test invalid values
		$this->assertSame([0, 0], Util::parseAnB('obviously + invalid'));
	}
}
