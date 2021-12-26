<?php

declare(strict_types=1);

namespace QueryPathTests\CSS;

use QueryPath\CSS\Token;
use QueryPathTests\TestCase;

/**
 * @ingroup querypath_tests
 * @group   CSS
 */
class TokenTest extends TestCase
{
	public function testName()
	{
		$this->assertSame('character', (Token::name(0)));
		$this->assertSame('a legal non-alphanumeric character', (Token::name(99)));
		$this->assertSame('end of file', (Token::name(false)));
		$this->assertSame(0, strpos(Token::name(22), 'illegal character'));
	}
}
