<?php

declare(strict_types=1);

namespace QueryPathTests\CSS;

use Iterator;
use QueryPath\CSS\EventHandler;
use QueryPath\CSS\Selector;
use QueryPath\CSS\SimpleSelector;
use QueryPathTests\TestCase;

class SelectorTest extends TestCase
{
	public function testElement() : void
	{
		$selector = $this->parse('test')->toArray();

		$this->assertCount(1, $selector);
		$this->assertSame('test', $selector[0]['0']->element);
	}

	public function testElementNS() : void
	{
		$selector = $this->parse('foo|test')->toArray();

		$this->assertCount(1, $selector);
		$this->assertSame('test', $selector[0]['0']->element);
		$this->assertSame('foo', $selector[0]['0']->ns);
	}

	public function testId() : void
	{
		$selector = $this->parse('#test')->toArray();

		$this->assertCount(1, $selector);
		$this->assertSame('test', $selector[0][0]->id);
	}

	public function testClasses() : void
	{
		$selector = $this->parse('.test')->toArray();

		$this->assertCount(1, $selector);
		$this->assertSame('test', $selector[0][0]->classes[0]);

		$selector = $this->parse('.test.foo.bar')->toArray();
		$this->assertSame('test', $selector[0][0]->classes[0]);
		$this->assertSame('foo', $selector[0][0]->classes[1]);
		$this->assertSame('bar', $selector[0][0]->classes[2]);
	}

	public function testAttributes() : void
	{
		$selector = $this->parse('foo[bar=baz]')->toArray();
		$this->assertCount(1, $selector);
		$attrs = $selector[0][0]->attributes;

		$this->assertCount(1, $attrs);

		$attr = $attrs[0];
		$this->assertSame('bar', $attr['name']);
		$this->assertSame(EventHandler::IS_EXACTLY, $attr['op']);
		$this->assertSame('baz', $attr['value']);

		$selector = $this->parse('foo[bar=baz][size=one]')->toArray();
		$attrs = $selector[0][0]->attributes;

		$this->assertSame('one', $attrs[1]['value']);
	}

	public function testAttributesNS() : void
	{
		$selector = $this->parse('[myns|foo=bar]')->toArray();

		/** @var array{ns:string, name:string} */
		$attr = $selector[0][0]->attributes[0];

		$this->assertSame('myns', $attr['ns']);
		$this->assertSame('foo', $attr['name']);
	}

	public function testPseudoClasses() : void
	{
		$selector = $this->parse('foo:first')->toArray();
		$pseudo = $selector[0][0]->pseudoClasses;

		$this->assertCount(1, $pseudo);

		$this->assertSame('first', $pseudo[0]['name']);
	}

	public function testPseudoElements() : void
	{
		$selector = $this->parse('foo::bar')->toArray();
		$pseudo = $selector[0][0]->pseudoElements;

		$this->assertCount(1, $pseudo);

		$this->assertSame('bar', $pseudo[0]);
	}

	public function testCombinators() : void
	{
		// This implies *>foo
		$selector = $this->parse('>foo')->toArray();

		$this->assertSame(SimpleSelector::DIRECT_DESCENDANT, $selector[0][1]->combinator);

		// This will be a selector with three samples:
		// 'bar'
		// 'foo '
		// '*>'
		$selector = $this->parse('>foo bar')->toArray();
		$this->assertNull($selector[0][0]->combinator);
		$this->assertSame(SimpleSelector::ANY_DESCENDANT, $selector[0][1]->combinator);
		$this->assertSame(SimpleSelector::DIRECT_DESCENDANT, $selector[0][2]->combinator);
	}

	public function testIterator() : void
	{
		$selector = $this->parse('foo::bar');

		$iterator = $selector->getIterator();
		$this->assertInstanceOf(Iterator::class, $iterator);
	}

	protected function parse(string $selector) : Selector
	{
		$handler = new \QueryPath\CSS\Selector();
		$parser = new \QueryPath\CSS\Parser($selector, $handler);
		$parser->parse();

		return $handler;
	}
}
