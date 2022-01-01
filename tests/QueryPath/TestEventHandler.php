<?php

declare(strict_types=1);

namespace QueryPathTests;

use function count;
use QueryPath\CSS\EventHandler;

/**
 * Testing harness for the EventHandler.
 *
 * @ingroup querypath_tests
 * @group   CSS
 */
class TestEventHandler implements EventHandler
{
	/** @var list<TestEvent> */
	public array $stack = [];

	/** @var list<TestEvent> */
	public array $expect = [];

	public function __construct()
	{
	}

	/**
	 * @param list<TestEvent> $stack
	 */
	public function expectsSmth(array $stack) : void
	{
		$this->expect = $stack;
	}

	public function success() : bool
	{
		$maybe = count($this->expect) === count($this->stack);

		if ( ! $maybe) {
			return false;
		}

		foreach ($this->expect as $k => $value) {
			if ( ! $value->compare($this->stack[$k])) {
				return false;
			}
		}

		return true;
	}

	public function elementID($id) : void
	{
		$this->stack[] = new TestEvent(TestEvent::ELEMENT_ID, $id);
	}

	public function element($name) : void
	{
		$this->stack[] = new TestEvent(TestEvent::ELEMENT, $name);
	}

	public function elementNS($name, $namespace = null) : void
	{
		$this->stack[] = new TestEvent(TestEvent::ELEMENT_NS, $name, $namespace);
	}

	public function anyElement() : void
	{
		$this->stack[] = new TestEvent(TestEvent::ANY_ELEMENT);
	}

	public function anyElementInNS($ns) : void
	{
		$this->stack[] = new TestEvent(TestEvent::ANY_ELEMENT_IN_NS, $ns);
	}

	public function elementClass($name) : void
	{
		$this->stack[] = new TestEvent(TestEvent::ELEMENT_CLASS, $name);
	}

	public function attribute($name, $value = null, $operation = EventHandler::IS_EXACTLY) : void
	{
		$this->stack[] = new TestEvent(TestEvent::ATTRIBUTE, $name, $value, $operation);
	}

	public function attributeNS($name, $ns, $value = null, $operation = EventHandler::IS_EXACTLY) : void
	{
		$this->stack[] = new TestEvent(TestEvent::ATTRIBUTE_NS, $name, $ns, $value, $operation);
	}

	public function pseudoClass($name, $value = null) : void
	{
		$this->stack[] = new TestEvent(TestEvent::PSEUDO_CLASS, $name, $value);
	}

	public function pseudoElement($name) : void
	{
		$this->stack[] = new TestEvent(TestEvent::PSEUDO_ELEMENT, $name);
	}

	public function directDescendant() : void
	{
		$this->stack[] = new TestEvent(TestEvent::DIRECT_DESCENDANT);
	}

	public function anyDescendant() : void
	{
		$this->stack[] = new TestEvent(TestEvent::ANY_DESCENDANT);
	}

	public function adjacent() : void
	{
		$this->stack[] = new TestEvent(TestEvent::ADJACENT);
	}

	public function anotherSelector() : void
	{
		$this->stack[] = new TestEvent(TestEvent::ANOTHER_SELECTOR);
	}

	public function sibling() : void
	{
		$this->stack[] = new TestEvent(TestEvent::SIBLING);
	}
}
