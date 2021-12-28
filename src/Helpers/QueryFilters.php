<?php

declare(strict_types=1);

namespace QueryPath\Helpers;

use function call_user_func;
use function count;
use function create_function;
use DOMElement;
use DOMNode;
use function in_array;
use function is_array;
use function is_callable;
use function is_object;
use QueryPath\CSS\DOMTraverser;
use QueryPath\CSS\ParseException;
use QueryPath\Exception;
use QueryPath\Query;
use QueryPath\QueryPath;
use QueryPath\TextContent;
use SplObjectStorage;
use function strlen;
use UnexpectedValueException;
use const XML_DOCUMENT_NODE;
use const XML_ELEMENT_NODE;

/**
 * Trait QueryFilters.
 *
 * @property array matches
 */
trait QueryFilters
{
	/**
	 * @return SplObjectStorage<DOMNode|TextContent, mixed>
	 */
	abstract public function getMatches() : SplObjectStorage;

	/**
	 * Filter a list down to only elements that match the selector.
	 * Use this, for example, to find all elements with a class, or with
	 * certain children.
	 *
	 * @param string $selector
	 *   The selector to use as a filter
	 *
	 * @throws ParseException
	 *
	 * @return Query The DOMQuery with non-matching items filtered out.*   The DOMQuery with non-matching items
	 *               filtered out.
	 *
	 * @see filterLambda()
	 * @see filterCallback()
	 * @see map()
	 * @see find()
	 * @see is()
	 */
	public function filter($selector) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();

		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$tmp = new SplObjectStorage();

		foreach ($this->getMatches() as $m) {
			$tmp->attach($m);
			// Seems like this should be right... but it fails unit
			// tests. Need to compare to jQuery.
			// $query = new \QueryPath\CSS\DOMTraverser($tmp, TRUE, $m);
			$query = new DOMTraverser($tmp);
			$query->find($selector);
			if (count($query->matches())) {
				$found->attach($m);
			}
			$tmp->detach($m);
		}

		return $this->inst($found, null);
	}

	/**
	 * Filter based on a lambda function.
	 *
	 * The function string will be executed as if it were the body of a
	 * function. It is passed two arguments:
	 * - $index: The index of the item.
	 * - $item: The current Element.
	 * If the function returns boolean FALSE, the item will be removed from
	 * the list of elements. Otherwise it will be kept.
	 *
	 * Example:
	 *
	 * @code
	 * qp('li')->filterLambda('qp($item)->attr("id") == "test"');
	 * @endcode
	 *
	 * The above would filter down the list to only an item whose ID is
	 * 'text'.
	 *
	 * @param string $fn
	 *  Inline lambda function in a string
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *
	 * @see filter()
	 * @see map()
	 * @see mapLambda()
	 * @see filterCallback()
	 */
	public function filterLambda($fn) : Query
	{
		/** @var callable(int, DOMNode|TextContent) */
		$function = create_function('$index, $item', $fn);
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		$i = 0;
		foreach ($this->getMatches() as $item) {
			if (false !== $function($i++, $item)) {
				$found->attach($item);
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Use regular expressions to filter based on the text content of matched elements.
	 *
	 * Only items that match the given regular expression will be kept. All others will
	 * be removed.
	 *
	 * The regular expression is run against the <i>text content</i> (the PCDATA) of the
	 * elements. This is a way of filtering elements based on their content.
	 *
	 * Example:
	 *
	 * @code
	 *  <?xml version="1.0"?>
	 *  <div>Hello <i>World</i></div>
	 * @endcode
	 *
	 * @code
	 *  <?php
	 *    // This will be 1.
	 *    qp($xml, 'div')->filterPreg('/World/')->matches->count();
	 *  ?>
	 * @endcode
	 *
	 * The return value above will be 1 because the text content of @codeqp($xml, 'div')@endcode is
	 * @codeHello World@endcode.
	 *
	 * Compare this to the behavior of the <em>:contains()</em> CSS3 pseudo-class.
	 *
	 * @param string $regex
	 *  A regular expression
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *
	 * @see       filter()
	 * @see       filterCallback()
	 * @see       preg_match()
	 */
	public function filterPreg($regex) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();

		foreach ($this->getMatches() as $item) {
			if (preg_match($regex, $item->textContent) > 0) {
				$found->attach($item);
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Filter based on a callback function.
	 *
	 * A callback may be any of the following:
	 *  - a function: 'my_func'.
	 *  - an object/method combo: $obj, 'myMethod'
	 *  - a class/method combo: 'MyClass', 'myMethod'
	 * Note that classes are passed in strings. Objects are not.
	 *
	 * Each callback is passed to arguments:
	 *  - $index: The index position of the object in the array.
	 *  - $item: The item to be operated upon.
	 *
	 * If the callback function returns FALSE, the item will be removed from the
	 * set of matches. Otherwise the item will be considered a match and left alone.
	 *
	 * @param callable $callback .
	 *                           A callback either as a string (function) or an array (object, method OR
	 *                           classname, method).
	 *
	 * @throws ParseException
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *                           Query path object augmented according to the function
	 *
	 * @see filter()
	 * @see filterLambda()
	 * @see map()
	 * @see is()
	 * @see find()
	 */
	public function filterCallback($callback) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		$i = 0;
		if (is_callable($callback)) {
			foreach ($this->getMatches() as $item) {
				if (false !== $callback($i++, $item)) {
					$found->attach($item);
				}
			}
		} else {
			throw new Exception('The specified callback is not callable.');
		}

		return $this->inst($found, null);
	}

	/**
	 * Run a function on each item in a set.
	 *
	 * The mapping callback can return anything. Whatever it returns will be
	 * stored as a match in the set, though. This means that afer a map call,
	 * there is no guarantee that the elements in the set will behave correctly
	 * with other DOMQuery functions.
	 *
	 * Callback rules:
	 * - If the callback returns NULL, the item will be removed from the array.
	 * - If the callback returns an array, the entire array will be stored in
	 *   the results.
	 * - If the callback returns anything else, it will be appended to the array
	 *   of matches.
	 *
	 * @param (callable(int, DOMNode|TextContent):(iterable<DOMNode|TextContent|scalar>|DOMNode|TextContent|scalar|null)) $callback
	 *  The function or callback to use. The callback will be passed two params:
	 *  - $index: The index position in the list of items wrapped by this object.
	 *  - $item: The current item.
	 *
	 * @throws Exception
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery object wrapping a list of whatever values were returned
	 *  by each run of the callback
	 *
	 * @see DOMQuery::get()
	 * @see filter()
	 * @see find()
	 */
	public function map($callback) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();

		if (is_callable($callback)) {
			$i = 0;
			foreach ($this->getMatches() as $item) {
				$c = call_user_func($callback, $i, $item);
				if (isset($c)) {
					if (is_iterable($c)) {
						foreach ($c as $retval) {
							if ( ! is_object($retval)) {
								$retval = new TextContent((string) $retval);
							}
							$found->attach($retval);
						}
					} else {
						if ( ! is_object($c)) {
							$c = new TextContent((string) $c);
						}
						$found->attach($c);
					}
				}
				++$i;
			}
		} else {
			throw new Exception('Callback is not callable.');
		}

		return $this->inst($found, null);
	}

	/**
	 * Narrow the items in this object down to only a slice of the starting items.
	 *
	 * @param int $start
	 *  Where in the list of matches to begin the slice
	 * @param int $length
	 *  The number of items to include in the slice. If nothing is specified, the
	 *  all remaining matches (from $start onward) will be included in the sliced
	 *  list.
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *
	 * @see array_slice()
	 */
	public function slice($start, $length = 0) : Query
	{
		$end = $length;
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		if ($start >= $this->count()) {
			return $this->inst($found, null);
		}

		$i = $j = 0;
		foreach ($this->getMatches() as $m) {
			if ($i >= $start) {
				if ($end > 0 && $j >= $end) {
					break;
				}
				$found->attach($m);
				++$j;
			}
			++$i;
		}

		return $this->inst($found, null);
	}

	/**
	 * Run a callback on each item in the list of items.
	 *
	 * Rules of the callback:
	 * - A callback is passed two variables: $index and $item. (There is no
	 *   special treatment of $this, as there is in jQuery.)
	 *   - You will want to pass $item by reference if it is not an
	 *     object (DOMNodes are all objects).
	 * - A callback that returns FALSE will stop execution of the each() loop. This
	 *   works like break in a standard loop.
	 * - A TRUE return value from the callback is analogous to a continue statement.
	 * - All other return values are ignored.
	 *
	 * @param callable(int, DOMNode|TextContent) $callback
	 *  The callback to run
	 *
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery
	 *
	 * @see filter()
	 * @see map()
	 */
	public function each($callback) : Query
	{
		if (is_callable($callback)) {
			$i = 0;
			foreach ($this->getMatches() as $item) {
				if (false === call_user_func($callback, $i, $item)) {
					return $this;
				}
				++$i;
			}
		} else {
			throw new \QueryPath\Exception('Callback is not callable.');
		}

		return $this;
	}

	/**
	 * Get the even elements, so counter-intuitively 1, 3, 5, etc.
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  A DOMQuery wrapping all of the children
	 *
	 * @see    removeChildren()
	 * @see    parent()
	 * @see    parents()
	 * @see    next()
	 * @see    prev()
	 * @since  2.1
	 *
	 * @author eabrand
	 */
	public function even() : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		$even = false;
		foreach ($this->getMatches() as $m) {
			if ( ! ($m instanceof DOMNode)) {
				continue;
			}
			if ($even && XML_ELEMENT_NODE === $m->nodeType) {
				$found->attach($m);
			}
			$even = $even ? false : true;
		}

		return $this->inst($found, null);
	}

	/**
	 * Get the odd elements, so counter-intuitively 0, 2, 4, etc.
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  A DOMQuery wrapping all of the children
	 *
	 * @see    removeChildren()
	 * @see    parent()
	 * @see    parents()
	 * @see    next()
	 * @see    prev()
	 * @since  2.1
	 *
	 * @author eabrand
	 */
	public function odd() : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		$odd = true;
		foreach ($this->getMatches() as $m) {
			if ( ! ($m instanceof DOMNode)) {
				continue;
			}
			if ($odd && XML_ELEMENT_NODE === $m->nodeType) {
				$found->attach($m);
			}
			$odd = $odd ? false : true;
		}

		return $this->inst($found, null);
	}

	/**
	 * Get the first matching element.
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  A DOMQuery wrapping all of the children
	 *
	 * @see    next()
	 * @see    prev()
	 * @since  2.1
	 *
	 * @author eabrand
	 */
	public function first() : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			if (XML_ELEMENT_NODE === $m->nodeType) {
				$found->attach($m);
				break;
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get the first child of the matching element.
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  A DOMQuery wrapping all of the children
	 *
	 * @see    next()
	 * @see    prev()
	 * @since  2.1
	 *
	 * @author eabrand
	 */
	public function firstChild() : Query
	{
		// Could possibly use $m->firstChild http://theserverpages.com/php/manual/en/ref.dom.php
		$found = new SplObjectStorage();
		$flag = false;
		foreach ($this->getMatches() as $m) {
			foreach ($m->childNodes as $c) {
				if (XML_ELEMENT_NODE === $c->nodeType) {
					$found->attach($c);
					$flag = true;
					break;
				}
			}
			if ($flag) {
				break;
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get the last matching element.
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  A DOMQuery wrapping all of the children
	 *
	 * @see    next()
	 * @see    prev()
	 * @since  2.1
	 *
	 * @author eabrand
	 */
	public function last() : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		$item = null;
		foreach ($this->getMatches() as $m) {
			if (XML_ELEMENT_NODE === $m->nodeType) {
				$item = $m;
			}
		}
		if ($item) {
			$found->attach($item);
		}

		return $this->inst($found, null);
	}

	/**
	 * Get the last child of the matching element.
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  A DOMQuery wrapping all of the children
	 *
	 * @see    next()
	 * @see    prev()
	 * @since  2.1
	 *
	 * @author eabrand
	 */
	public function lastChild() : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		$item = null;
		foreach ($this->getMatches() as $m) {
			foreach ($m->childNodes as $c) {
				if (XML_ELEMENT_NODE === $c->nodeType) {
					$item = $c;
				}
			}
			if ($item) {
				$found->attach($item);
				$item = null;
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get all siblings after an element until the selector is reached.
	 *
	 * For each element in the DOMQuery, get all siblings that appear after
	 * it. If a selector is passed in, then only siblings that match the
	 * selector will be included.
	 *
	 * @param string $selector
	 *  A valid CSS 3 selector
	 *
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery object, now containing the matching siblings
	 *
	 * @see    next()
	 * @see    prevAll()
	 * @see    children()
	 * @see    siblings()
	 * @since  2.1
	 *
	 * @author eabrand
	 */
	public function nextUntil($selector = null) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			while (isset($m->nextSibling)) {
				$m = $m->nextSibling;
				if (XML_ELEMENT_NODE === $m->nodeType) {
					if (null !== $selector && QueryPath::with($m, null, $this->options)->is($selector) > 0) {
						break;
					}
					$found->attach($m);
				}
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get the previous siblings for each element in the DOMQuery
	 * until the selector is reached.
	 *
	 * For each element in the DOMQuery, get all previous siblings. If a
	 * selector is provided, only matching siblings will be retrieved.
	 *
	 * @param string $selector
	 *  A valid CSS 3 selector
	 *
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery object, now wrapping previous sibling elements
	 *
	 * @see    prev()
	 * @see    nextAll()
	 * @see    siblings()
	 * @see    contents()
	 * @see    children()
	 * @since  2.1
	 *
	 * @author eabrand
	 */
	public function prevUntil($selector = null) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			while (isset($m->previousSibling)) {
				$m = $m->previousSibling;
				if (XML_ELEMENT_NODE === $m->nodeType) {
					if (null !== $selector && QueryPath::with($m, null, $this->options)->is($selector)) {
						break;
					}

					$found->attach($m);
				}
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get all ancestors of each element in the DOMQuery until the selector is reached.
	 *
	 * If a selector is present, only matching ancestors will be retrieved.
	 *
	 * @see    parent()
	 *
	 * @param string $selector
	 *  A valid CSS 3 Selector
	 *
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  A DOMNode object containing the matching ancestors
	 *
	 * @see    siblings()
	 * @see    children()
	 * @since  2.1
	 *
	 * @author eabrand
	 */
	public function parentsUntil($selector = null) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			if ( ! ($m instanceof DOMNode)) {
				continue;
			}
			assert(
				($m->parentNode instanceof DOMNode),
				new UnexpectedValueException('parentNode not found!')
			);
			while (
				(($parentNode = ($m->parentNode ?? null)) instanceof DOMNode)
				&& XML_DOCUMENT_NODE !== $parentNode->nodeType
			) {
				$m = $parentNode;
				// Is there any case where parent node is not an element?
				if (XML_ELEMENT_NODE === $m->nodeType) {
					if ( ! empty($selector)) {
						if (QueryPath::with($m, null, $this->options)->is($selector) > 0) {
							break;
						}
						$found->attach($m);
					} else {
						$found->attach($m);
					}
				}
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Reduce the matched set to just one.
	 *
	 * This will take a matched set and reduce it to just one item -- the item
	 * at the index specified. This is a destructive operation, and can be undone
	 * with {@link end()}.
	 *
	 * @param $index
	 *  The index of the element to keep. The rest will be
	 *  discarded.
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *
	 * @see get()
	 * @see is()
	 * @see end()
	 */
	public function eq($index) : Query
	{
		return $this->inst($this->getNthMatch($index), null);
	}

	/**
	 * Filter a list to contain only items that do NOT match.
	 *
	 * @param string|SplObjectStorage<DOMNode|TextContent, mixed>|list<DOMNode>|DOMElement $selector
	 *  A selector to use as a negation filter. If the filter is matched, the
	 *  element will be removed from the list.
	 *
	 * @throws ParseException
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery object with matching items filtered out
	 *
	 * @see find()
	 */
	public function not(SplObjectStorage|DOMElement|string|array $selector) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, null> */
		$found = new SplObjectStorage();
		if ($selector instanceof DOMElement) {
			foreach ($this->getMatches() as $m) {
				if ($m !== $selector) {
					$found->attach($m);
				}
			}
		} elseif (is_array($selector)) {
			foreach ($this->getMatches() as $m) {
				if ( ! in_array($m, $selector, true)) {
					$found->attach($m);
				}
			}
		} elseif ($selector instanceof SplObjectStorage) {
			foreach ($this->getMatches() as $m) {
				if ($selector->contains($m)) {
					$found->attach($m);
				}
			}
		} else {
			foreach ($this->getMatches() as $m) {
				if ( ! ($m instanceof DOMNode)) {
					continue;
				}
				if ( ! QueryPath::with($m, null, $this->options)->is($selector)) {
					$found->attach($m);
				}
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Find the closest element matching the selector.
	 *
	 * This finds the closest match in the ancestry chain. It first checks the
	 * present element. If the present element does not match, this traverses up
	 * the ancestry chain (e.g. checks each parent) looking for an item that matches.
	 *
	 * It is provided for jQuery 1.3 compatibility.
	 *
	 * @param string $selector
	 *  A CSS Selector to match
	 *
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  The set of matches
	 *
	 * @since 2.0
	 */
	public function closest($selector) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			if ( ! ($m instanceof DOMNode)) {
				continue;
			}
			if (QueryPath::with($m, null, $this->options)->is($selector) > 0) {
				$found->attach($m);
			} else {
				while (
					(($parentNode = ($m->parentNode ?? null)) instanceof DOMNode)
					&& (XML_DOCUMENT_NODE !== $parentNode->nodeType)
				) {
					$m = $parentNode;
					// Is there any case where parent node is not an element?
					if (XML_ELEMENT_NODE === $m->nodeType && QueryPath::with($m, null,
							$this->options)->is($selector) > 0) {
						$found->attach($m);
						break;
					}
				}
			}
		}

		// XXX: Should this be an in-place modification?
		return $this->inst($found, null);
	}

	/**
	 * Get the immediate parent of each element in the DOMQuery.
	 *
	 * If a selector is passed, this will return the nearest matching parent for
	 * each element in the DOMQuery.
	 *
	 * @param string $selector
	 *  A valid CSS3 selector
	 *
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  A DOMNode object wrapping the matching parents
	 *
	 * @see children()
	 * @see siblings()
	 * @see parents()
	 */
	public function parent($selector = null) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			while (
				(($parentNode = ($m->parentNode ?? null)) instanceof DOMNode)
				&& (XML_DOCUMENT_NODE !== $parentNode->nodeType)
			) {
				$m = $parentNode;
				// Is there any case where parent node is not an element?
				if (XML_ELEMENT_NODE === $m->nodeType) {
					if ( ! empty($selector)) {
						if (QueryPath::with($m, null, $this->options)->is($selector) > 0) {
							$found->attach($m);
							break;
						}
					} else {
						$found->attach($m);
						break;
					}
				}
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get all ancestors of each element in the DOMQuery.
	 *
	 * If a selector is present, only matching ancestors will be retrieved.
	 *
	 * @see parent()
	 *
	 * @param string $selector
	 *  A valid CSS 3 Selector
	 *
	 * @throws ParseException
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  A DOMNode object containing the matching ancestors
	 *
	 * @see siblings()
	 * @see children()
	 */
	public function parents($selector = null) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			while (
				(($parentNode = ($m->parentNode ?? null)) instanceof DOMNode)
				&& (XML_DOCUMENT_NODE !== $parentNode->nodeType)
			) {
				$m = $parentNode;
				// Is there any case where parent node is not an element?
				if (XML_ELEMENT_NODE === $m->nodeType) {
					if ( ! empty($selector)) {
						if (QueryPath::with($m, null, $this->options)->is($selector) > 0) {
							$found->attach($m);
						}
					} else {
						$found->attach($m);
					}
				}
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get the next sibling of each element in the DOMQuery.
	 *
	 * If a selector is provided, the next matching sibling will be returned.
	 *
	 * @param string $selector
	 *  A CSS3 selector
	 *
	 * @throws Exception
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery object
	 *
	 * @see nextAll()
	 * @see prev()
	 * @see children()
	 * @see contents()
	 * @see parent()
	 * @see parents()
	 */
	public function next($selector = null) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			while (isset($m->nextSibling)) {
				$m = $m->nextSibling;
				if (XML_ELEMENT_NODE === $m->nodeType) {
					if ( ! empty($selector)) {
						if (QueryPath::with($m, null, $this->options)->is($selector) > 0) {
							$found->attach($m);
							break;
						}
					} else {
						$found->attach($m);
						break;
					}
				}
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get all siblings after an element.
	 *
	 * For each element in the DOMQuery, get all siblings that appear after
	 * it. If a selector is passed in, then only siblings that match the
	 * selector will be included.
	 *
	 * @param string $selector
	 *  A valid CSS 3 selector
	 *
	 * @throws Exception
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery object, now containing the matching siblings
	 *
	 * @see next()
	 * @see prevAll()
	 * @see children()
	 * @see siblings()
	 */
	public function nextAll($selector = null) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			while (isset($m->nextSibling)) {
				$m = $m->nextSibling;
				if (XML_ELEMENT_NODE === $m->nodeType) {
					if ( ! empty($selector)) {
						if (QueryPath::with($m, null, $this->options)->is($selector) > 0) {
							$found->attach($m);
						}
					} else {
						$found->attach($m);
					}
				}
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get the next sibling before each element in the DOMQuery.
	 *
	 * For each element in the DOMQuery, this retrieves the previous sibling
	 * (if any). If a selector is supplied, it retrieves the first matching
	 * sibling (if any is found).
	 *
	 * @param string $selector
	 *  A valid CSS 3 selector
	 *
	 * @throws Exception
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  A DOMNode object, now containing any previous siblings that have been
	 *  found
	 *
	 * @see prevAll()
	 * @see next()
	 * @see siblings()
	 * @see children()
	 */
	public function prev($selector = null) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			while (isset($m->previousSibling)) {
				$m = $m->previousSibling;
				if (XML_ELEMENT_NODE === $m->nodeType) {
					if ( ! empty($selector)) {
						if (QueryPath::with($m, null, $this->options)->is($selector)) {
							$found->attach($m);
							break;
						}
					} else {
						$found->attach($m);
						break;
					}
				}
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get the previous siblings for each element in the DOMQuery.
	 *
	 * For each element in the DOMQuery, get all previous siblings. If a
	 * selector is provided, only matching siblings will be retrieved.
	 *
	 * @param string $selector
	 *  A valid CSS 3 selector
	 *
	 * @throws ParseException
	 * @throws \QueryPath\Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery object, now wrapping previous sibling elements
	 *
	 * @see prev()
	 * @see nextAll()
	 * @see siblings()
	 * @see contents()
	 * @see children()
	 */
	public function prevAll($selector = null) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			while (isset($m->previousSibling)) {
				$m = $m->previousSibling;
				if (XML_ELEMENT_NODE === $m->nodeType) {
					if ( ! empty($selector)) {
						if (QueryPath::with($m, null, $this->options)->is($selector)) {
							$found->attach($m);
						}
					} else {
						$found->attach($m);
					}
				}
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get the children of the elements in the DOMQuery object.
	 *
	 * If a selector is provided, the list of children will be filtered through
	 * the selector.
	 *
	 * @param string $selector
	 *  A valid selector
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  A DOMQuery wrapping all of the children
	 *
	 * @see removeChildren()
	 * @see parent()
	 * @see parents()
	 * @see next()
	 * @see prev()
	 */
	public function children($selector = null) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		$filter = strlen($selector ?? '') > 0;

		if ($filter) {
			/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
			$tmp = new SplObjectStorage();
		}
		foreach ($this->getMatches() as $m) {
			foreach ($m->childNodes as $c) {
				if (XML_ELEMENT_NODE === $c->nodeType) {
					// This is basically an optimized filter() just for children().
					if ($filter) {
						$tmp->attach($c);
						$query = new DOMTraverser($tmp, true, $c);
						$query->find($selector);
						if (count($query->matches()) > 0) {
							$found->attach($c);
						}
						$tmp->detach($c);
					} // No filter. Just attach it.
					else {
						$found->attach($c);
					}
				}
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get all child nodes (not just elements) of all items in the matched set.
	 *
	 * It gets only the immediate children, not all nodes in the subtree.
	 *
	 * This does not process iframes. Xinclude processing is dependent on the
	 * DOM implementation and configuration.
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  A DOMNode object wrapping all child nodes for all elements in the
	 *  DOMNode object
	 *
	 * @see find()
	 * @see text()
	 * @see html()
	 * @see innerHTML()
	 * @see xml()
	 * @see innerXML()
	 */
	public function contents() : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			foreach ($m->childNodes as $c) {
				$found->attach($c);
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get a list of siblings for elements currently wrapped by this object.
	 *
	 * This will compile a list of every sibling of every element in the
	 * current list of elements.
	 *
	 * Note that if two siblings are present in the DOMQuery object to begin with,
	 * then both will be returned in the matched set, since they are siblings of each
	 * other. In other words,if the matches contain a and b, and a and b are siblings of
	 * each other, than running siblings will return a set that contains
	 * both a and b.
	 *
	 * @param string $selector
	 *  If the optional selector is provided, siblings will be filtered through
	 *  this expression
	 *
	 * @throws ParseException
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery containing the matched siblings
	 *
	 * @see contents()
	 * @see children()
	 * @see parent()
	 * @see parents()
	 */
	public function siblings($selector = null) : Query
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			$parent = $m->parentNode;
			foreach ($parent->childNodes ?? [] as $n) {
				if (XML_ELEMENT_NODE === $n->nodeType && $n !== $m) {
					$found->attach($n);
				}
			}
		}
		if (empty($selector)) {
			return $this->inst($found, null);
		}

		return $this->inst($found, null)->filter($selector);
	}
}
