<?php

declare(strict_types=1);

namespace QueryPathTests\CSS;

use Exception;
use QueryPath\CSS\EventHandler;
use QueryPath\CSS\Parser;
use QueryPathTests\TestCase;
use QueryPathTests\TestEvent;
use QueryPathTests\TestEventHandler;

/**
 * @ingroup querypath_tests
 * @group   CSS
 */
class ParserTest extends TestCase
{
	public function testElementNS() : void
	{
		$mock = $this->createMock(TestEventHandler::class);
		$mock->expects($this->once())
			->method('elementNS')
			->with($this->equalTo('mytest'), $this->equalTo('myns'))
		;

		$parser = new Parser('myns|mytest', $mock);
		$parser->parse();

		$mock = $this->createMock(TestEventHandler::class);
		$mock->expects($this->once())
			->method('elementNS')
			->with($this->equalTo('mytest'), $this->equalTo('*'))
		;

		$parser = new Parser('*|mytest', $mock);
		$parser->parse();

		$mock = $this->createMock(TestEventHandler::class);
		$mock->expects($this->once())
			->method('anyElementInNS')
			->with($this->equalTo('*'))
		;

		$parser = new Parser('*|*', $mock);
		$parser->parse();
	}

	public function testAnyElement() : void
	{
		$mock = $this->createMock(TestEventHandler::class);
		$mock->expects($this->once())
			->method('anyElement')
		;

		$parser = new Parser('*', $mock);
		$parser->parse();
	}

	public function testAnyElementInNS() : void
	{
		$mock = $this->createMock(TestEventHandler::class);
		$mock->expects($this->once())
			->method('anyElementInNS')
			->with($this->equalTo('myns'))
		;

		$parser = new Parser('myns|*', $mock);
		$parser->parse();
	}

	public function testElementClass() : void
	{
		$mock = $this->createMock(TestEventHandler::class);
		$mock->expects($this->once())
			->method('elementClass')
			->with($this->equalTo('myclass'))
		;

		$parser = new Parser('.myclass', $mock);
		$parser->parse();
	}

	public function testPseudoClass() : void
	{
		// Test empty pseudoclass
		$mock = $this->createMock(TestEventHandler::class);
		$mock->expects($this->once())
			->method('pseudoClass')
			->with($this->equalTo('mypclass'))
		;

		$parser = new Parser('myele:mypclass', $mock);
		$parser->parse();

		// Test pseudoclass with value
		$mock = $this->createMock(TestEventHandler::class);
		$mock->expects($this->once())
			->method('pseudoClass')
			->with($this->equalTo('mypclass'), $this->equalTo('myval'))
		;

		$parser = new Parser('myele:mypclass(myval)', $mock);
		$parser->parse();

		// Test pseudclass with pseudoclass:
		$mock = $this->createMock(TestEventHandler::class);
		$mock->expects($this->once())
			->method('pseudoClass')
			->with($this->equalTo('mypclass'), $this->equalTo(':anotherPseudo'))
		;

		$parser = new Parser('myele:mypclass(:anotherPseudo)', $mock);
		$parser->parse();
	}

	public function testPseudoElement() : void
	{
		// Test pseudo-element
		$mock = $this->createMock(TestEventHandler::class);
		$mock->expects($this->once())
			->method('pseudoElement')
			->with($this->equalTo('mypele'))
		;

		$parser = new Parser('myele::mypele', $mock);
		$parser->parse();
	}

	public function testDirectDescendant() : void
	{
		// Test direct Descendant
		$mock = $this->createMock(TestEventHandler::class);
		$mock->expects($this->once())
			->method('directDescendant')
		;

		$parser = new Parser('ele1 > ele2', $mock);
		$parser->parse();
	}

	public function testAnyDescendant() : void
	{
		// Test direct Descendant
		$mock = $this->createMock(TestEventHandler::class);
		$mock->expects($this->once())
			->method('anyDescendant')
		;

		$parser = new Parser('ele1  .class', $mock);
		$parser->parse();
	}

	public function testAdjacent() : void
	{
		// Test sibling
		$mock = $this->createMock(TestEventHandler::class);
		$mock->expects($this->once())
			->method('adjacent')
		;

		$parser = new Parser('ele1 + ele2', $mock);
		$parser->parse();
	}

	public function testSibling() : void
	{
		// Test adjacent
		$mock = $this->createMock(TestEventHandler::class);
		$mock->expects($this->once())
			->method('sibling')
		;

		$parser = new Parser('ele1 ~ ele2', $mock);
		$parser->parse();
	}

	public function testAnotherSelector() : void
	{
		// Test adjacent
		$mock = $this->createMock(TestEventHandler::class);
		$mock->expects($this->once())
			->method('anotherSelector')
		;

		$parser = new Parser('ele1 , ele2', $mock);
		$parser->parse();
	}

	public function testIllegalAttribute() : void
	{
		// Note that this is designed to test throwError() as well as
		// bad selector handling.

		$this->expectException(\QueryPath\CSS\ParseException::class);
		$parser = new Parser('[test=~far]', new TestEventHandler());
		try {
			$parser->parse();
		} catch (Exception $e) {
			//print $e->getMessage();
			throw $e;
		}
	}

	public function testAttribute() : void
	{
		$selectors = [
			'element[attr]' => 'attr',
			'*[attr]' => 'attr',
			'element[attr]:class' => 'attr',
			'element[attr2]' => 'attr2', // Issue #
		];
		foreach ($selectors as $filter => $expected) {
			$mock = $this->createMock(TestEventHandler::class);
			$mock->expects($this->once())
				->method('attribute')
				->with($this->equalTo($expected))
			;

			$parser = new Parser($filter, $mock);
			$parser->parse();
		}

		$selectors = [
			'*[attr="value"]' => ['attr', 'value', EventHandler::IS_EXACTLY],
			'*[attr^="value"]' => ['attr', 'value', EventHandler::BEGINS_WITH],
			'*[attr$="value"]' => ['attr', 'value', EventHandler::ENDS_WITH],
			'*[attr*="value"]' => ['attr', 'value', EventHandler::CONTAINS_IN_STRING],
			'*[attr~="value"]' => ['attr', 'value', EventHandler::CONTAINS_WITH_SPACE],
			'*[attr|="value"]' => ['attr', 'value', EventHandler::CONTAINS_WITH_HYPHEN],

			// This should act like [attr="value"]
			'*[|attr="value"]' => ['attr', 'value', EventHandler::IS_EXACTLY],

			// This behavior is displayed in the spec, but not accounted for in the
			// grammar:
			'*[attr=value]' => ['attr', 'value', EventHandler::IS_EXACTLY],

			// Should be able to escape chars using backslash.
			'*[attr="\.value"]' => ['attr', '.value', EventHandler::IS_EXACTLY],
			'*[attr="\.\]\]\]"]' => ['attr', '.]]]', EventHandler::IS_EXACTLY],

			// Backslash-backslash should resolve to single backslash.
			'*[attr="\\\c"]' => ['attr', '\\c', EventHandler::IS_EXACTLY],

			// Should return an empty value. It seems, though, that a value should be
			// passed here.
			'*[attr=""]' => ['attr', '', EventHandler::IS_EXACTLY],
		];
		foreach ($selectors as $filter => $expected) {
			$mock = $this->createMock(TestEventHandler::class);
			$mock->expects($this->once())
				->method('attribute')
				->with($this->equalTo($expected[0]), $this->equalTo($expected[1]), $this->equalTo($expected[2]))
			;

			$parser = new Parser($filter, $mock);
			$parser->parse();
		}
	}

	public function testAttributeNS() : void
	{
		$selectors = [
			'*[ns|attr="value"]' => ['attr', 'ns', 'value', EventHandler::IS_EXACTLY],
			'*[*|attr^="value"]' => ['attr', '*', 'value', EventHandler::BEGINS_WITH],
			'*[*|attr|="value"]' => ['attr', '*', 'value', EventHandler::CONTAINS_WITH_HYPHEN],
		];

		foreach ($selectors as $filter => $expected) {
			$mock = $this->createMock(TestEventHandler::class);
			$mock->expects($this->once())
				->method('attributeNS')
				->with($this->equalTo($expected[0]), $this->equalTo($expected[1]), $this->equalTo($expected[2]),
					$this->equalTo($expected[3]))
			;

			$parser = new Parser($filter, $mock);
			$parser->parse();
		}
	}

	// Test things that should break...

	public function testIllegalCombinators1() : void
	{
		$handler = new TestEventHandler();
		$parser = new Parser('ele1 > > ele2', $handler);

		$this->expectException(\QueryPath\CSS\ParseException::class);
		$parser->parse();
	}

	public function testIllegalCombinators2() : void
	{
		$handler = new TestEventHandler();
		$parser = new Parser('ele1+ ,ele2', $handler);

		$this->expectException(\QueryPath\CSS\ParseException::class);
		$parser->parse();
	}

	public function testIllegalID() : void
	{
		$handler = new TestEventHandler();
		$parser = new Parser('##ID', $handler);

		$this->expectException(\QueryPath\CSS\ParseException::class);
		$parser->parse();
	}

	// Test combinations

	public function testElementNSClassAndAttribute() : void
	{
		$expect = [
			new TestEvent(TestEvent::ELEMENT_NS, 'element', 'ns'),
			new TestEvent(TestEvent::ELEMENT_CLASS, 'class'),
			new TestEvent(TestEvent::ATTRIBUTE, 'name', 'value', EventHandler::IS_EXACTLY),
		];
		$selector = 'ns|element.class[name="value"]';

		$handler = new TestEventHandler();
		$handler->expectsSmth($expect);
		$parser = new Parser($selector, $handler);
		$parser->parse();
		$this->assertTrue($handler->success());

		// Again, with spaces this time:
		$selector = ' ns|element. class[  name = "value" ]';

		$handler = new TestEventHandler();
		$handler->expectsSmth($expect);
		$parser = new Parser($selector, $handler);
		$parser->parse();

		$this->assertTrue($handler->success());
	}

	public function testAllCombo() : void
	{
		$selector = '*|ele1 > ele2.class1 + ns1|ele3.class2[attr=simple] ~
     .class2[attr2~="longer string of text."]:pseudoClass(value)
     .class3::pseudoElement';
		$expect = [
			new TestEvent(TestEvent::ELEMENT_NS, 'ele1', '*'),
			new TestEvent(TestEvent::DIRECT_DESCENDANT),
			new TestEvent(TestEvent::ELEMENT, 'ele2'),
			new TestEvent(TestEvent::ELEMENT_CLASS, 'class1'),
			new TestEvent(TestEvent::ADJACENT),
			new TestEvent(TestEvent::ELEMENT_NS, 'ele3', 'ns1'),
			new TestEvent(TestEvent::ELEMENT_CLASS, 'class2'),
			new TestEvent(TestEvent::ATTRIBUTE, 'attr', 'simple', EventHandler::IS_EXACTLY),
			new TestEvent(TestEvent::SIBLING),
			new TestEvent(TestEvent::ELEMENT_CLASS, 'class2'),
			new TestEvent(TestEvent::ATTRIBUTE, 'attr2', 'longer string of text.', EventHandler::CONTAINS_WITH_SPACE),
			new TestEvent(TestEvent::PSEUDO_CLASS, 'pseudoClass', 'value'),
			new TestEvent(TestEvent::ANY_DESCENDANT),
			new TestEvent(TestEvent::ELEMENT_CLASS, 'class3'),
			new TestEvent(TestEvent::PSEUDO_ELEMENT, 'pseudoElement'),
		];

		$handler = new TestEventHandler();
		$handler->expectsSmth($expect);
		$parser = new Parser($selector, $handler);
		$parser->parse();

		$this->assertTrue($handler->success());
	}
}
