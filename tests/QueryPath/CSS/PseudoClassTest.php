<?php

declare(strict_types=1);

namespace QueryPathTests\CSS;

use DOMDocument;
use DOMElement;
use DOMNode;
use QueryPath\CSS\DOMTraverser\PseudoClass;
use QueryPathTests\TestCase;

/**
 * @ingroup querypath_tests
 * @group   CSS
 */
class PseudoClassTest extends TestCase
{
	public function testUnknownPseudoClass() : void
	{
		$xml = '<?xml version="1.0"?><root><foo>test</foo></root>';

		[$ele, $root] = $this->doc($xml, 'foo');
		$ps = new PseudoClass();

		$this->expectException(\QueryPath\CSS\ParseException::class);
		$ps->elementMatches('TotallyFake', $ele, $root);
	}

	public function testLang() : void
	{
		$xml = '<?xml version="1.0"?><root><foo lang="en-US">test</foo></root>';

		[$ele, $root] = $this->doc($xml, 'foo');
		$ps = new PseudoClass();

		$ret = $ps->elementMatches('lang', $ele, $root, 'en-US');
		$this->assertTrue($ret);
		$ret = $ps->elementMatches('lang', $ele, $root, 'en');
		$this->assertTrue($ret);
		$ret = $ps->elementMatches('lang', $ele, $root, 'fr-FR');
		$this->assertFalse($ret);
		$ret = $ps->elementMatches('lang', $ele, $root, 'fr');
		$this->assertFalse($ret);

		// Check on ele that doesn't have lang.
		$ret = $ps->elementMatches('lang', $root, $root, 'fr');
		$this->assertFalse($ret);
	}

	public function testLangNS() : void
	{
		$xml = '<?xml version="1.0"?><root><foo xml:lang="en-US">test</foo></root>';

		[$ele, $root] = $this->doc($xml, 'foo');
		$ps = new PseudoClass();

		$ret = $ps->elementMatches('lang', $ele, $root, 'en-US');
		$this->assertTrue($ret);
		$ret = $ps->elementMatches('lang', $ele, $root, 'en');
		$this->assertTrue($ret);
		$ret = $ps->elementMatches('lang', $ele, $root, 'fr-FR');
		$this->assertFalse($ret);
		$ret = $ps->elementMatches('lang', $ele, $root, 'fr');
		$this->assertFalse($ret);

		// Check on ele that doesn't have lang.
		$ret = $ps->elementMatches('lang', $root, $root, 'fr');
		$this->assertFalse($ret);
	}

	public function testFormType() : void
	{
		$xml = '<?xml version="1.0"?><root><foo type="submit">test</foo></root>';

		[$ele, $root] = $this->doc($xml, 'foo');
		$ps = new PseudoClass();

		$ret = $ps->elementMatches('submit', $ele, $root);
		$this->assertTrue($ret);

		$ret = $ps->elementMatches('reset', $ele, $root);
		$this->assertFalse($ret);
	}

	public function testHasAttribute() : void
	{
		$xml = '<?xml version="1.0"?><root><foo enabled="enabled">test</foo></root>';

		[$ele, $root] = $this->doc($xml, 'foo');
		$ps = new PseudoClass();

		$ret = $ps->elementMatches('enabled', $ele, $root);
		$this->assertTrue($ret);
		$ret = $ps->elementMatches('disabled', $ele, $root);
		$this->assertFalse($ret);
	}

	public function testHeader() : void
	{
		$xml = '<?xml version="1.0"?><root><h1>TEST</h1><H6></H6><hi/><h12/><h1i/></root>';

		[$ele, $root] = $this->doc($xml, 'h1');
		$ps = new PseudoClass();

		$ret = $ps->elementMatches('header', $ele, $root);
		$this->assertTrue($ret);

		[$ele, $root] = $this->doc($xml, 'H6');
		$ret = $ps->elementMatches('header', $ele, $root);
		$this->assertTrue($ret);

		[$ele, $root] = $this->doc($xml, 'hi');
		$ret = $ps->elementMatches('header', $ele, $root);
		$this->assertFalse($ret);
		[$ele, $root] = $this->doc($xml, 'h1i');
		$ret = $ps->elementMatches('header', $ele, $root);
		$this->assertFalse($ret);
		[$ele, $root] = $this->doc($xml, 'h12');
		$ret = $ps->elementMatches('header', $ele, $root);
		$this->assertFalse($ret);
	}

	public function testContains() : void
	{
		$xml = '<?xml version="1.0"?><root><h>This is a test of :contains.</h></root>';

		[$ele, $root] = $this->doc($xml, 'h');
		$ps = new PseudoClass();

		$ret = $ps->elementMatches('contains', $ele, $root, 'test');
		$this->assertTrue($ret);

		$ret = $ps->elementMatches('contains', $ele, $root, 'is a test');
		$this->assertTrue($ret);

		$ret = $ps->elementMatches('contains', $ele, $root, 'This is a test of :contains.');
		$this->assertTrue($ret);

		$ret = $ps->elementMatches('contains', $ele, $root, 'Agent P, here is your mission.');
		$this->assertFalse($ret);

		$ret = $ps->elementMatches('contains', $ele, $root, "'Agent P, here is your mission.'");
		$this->assertFalse($ret);
	}

	public function testContainsExactly() : void
	{
		$xml = '<?xml version="1.0"?><root><h>This is a test of :contains-exactly.</h></root>';

		[$ele, $root] = $this->doc($xml, 'h');
		$ps = new PseudoClass();

		$ret = $ps->elementMatches('contains-exactly', $ele, $root, 'test');
		$this->assertFalse($ret);

		$ret = $ps->elementMatches('contains-exactly', $ele, $root, 'This is a test of :contains-exactly.');
		$this->assertTrue($ret);

		$ret = $ps->elementMatches('contains-exactly', $ele, $root, 'Agent P, here is your mission.');
		$this->assertFalse($ret);

		$ret = $ps->elementMatches('contains-exactly', $ele, $root, '"Agent P, here is your mission."');
		$this->assertFalse($ret);
	}

	public function testHas() : void
	{
		$xml = '<?xml version="1.0"?><root><button disabled="disabled"/></root>';

		[$ele, $root] = $this->doc($xml, 'button');
		$ps = new PseudoClass();

		// Example from CSS 4 spec
		$ret = $ps->elementMatches('matches', $ele, $root, '[disabled]');
		$this->assertTrue($ret);

		// Regression for Issue #84:
		$xml = '<?xml version="1.0"?><root><a/><a/><a src="/foo/bar"/><b src="http://example.com/foo/bar"/></root>';

		[$ele, $root] = $this->doc($xml, 'root');
		$nl = $ele->childNodes;
		$ps = new PseudoClass();

		$i = 0;
		foreach ($nl as $n) {
			$ret = $ps->elementMatches('matches', $n, $root, '[src^="/foo/"]');
			if ($ret) {
				++$i;
			}
		}
		$this->assertSame(1, $i);
	}

	public function testParent() : void
	{
		$ps = new PseudoClass();

		$xml = '<?xml version="1.0"?><root><p/></root>';
		[$ele, $root] = $this->doc($xml, 'p');
		$ret = $ps->elementMatches('parent', $ele, $root);
		$this->assertFalse($ret);

		$xml = '<?xml version="1.0"?><root><p></p>></root>';
		[$ele, $root] = $this->doc($xml, 'p');
		$ret = $ps->elementMatches('parent', $ele, $root);
		$this->assertFalse($ret);

		$xml = '<?xml version="1.0"?><root><p>TEST</p></root>';
		[$ele, $root] = $this->doc($xml, 'p');
		$ret = $ps->elementMatches('parent', $ele, $root);
		$this->assertTrue($ret);

		$xml = '<?xml version="1.0"?><root><p><q/></p></root>';
		[$ele, $root] = $this->doc($xml, 'p');
		$ret = $ps->elementMatches('parent', $ele, $root);
		$this->assertTrue($ret);
	}

	public function testFirst() : void
	{
		$ps = new PseudoClass();
		$xml = '<?xml version="1.0"?><root><p><q/></p><a></a><b/></root>';

		[$ele, $root] = $this->doc($xml, 'q');
		$ret = $ps->elementMatches('first', $ele, $root);
		$this->assertTrue($ret);

		[$ele, $root] = $this->doc($xml, 'p');
		$ret = $ps->elementMatches('first', $ele, $root);
		$this->assertTrue($ret);

		[$ele, $root] = $this->doc($xml, 'b');
		$ret = $ps->elementMatches('first', $ele, $root);
		$this->assertFalse($ret);
	}

	public function testLast() : void
	{
		$ps = new PseudoClass();
		$xml = '<?xml version="1.0"?><root><p><q/></p><a></a><b/></root>';

		[$ele, $root] = $this->doc($xml, 'q');
		$ret = $ps->elementMatches('last', $ele, $root);
		$this->assertTrue($ret);

		[$ele, $root] = $this->doc($xml, 'p');
		$ret = $ps->elementMatches('last', $ele, $root);
		$this->assertFalse($ret);

		[$ele, $root] = $this->doc($xml, 'b');
		$ret = $ps->elementMatches('last', $ele, $root);
		$this->assertTrue($ret);
	}

	public function testNot() : void
	{
		$xml = '<?xml version="1.0"?><root><button/></root>';

		[$ele, $root] = $this->doc($xml, 'button');
		$ps = new PseudoClass();

		// Example from CSS 4 spec
		$ret = $ps->elementMatches('not', $ele, $root, '[disabled]');
		$this->assertTrue($ret);

		$xml = '<?xml version="1.0"?><root><b/><b/><c/><b/></root>';
		[$ele, $root] = $this->doc($xml, 'root');
		$nl = $ele->childNodes;

		$i = 0;
		foreach ($nl as $n) {
			$ret = $ps->elementMatches('not', $n, $root, 'c');
			if ($ret) {
				++$i;
			}
		}
		$this->assertSame(3, $i);
	}

	public function testEmpty() : void
	{
		$xml = '<?xml version="1.0"?><root><foo lang="en-US">test</foo><bar/><baz></baz></root>';

		[$ele, $root] = $this->doc($xml, 'foo');
		$ps = new PseudoClass();

		$ret = $ps->elementMatches('empty', $ele, $root);
		$this->assertFalse($ret);

		[$ele, $root] = $this->doc($xml, 'bar');
		$ret = $ps->elementMatches('empty', $ele, $root);
		$this->assertTrue($ret);

		[$ele, $root] = $this->doc($xml, 'baz');
		$ret = $ps->elementMatches('empty', $ele, $root);
		$this->assertTrue($ret);
	}

	public function testOnlyChild() : void
	{
		$xml = '<?xml version="1.0"?><root><foo>test<a/></foo><b><c/></b></root>';
		$ps = new PseudoClass();

		[$ele, $root] = $this->doc($xml, 'foo');
		$ret = $ps->elementMatches('only-child', $ele, $root);
		$this->assertFalse($ret);

		[$ele, $root] = $this->doc($xml, 'a');
		$ret = $ps->elementMatches('only-child', $ele, $root);
		$this->assertTrue($ret);

		[$ele, $root] = $this->doc($xml, 'c');
		$ret = $ps->elementMatches('only-child', $ele, $root);
		$this->assertTrue($ret);
	}

	public function testLastOfType() : void
	{
		$xml = '<?xml version="1.0"?><root><one><a/><b/><c/></one><two><d/><d/><b/></two></root>';
		$ps = new PseudoClass();

		[$ele, $root] = $this->doc($xml, 'a');
		$ret = $ps->elementMatches('last-of-type', $ele, $root);
		$this->assertTrue($ret);

		[, $root] = $this->doc($xml, 'a');
		$nl = $root->getElementsByTagName('d');

		$ret = $ps->elementMatches('last-of-type', $nl->item(0), $root);
		$this->assertFalse($ret);

		$ret = $ps->elementMatches('last-of-type', $nl->item(1), $root);
		$this->assertTrue($ret);
	}

	public function testFirstOftype() : void
	{
		$xml = '<?xml version="1.0"?><root><one><a/><b/><c/></one><two><d/><d/><b/></two></root>';
		$ps = new PseudoClass();

		[$ele, $root] = $this->doc($xml, 'a');
		$ret = $ps->elementMatches('first-of-type', $ele, $root);
		$this->assertTrue($ret);

		[, $root] = $this->doc($xml, 'a');
		$nl = $root->getElementsByTagName('d');

		$ret = $ps->elementMatches('first-of-type', $nl->item(0), $root);
		$this->assertTrue($ret);

		$ret = $ps->elementMatches('first-of-type', $nl->item(1), $root);
		$this->assertFalse($ret);
	}

	public function testOnlyOfType() : void
	{
		$xml = '<?xml version="1.0"?><root><one><a/><b/><c/></one><two><d/><d/><b/></two></root>';
		$ps = new PseudoClass();

		[$ele, $root] = $this->doc($xml, 'a');
		$ret = $ps->elementMatches('only-of-type', $ele, $root);
		$this->assertTrue($ret);

		[, $root] = $this->doc($xml, 'a');
		$nl = $root->getElementsByTagName('d');

		$ret = $ps->elementMatches('only-of-type', $nl->item(0), $root);
		$this->assertFalse($ret);

		$ret = $ps->elementMatches('only-of-type', $nl->item(1), $root);
		$this->assertFalse($ret);
	}

	public function testNthLastChild() : void
	{
		$xml = '<?xml version="1.0"?><root>';
		$xml .= str_repeat('<a/><b/><c/><d/>', 5);
		$xml .= '</root>';

		$ps = new PseudoClass();
		[, $root] = $this->doc($xml, 'root');
		$nl = $root->childNodes;

		// 2n + 1 -- Every odd row, from the last element.
		$i = 0;
		$expects = ['b', 'd'];
		$j = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-last-child', $n, $root, '2n+1');
			if ($res) {
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
				$name = $n->tagName;
				$this->assertContains($name, $expects, sprintf('Expected b or d, got %s in slot %s', $name, ++$j));
			}
		}
		$this->assertSame(10, $i, '2n+1 is ten items.');

		// 3 (0n+3) -- third from the end (b).
		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-last-child', $n, $root, '3');
			if ($res) {
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
				$this->assertSame('b', $n->tagName);
			}
		}
		$this->assertSame(1, $i);

		// -n+3: Last three elements.
		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-last-child', $n, $root, '-n+3');
			if ($res) {
				++$i;
			}
		}
		$this->assertSame(3, $i);
	}

	public function testNthChild() : void
	{
		$xml = '<?xml version="1.0"?><root>';
		$xml .= str_repeat('<a/><b/><c/><d/>', 5);
		$xml .= '</root>';

		$ps = new PseudoClass();
		[, $root] = $this->doc($xml, 'root');
		$nl = $root->childNodes;

		// 2n + 1 -- Every odd row.
		$i = 0;
		$expects = ['a', 'c'];
		$j = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-child', $n, $root, '2n+1');
			if ($res) {
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
				$name = $n->tagName;
				$this->assertContains($name, $expects, sprintf('Expected b or d, got %s in slot %s', $name, ++$j));
			}
		}
		$this->assertSame(10, $i, '2n+1 is ten items.');

		// Odd
		$i = 0;
		$expects = ['a', 'c'];
		$j = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-child', $n, $root, 'odd');
			if ($res) {
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
				$name = $n->tagName;
				$this->assertContains($name, $expects, sprintf('Expected b or d, got %s in slot %s', $name, ++$j));
			}
		}
		$this->assertSame(10, $i, '2n+1 is ten items.');

		// 2n + 0 -- every even row
		$i = 0;
		$expects = ['b', 'd'];
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-child', $n, $root, '2n');
			if ($res) {
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
				$name = $n->tagName;
				$this->assertContains($name, $expects, 'Expected a or c, got ' . $name);
			}
		}
		$this->assertSame(10, $i, '2n+0 is ten items.');

		// Even (2n)
		$i = 0;
		$expects = ['b', 'd'];
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-child', $n, $root, 'even');
			if ($res) {
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
				$name = $n->tagName;
				$this->assertContains($name, $expects, 'Expected a or c, got ' . $name);
			}
		}
		$this->assertSame(10, $i, ' even is ten items.');

		// 4n - 1 == 4n + 3
		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-child', $n, $root, '4n-1');
			if ($res) {
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
				$name = $n->tagName;
				$this->assertSame('c', $name, 'Expected c, got ' . $name);
			}
		}
		$this->assertSame(5, $i);

		// 6n - 1
		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-child', $n, $root, '6n-1');
			if ($res) {
				++$i;
			}
		}
		$this->assertSame(3, $i);

		// 6n + 1
		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-child', $n, $root, '6n+1');
			if ($res) {
				++$i;
			}
		}
		$this->assertSame(4, $i);

		// 26n - 1
		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-child', $n, $root, '26n-1');
			if ($res) {
				++$i;
			}
		}
		$this->assertSame(0, $i);

		// 0n + 0 -- spec says this is always FALSE.
		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-child', $n, $root, '0n+0');
			if ($res) {
				++$i;
			}
		}
		$this->assertSame(0, $i);

		// 3 (0n+3)
		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-child', $n, $root, '3');
			if ($res) {
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
				$this->assertSame('c', $n->tagName);
			}
		}
		$this->assertSame(1, $i);

		// -n+3: First three elements.
		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-child', $n, $root, '-n+3');
			if ($res) {
				++$i;
				//$this->assertEquals('c', $n->tagName);
			}
		}
		$this->assertSame(3, $i);

		// BROKEN RULES -- these should always fail to match.

		// 6n + 7;
		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-child', $n, $root, '6n+7');
			if ($res) {
				++$i;
			}
		}
		$this->assertSame(0, $i);
	}

	public function testEven() : void
	{
		$xml = '<?xml version="1.0"?><root>';
		$xml .= str_repeat('<a/><b/><c/><d/>', 5);
		$xml .= '</root>';

		$ps = new PseudoClass();
		[, $root] = $this->doc($xml, 'root');
		$nl = $root->childNodes;

		$i = 0;
		$expects = ['b', 'd'];
		foreach ($nl as $n) {
			$res = $ps->elementMatches('even', $n, $root);
			if ($res) {
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
				$name = $n->tagName;
				$this->assertContains($name, $expects, 'Expected a or c, got ' . $name);
			}
		}
		$this->assertSame(10, $i, ' even is ten items.');
	}

	public function testOdd() : void
	{
		$xml = '<?xml version="1.0"?><root>';
		$xml .= str_repeat('<a/><b/><c/><d/>', 5);
		$xml .= '</root>';

		$ps = new PseudoClass();
		[, $root] = $this->doc($xml, 'root');
		$nl = $root->childNodes;

		// Odd
		$i = 0;
		$expects = ['a', 'c'];
		$j = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('odd', $n, $root);
			if ($res) {
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
				$name = $n->tagName;
				$this->assertContains($name, $expects, sprintf('Expected b or d, got %s in slot %s', $name, ++$j));
			}
		}
		$this->assertSame(10, $i, 'Ten odds.');
	}

	public function testNthOfTypeChild() : void
	{
		$xml = '<?xml version="1.0"?><root>';
		$xml .= str_repeat('<a/><a/><a/><a/>', 5);
		$xml .= '</root>';

		$ps = new PseudoClass();
		[, $root] = $this->doc($xml, 'root');
		$nl = $root->childNodes;

		// Odd
		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-of-type', $n, $root, '2n+1');
			if ($res) {
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
				$name = $n->tagName;
				$this->assertSame('a', $name);
			}
		}
		$this->assertSame(10, $i, 'Ten odds.');

		// Fun with ambiguous pseudoclasses:
		// This emulates the selector 'root > :nth-of-type(2n+1)'
		$xml = '<?xml version="1.0"?><root>';
		$xml .= '<a/><b/><c/><a/><a/><a/>';
		$xml .= '</root>';

		[, $root] = $this->doc($xml, 'root');
		$nl = $root->childNodes;

		// Odd
		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth-of-type', $n, $root, '2n+1');
			if ($res) {
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
			}
		}
		// THis should be: 2 x a + 1 x b + 1 x c = 4
		$this->assertSame(4, $i, 'Four odds.');
	}

	public function testNthLastOfTypeChild() : void
	{
		$xml = '<?xml version="1.0"?><root>';
		$xml .= '<a/><a/><OOPS/><a/><a/>';
		$xml .= '</root>';

		$ps = new PseudoClass();
		[, $root] = $this->doc($xml, 'root');
		$nl = $root->childNodes;

		// Third from beginning is second from last.
		$third = $nl->item(2);
		$res = $ps->elementMatches('nth-last-of-type', $third, $root, '3');
		$this->assertFalse($res);

		$first = $nl->item(0);
		$res = $ps->elementMatches('nth-last-of-type', $first, $root, '3');
		$this->assertFalse($res);

		$last = $nl->item(3);
		$res = $ps->elementMatches('nth-last-of-type', $last, $root, '3');
		$this->assertFalse($res);

		// Second from start is 3rd from last
		$second = $nl->item(1);
		$res = $ps->elementMatches('nth-last-of-type', $second, $root, '3');
		$this->assertTrue($res);
	}

	public function testLink() : void
	{
		$ps = new PseudoClass();
		$xml = '<?xml version="1.0"?><root><a href="foo"><b hreff="bar">test</b></a><c/></root>';

		[$ele, $root] = $this->doc($xml, 'c');
		$ret = $ps->elementMatches('link', $ele, $root);
		$this->assertFalse($ret);

		[$ele, $root] = $this->doc($xml, 'a');
		$ret = $ps->elementMatches('link', $ele, $root);
		$this->assertTrue($ret);

		[$ele, $root] = $this->doc($xml, 'b');
		$ret = $ps->elementMatches('link', $ele, $root);
		$this->assertFalse($ret);
	}

	public function testRoot() : void
	{
		$ps = new PseudoClass();
		$xml = '<?xml version="1.0"?><root><p><q/></p><a></a><b/></root>';

		[$ele, $root] = $this->doc($xml, 'q');
		$ret = $ps->elementMatches('root', $ele, $root);
		$this->assertFalse($ret);

		[$ele, $root] = $this->doc($xml, 'root');
		$ret = $ps->elementMatches('root', $ele, $root);
		$this->assertTrue($ret);
	}

	public function testLt() : void
	{
		$xml = '<?xml version="1.0"?><root>';
		$xml .= str_repeat('<a/><a/><a/><a/>', 5);
		$xml .= '</root>';

		$ps = new PseudoClass();
		[, $root] = $this->doc($xml, 'root');
		$nl = $root->childNodes;

		// Odd
		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('lt', $n, $root, '15');
			if ($res) {
				static::assertInstanceOf(DOMElement::class, $n);
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
			}
		}
		$this->assertSame(15, $i, 'Less than or equal to 15.');
	}

	public function testGt() : void
	{
		$xml = '<?xml version="1.0"?><root>';
		$xml .= str_repeat('<a/><a/><a/><a/>', 5);
		$xml .= '</root>';

		$ps = new PseudoClass();
		[, $root] = $this->doc($xml, 'root');
		$nl = $root->childNodes;

		// Odd
		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('gt', $n, $root, '15');
			if ($res) {
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
			}
		}
		$this->assertSame(5, $i, 'Greater than the 15th element.');
	}

	public function testEq() : void
	{
		$xml = '<?xml version="1.0"?><root>';
		$xml .= str_repeat('<a/><b/><c/><a/>', 5);
		$xml .= '</root>';

		$ps = new PseudoClass();
		[, $root] = $this->doc($xml, 'root');
		$nl = $root->childNodes;

		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('eq', $n, $root, '15');
			if ($res) {
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
				$name = $n->tagName;
				$this->assertSame('c', $name);
			}
		}
		$this->assertSame(1, $i, 'The 15th element.');

		$i = 0;
		foreach ($nl as $n) {
			$res = $ps->elementMatches('nth', $n, $root, '15');
			if ($res) {
				++$i;
				static::assertInstanceOf(DOMElement::class, $n);
				$name = $n->tagName;
				$this->assertSame('c', $name);
			}
		}
		$this->assertSame(1, $i, 'The 15th element.');
	}

	public function testAnyLink() : void
	{
		$ps = new PseudoClass();
		$xml = '<?xml version="1.0"?><root><a href="foo"><b hreff="bar">test</b></a><c/><d src="foo"/></root>';

		[$ele, $root] = $this->doc($xml, 'c');
		$ret = $ps->elementMatches('any-link', $ele, $root);
		$this->assertFalse($ret);

		[$ele, $root] = $this->doc($xml, 'a');
		$ret = $ps->elementMatches('any-link', $ele, $root);
		$this->assertTrue($ret);

		[$ele, $root] = $this->doc($xml, 'd');
		$ret = $ps->elementMatches('any-link', $ele, $root);
		$this->assertTrue($ret);

		[$ele, $root] = $this->doc($xml, 'b');
		$ret = $ps->elementMatches('any-link', $ele, $root);
		$this->assertFalse($ret);
	}

	public function testLocalLink() : void
	{
		$ps = new PseudoClass();
		$xml = '<?xml version="1.0"?><root><a href="foo"><b href="http://example.com/bar">test</b></a><c/><d href="file://foo"/></root>';

		[$ele, $root] = $this->doc($xml, 'c');
		$ret = $ps->elementMatches('local-link', $ele, $root);
		$this->assertFalse($ret);

		[$ele, $root] = $this->doc($xml, 'a');
		$ret = $ps->elementMatches('local-link', $ele, $root);
		$this->assertTrue($ret);

		[$ele, $root] = $this->doc($xml, 'd');
		$ret = $ps->elementMatches('local-link', $ele, $root);
		$this->assertTrue($ret);

		[$ele, $root] = $this->doc($xml, 'b');
		$ret = $ps->elementMatches('local-link', $ele, $root);
		$this->assertFalse($ret);
	}

	public function testScope() : void
	{
		$ps = new PseudoClass();
		$xml = '<?xml version="1.0"?><root><a href="foo"><b>test</b></a></root>';

		[$ele] = $this->doc($xml, 'a');
		$node = $ele->childNodes->item(0);
		$ret = $ps->elementMatches('scope', $node, $ele);
		$this->assertFalse($ret);
	}

	/**
	 * @return array{0:DOMNode, 1:DOMElement}
	 */
	protected function doc(string $string, string $tagname) : array
	{
		$doc = new DOMDocument('1.0');
		$doc->loadXML($string);
		$found = $doc->getElementsByTagName($tagname)->item(0);

		return [$found, $doc->documentElement];
	}
}
