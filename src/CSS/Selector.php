<?php

declare(strict_types=1);
/** @file
 * A selector.
 */

namespace QueryPath\CSS;

use ArrayIterator;
use function count;
use Countable;
use IteratorAggregate;

/**
 * A CSS Selector.
 *
 * A CSS selector is made up of one or more Simple Selectors
 * (SimpleSelector).
 *
 * @attention
 * The Selector data structure is a LIFO (Last in, First out). This is
 * because CSS selectors are best processed "bottom up". Thus, when
 * iterating over 'a>b>c', the iterator will produce:
 * - c
 * - b
 * - a
 * It is assumed, therefore, that any suitable querying engine will
 * traverse from the bottom (`c`) back up.
 *
 * @b     Usage
 *
 * This class is an event handler. It can be plugged into an Parser and
 * receive the events the Parser generates.
 *
 * This class is also an iterator. Once the parser has completed, the
 * captured selectors can be iterated over.
 *
 * @code
 * <?php
 * $selectorList = new \QueryPath\CSS\Selector();
 * $parser = new \QueryPath\CSS\Parser($selector, $selectorList);
 *
 * $parser->parse();
 *
 * foreach ($selectorList as $simpleSelector) {
 *   // Do something with the SimpleSelector.
 *   print_r($simpleSelector);
 * }
 * ?>
 * @endode
 *
 * @since QueryPath 3.0.0
 *
 * @template-implements IteratorAggregate<int, array<int, SimpleSelector>>
 */
class Selector implements EventHandler, IteratorAggregate, Countable
{
	/** @var array<int, array<int, SimpleSelector>> */
	protected array $selectors = [];
	protected SimpleSelector $currSelector;
	protected int $groupIndex = 0;

	public function __construct()
	{
		$this->currSelector = new SimpleSelector();

		if ( ! isset($this->selectors[$this->groupIndex])) {
			/** @var array<int, SimpleSelector> */
			$this->selectors[$this->groupIndex] = [];
		}

		$this->selectors[$this->groupIndex][] = $this->currSelector;
	}

	/**
	 * @return ArrayIterator<int, array<int, SimpleSelector>>
	 */
	public function getIterator() : ArrayIterator
	{
		return new ArrayIterator($this->selectors);
	}

	/**
	 * Get the array of SimpleSelector objects.
	 *
	 * Normally, one iterates over a Selector. However, if it is
	 * necessary to get the selector array and manipulate it, this
	 * method can be used.
	 *
	 * @return array<int, array<int, SimpleSelector>>
	 */
	public function toArray() : array
	{
		return $this->selectors;
	}

	public function count() : int
	{
		return count($this->selectors);
	}

	public function elementID($id) : void
	{
		$this->currSelector->id = $id;
	}

	public function element($name) : void
	{
		$this->currSelector->element = $name;
	}

	public function elementNS($name, $namespace = null) : void
	{
		$this->currSelector->ns = $namespace;
		$this->currSelector->element = $name;
	}

	public function anyElement() : void
	{
		$this->currSelector->element = '*';
	}

	public function anyElementInNS($ns) : void
	{
		$this->currSelector->ns = $ns;
		$this->currSelector->element = '*';
	}

	public function elementClass($name) : void
	{
		$this->currSelector->classes[] = $name;
	}

	public function attribute(string $name, string $value = null, ?int $operation = EventHandler::IS_EXACTLY) : void
	{
		$this->currSelector->attributes[] = [
			'name' => $name,
			'value' => $value,
			'op' => $operation,
		];
	}

	public function attributeNS(string $name, string $ns, string $value = null, ?int $operation = EventHandler::IS_EXACTLY) : void
	{
		$this->currSelector->attributes[] = [
			'name' => $name,
			'value' => $value,
			'op' => $operation,
			'ns' => $ns,
		];
	}

	public function pseudoClass($name, $value = null) : void
	{
		$this->currSelector->pseudoClasses[] = ['name' => $name, 'value' => $value];
	}

	public function pseudoElement($name) : void
	{
		$this->currSelector->pseudoElements[] = $name;
	}

	public function combinator(?int $combinatorName) : void
	{
		$this->currSelector->combinator = $combinatorName;
		$this->currSelector = new SimpleSelector();
		array_unshift($this->selectors[$this->groupIndex], $this->currSelector);
	}

	public function directDescendant() : void
	{
		$this->combinator(SimpleSelector::DIRECT_DESCENDANT);
	}

	public function adjacent() : void
	{
		$this->combinator(SimpleSelector::ADJACENT);
	}

	public function anotherSelector() : void
	{
		++$this->groupIndex;
		$this->currSelector = new SimpleSelector();
		$this->selectors[$this->groupIndex] = [$this->currSelector];
	}

	public function sibling() : void
	{
		$this->combinator(SimpleSelector::SIBLING);
	}

	public function anyDescendant() : void
	{
		$this->combinator(SimpleSelector::ANY_DESCENDANT);
	}
}
