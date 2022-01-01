<?php

declare(strict_types=1);

namespace QueryPath\Helpers;

use function assert;
use function count;
use DOMDocument;
use DOMDocumentFragment;
use DOMElement;
use DOMNode;
use function in_array;
use function is_array;
use function is_null;
use QueryPath\CSS\ParseException;
use QueryPath\CSS\QueryPathEventHandler;
use QueryPath\DOMQuery;
use QueryPath\Exception;
use QueryPath\Query;
use QueryPath\QueryPath;
use QueryPath\TextContent;
use SimpleXMLElement;
use SplObjectStorage;
use UnexpectedValueException;

trait QueryMutators
{
	/**
	 * Empty everything within the specified element.
	 *
	 * A convenience function for removeChildren(). This is equivalent to jQuery's
	 * empty() function. However, `empty` is a built-in in PHP, and cannot be used as a
	 * function name.
	 *
	 * @return $this
	 *  The DOMQuery object with the newly emptied elements
	 *
	 * @see        removeChildren()
	 * @since      2.1
	 *
	 * @author     eabrand
	 *
	 * @deprecated the removeChildren() function is the preferred method
	 */
	public function emptyElement() : DOMQuery
	{
		$this->removeChildren();

		return $this;
	}

	/**
	 * Insert the given markup as the last child.
	 *
	 * The markup will be inserted into each match in the set.
	 *
	 * The same element cannot be inserted multiple times into a document. DOM
	 * documents do not allow a single object to be inserted multiple times
	 * into the DOM. To insert the same XML repeatedly, we must first clone
	 * the object. This has one practical implication: Once you have inserted
	 * an element into the object, you cannot further manipulate the original
	 * element and expect the changes to be replciated in the appended object.
	 * (They are not the same -- there is no shared reference.) Instead, you
	 * will need to retrieve the appended object and operate on that.
	 *
	 * @param string|DOMNode|DOMDocumentFragment|DOMQuery|SimpleXMLElement|TextContent|null $data
	 *  This can be either a string (the usual case), or a DOM Element
	 *
	 *  Thrown if $data is an unsupported object type
	 *
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery object
	 *
	 * @see appendTo()
	 * @see prepend()
	 */
	public function append(string|DOMNode|DOMDocumentFragment|DOMQuery|SimpleXMLElement|TextContent|null $data) : DOMQuery
	{
		/** @var DOMDocumentFragment|DOMNode|null */
		$data = $this->prepareInsert($data);
		if (isset($data)) {
			if (empty($this->document()->documentElement) && 0 === $this->getMatches()->count()) {
				// Then we assume we are writing to the doc root
				$this->document()->appendChild($data);
				/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
				$found = new SplObjectStorage();
				$element = $this->document()->documentElement;
				assert(
					$element instanceof DOMNode,
					new UnexpectedValueException(
						'Freshly appended document element not found!'
					)
				);
				$found->attach($element);
				$this->setMatches($found);
			} else {
				// You can only append in item once. So in cases where we
				// need to append multiple times, we have to clone the node.
				foreach ($this->getMatches() as $m) {
					if ( ! ($m instanceof DOMNode)) {
						continue;
					}
					// DOMDocumentFragments are even more troublesome, as they don't
					// always clone correctly. So we have to clone their children.
					if ($data instanceof DOMDocumentFragment) {
						foreach ($data->childNodes as $n) {
							$m->appendChild($n->cloneNode(true));
						}
					} else {
						// Otherwise a standard clone will do.
						$cloned = $data->cloneNode(true);
						$m->appendChild($cloned);
					}
				}
			}
		}

		return $this;
	}

	/**
	 * Insert the given markup as the first child.
	 *
	 * The markup will be inserted into each match in the set.
	 *
	 * @param string|DOMQuery|DOMNode|SimpleXMLElement|TextContent|null $data
	 *  This can be either a string (the usual case), or a DOM Element
	 *
	 *  Thrown if $data is an unsupported object type
	 *
	 * @throws Exception
	 *
	 * @see append()
	 * @see before()
	 * @see after()
	 * @see prependTo()
	 */
	public function prepend(string|DOMQuery|DOMNode|SimpleXMLElement|TextContent|null $data) : DOMQuery
	{
		/** @var DOMDocumentFragment|DOMNode|null */
		$data = $this->prepareInsert($data);
		if (isset($data)) {
			foreach ($this->getMatches() as $m) {
				if ( ! ($m instanceof DOMNode)) {
					continue;
				}
				$ins = $data->cloneNode(true);
				if ($m->hasChildNodes()) {
					$m->insertBefore($ins, $m->childNodes->item(0));
				} else {
					$m->appendChild($ins);
				}
			}
		}

		return $this;
	}

	/**
	 * Take all nodes in the current object and prepend them to the children nodes of
	 * each matched node in the passed-in DOMQuery object.
	 *
	 * This will iterate through each item in the current DOMQuery object and
	 * add each item to the beginning of the children of each element in the
	 * passed-in DOMQuery object.
	 *
	 * @see insertBefore()
	 * @see insertAfter()
	 * @see prepend()
	 * @see appendTo()
	 *
	 * @param DOMQuery $dest
	 *  The destination DOMQuery object
	 *
	 *  Thrown if $data is an unsupported object type
	 *
	 * @return \QueryPath\DOMQuery
	 *  The original DOMQuery, unmodified. NOT the destination DOMQuery.
	 */
	public function prependTo(Query $dest)
	{
		foreach ($this->getMatches() as $m) {
			$dest->prepend($m);
		}

		return $this;
	}

	/**
	 * Insert the given data before each element in the current set of matches.
	 *
	 * This will take the give data (XML or HTML) and put it before each of the items that
	 * the DOMQuery object currently contains. Contrast this with after().
	 *
	 * @param string|DOMQuery|DOMNode|SimpleXMLElement|null $data
	 *  The data to be inserted. This can be XML in a string, a DomFragment, a DOMElement,
	 *  or the other usual suspects. (See {@link qp()}).
	 *
	 *  Thrown if $data is an unsupported object type
	 *
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  Returns the DOMQuery with the new modifications. The list of elements currently
	 *  selected will remain the same.
	 *
	 * @see insertBefore()
	 * @see after()
	 * @see append()
	 * @see prepend()
	 */
	public function before(string|DOMQuery|DOMNode|SimpleXMLElement|null $data) : DOMQuery
	{
		/** @var DOMDocumentFragment|DOMNode|null */
		$data = $this->prepareInsert($data);
		assert(
			! is_null($data),
			new UnexpectedValueException('data was null!')
		);
		foreach ($this->getMatches() as $m) {
			if ( ! ($m instanceof DOMNode)) {
				continue;
			}
			assert(
				$m->parentNode instanceof DOMNode,
				new UnexpectedValueException(
					'parentNode not found!'
				)
			);
			$ins = $data->cloneNode(true);
			$m->parentNode->insertBefore($ins, $m);
		}

		return $this;
	}

	/**
	 * Insert the current elements into the destination document.
	 * The items are inserted before each element in the given DOMQuery document.
	 * That is, they will be siblings with the current elements.
	 *
	 * @param query $dest
	 *  Destination DOMQuery document
	 *
	 *  Thrown if $data is an unsupported object type
	 *
	 * @return \QueryPath\DOMQuery
	 *  The current DOMQuery object, unaltered. Only the destination DOMQuery
	 *  object is altered.
	 *
	 * @see before()
	 * @see insertAfter()
	 * @see appendTo()
	 */
	public function insertBefore(Query $dest) : DOMQuery
	{
		foreach ($this->getMatches() as $m) {
			$dest->before($m);
		}

		return $this;
	}

	/**
	 * Insert the contents of the current DOMQuery after the nodes in the
	 * destination DOMQuery object.
	 *
	 * @param query $dest
	 *  Destination object where the current elements will be deposited
	 *
	 *  Thrown if $data is an unsupported object type
	 *
	 * @return \QueryPath\DOMQuery
	 *  The present DOMQuery, unaltered. Only the destination object is altered.
	 *
	 * @see after()
	 * @see insertBefore()
	 * @see append()
	 */
	public function insertAfter(Query $dest) : DOMQuery
	{
		foreach ($this->getMatches() as $m) {
			$dest->after($m);
		}

		return $this;
	}

	/**
	 * Insert the given data after each element in the current DOMQuery object.
	 *
	 * This inserts the element as a peer to the currently matched elements.
	 * Contrast this with {@link append()}, which inserts the data as children
	 * of matched elements.
	 *
	 * @param string|DOMNode|DOMDocumentFragment|DOMQuery|SimpleXMLElement|null $data
	 *  The data to be appended
	 *
	 *  Thrown if $data is an unsupported object type
	 *
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery object (with the items inserted)
	 *
	 * @see before()
	 * @see append()
	 */
	public function after(string|DOMNode|DOMDocumentFragment|DOMQuery|SimpleXMLElement|null $data) : DOMQuery
	{
		if (empty($data)) {
			return $this;
		}
		/** @var DOMDocumentFragment|DOMNode|null */
		$data = $this->prepareInsert($data);
		assert(
			! is_null($data),
			new UnexpectedValueException('data was null!')
		);
		foreach ($this->getMatches() as $m) {
			assert(
				$m->parentNode instanceof DOMNode,
				new UnexpectedValueException(
					'parentNode not found!'
				)
			);
			$ins = $data->cloneNode(true);
			if (isset($m->nextSibling)) {
				$m->parentNode->insertBefore($ins, $m->nextSibling);
			} else {
				$m->parentNode->appendChild($ins);
			}
		}

		return $this;
	}

	/**
	 * Replace the existing element(s) in the list with a new one.
	 *
	 * @param string|DOMQuery|DOMNode|SimpleXMLElement|null $new
	 *  A DOMElement or XML in a string. This will replace all elements
	 *  currently wrapped in the DOMQuery object.
	 *
	 * @throws Exception
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery object wrapping <b>the items that were removed</b>.
	 *  This remains consistent with the jQuery API.
	 *
	 * @see append()
	 * @see prepend()
	 * @see before()
	 * @see after()
	 * @see remove()
	 * @see replaceAll()
	 */
	public function replaceWith(string|DOMQuery|DOMNode|SimpleXMLElement|null $new) : DOMQuery
	{
		/** @var DOMDocumentFragment|DOMNode|null */
		$data = $this->prepareInsert($new);
		assert(
			! is_null($data),
			new UnexpectedValueException('data was null!')
		);
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			if ( ! ($m instanceof DOMNode)) {
				continue;
			}
			assert(
				$m->parentNode instanceof DOMNode,
				new UnexpectedValueException(
					'parentNode not found!'
				)
			);
			$parent = $m->parentNode;
			$clone = $data->cloneNode(true);
			$parent->insertBefore($clone, $m);
			$found->attach($parent->removeChild($m));
		}

		return $this->inst($found, null);
	}

	/**
	 * Remove the parent element from the selected node or nodes.
	 *
	 * This takes the given list of nodes and "unwraps" them, moving them out of their parent
	 * node, and then deleting the parent node.
	 *
	 * For example, consider this:
	 *
	 * @code
	 *   <root><wrapper><content/></wrapper></root>
	 * @endcode
	 *
	 * Now we can run this code:
	 * @code
	 *   qp($xml, 'content')->unwrap();
	 * @endcode
	 *
	 * This will result in:
	 *
	 * @code
	 *   <root><content/></root>
	 * @endcode
	 * This is the opposite of wrap().
	 *
	 * <b>The root element cannot be unwrapped.</b> It has no parents.
	 * If you attempt to use unwrap on a root element, this will throw a
	 * QueryPath::Exception. (You can, however, "Unwrap" a child that is
	 * a direct descendant of the root element. This will remove the root
	 * element, and replace the child as the root element. Be careful, though.
	 * You cannot set more than one child as a root element.)
	 *
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery object, with the same element(s) selected
	 *
	 * @see    wrap()
	 * @since  2.1
	 *
	 * @author mbutcher
	 */
	public function unwrap() : DOMQuery
	{
		// We do this in two loops in order to
		// capture the case where two matches are
		// under the same parent. Othwerwise we might
		// remove a match before we can move it.
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$parents = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			if ( ! ($m instanceof DOMNode)) {
				continue;
			}
			assert(
				$m->ownerDocument instanceof DOMDocument,
				new UnexpectedValueException('ownerDocument missing!')
			);
			// Cannot unwrap the root element.
			if ($m->isSameNode($m->ownerDocument->documentElement)) {
				throw new \QueryPath\Exception('Cannot unwrap the root element.');
			}
			assert(
				$m->parentNode instanceof DOMNode,
				new UnexpectedValueException('parentNode missing!')
			);

			// Move children to peer of parent.
			$parent = $m->parentNode;
			$old = $parent->removeChild($m);
			assert(
				$parent->parentNode instanceof DOMNode,
				new UnexpectedValueException('parentNode missing!')
			);
			$parent->parentNode->insertBefore($old, $parent);
			$parents->attach($parent);
		}

		// Now that all the children are moved, we
		// remove all of the parents.
		foreach ($parents as $ele) {
			if ( ! ($ele instanceof DOMNode)) {
				continue;
			}
			assert(
				$ele->parentNode instanceof DOMNode,
				new UnexpectedValueException('parentNode missing!')
			);
			$ele->parentNode->removeChild($ele);
		}

		return $this;
	}

	/**
	 * Wrap each element inside of the given markup.
	 *
	 * Markup is usually a string, but it can also be a DOMNode, a document
	 * fragment, a SimpleXMLElement, or another DOMNode object (in which case
	 * the first item in the list will be used.)
	 *
	 * @param string|DOMQuery|DOMNode|SimpleXMLElement|null $markup
	 *  Markup that will wrap each element in the current list
	 *
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery object with the wrapping changes made
	 *
	 * @see wrapAll()
	 * @see wrapInner()
	 */
	public function wrap(string|DOMQuery|DOMNode|SimpleXMLElement|null $markup) : DOMQuery
	{
		/** @var DOMDocumentFragment|DOMNode|null */
		$data = $this->prepareInsert($markup);

		// If the markup passed in is empty, we don't do any wrapping.
		if (empty($data)) {
			return $this;
		}

		foreach ($this->getMatches() as $m) {
			if ( ! ($m instanceof DOMNode)) {
				continue;
			}

			$data_source = ($data instanceof DOMDocumentFragment) ? $data->firstChild : $data;

			assert(
				($data_source instanceof DOMNode),
				new UnexpectedValueException('clone not a DOMNode!')
			);

			$copy = $data_source->cloneNode(true);

			// XXX: Should be able to avoid doing this over and over.
			if ($copy->hasChildNodes()) {
				$deepest = $this->deepestNode($copy);
				// FIXME: Does this need a different data structure?
				$bottom = $deepest[0];
			} else {
				$bottom = $copy;
			}

			assert(
				$m->parentNode instanceof DOMNode,
				new UnexpectedValueException(
					'parentNode not found!'
				)
			);

			$parent = $m->parentNode;
			$parent->insertBefore($copy, $m);
			$m = $parent->removeChild($m);
			$bottom->appendChild($m);
		}

		return $this;
	}

	/**
	 * Wrap all elements inside of the given markup.
	 *
	 * So all elements will be grouped together under this single marked up
	 * item. This works by first determining the parent element of the first item
	 * in the list. It then moves all of the matching elements under the wrapper
	 * and inserts the wrapper where that first element was found. (This is in
	 * accordance with the way jQuery works.)
	 *
	 * Markup is usually XML in a string, but it can also be a DOMNode, a document
	 * fragment, a SimpleXMLElement, or another DOMNode object (in which case
	 * the first item in the list will be used.)
	 *
	 * @param string|\QueryPath\DOMQuery|DOMNode|SimpleXMLElement $markup
	 *  Markup that will wrap all elements in the current list
	 *
	 * @throws Exception
	 *
	 * @return $this|null
	 *  The DOMQuery object with the wrapping changes made
	 *
	 * @see wrap()
	 * @see wrapInner()
	 */
	public function wrapAll(string|DOMQuery|DOMNode|SimpleXMLElement $markup) : ?DOMQuery
	{
		if (0 === $this->getMatches()->count()) {
			return null;
		}

		/** @var DOMDocumentFragment|DOMNode|null */
		$data = $this->prepareInsert($markup);

		if (empty($data)) {
			return $this;
		}

		$data_source = ($data instanceof DOMDocumentFragment) ? $data->firstChild : $data;

		assert(
			($data_source instanceof DOMNode),
			new UnexpectedValueException('clone not a DOMNode!')
		);

		$data = $data_source->cloneNode(true);

		if ($data->hasChildNodes()) {
			$deepest = $this->deepestNode($data);
			// FIXME: Does this need fixing?
			$bottom = $deepest[0];
		} else {
			$bottom = $data;
		}

		$first = $this->getFirstMatch();
		assert(
			(
				($first instanceof DOMNode)
				&& ($first->parentNode instanceof DOMNode)
			),
			new UnexpectedValueException(
				'parentNode not found!'
			)
		);
		$parent = $first->parentNode;
		$parent->insertBefore($data, $first);
		foreach ($this->getMatches() as $m) {
			if ( ! ($m instanceof DOMNode)) {
				continue;
			}
			assert(
				$m->parentNode instanceof DOMNode,
				new UnexpectedValueException(
					'parentNode not found!'
				)
			);
			$bottom->appendChild($m->parentNode->removeChild($m));
		}

		return $this;
	}

	/**
	 * Wrap the child elements of each item in the list with the given markup.
	 *
	 * Markup is usually a string, but it can also be a DOMNode, a document
	 * fragment, a SimpleXMLElement, or another DOMNode object (in which case
	 * the first item in the list will be used.)
	 *
	 * @param string|\QueryPath\DOMQuery $markup
	 *  Markup that will wrap children of each element in the current list
	 *
	 * @throws \QueryPath\Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery object with the wrapping changes made
	 *
	 * @see wrap()
	 * @see wrapAll()
	 */
	public function wrapInner(string|DOMQuery $markup)
	{
		/** @var DOMDocumentFragment|DOMNode|null */
		$data = $this->prepareInsert($markup);

		// No data? Short circuit.
		if (empty($data)) {
			return $this;
		}

		foreach ($this->getMatches() as $m) {
			if ( ! ($m instanceof DOMNode)) {
				continue;
			}
			$wrapper_source = ($data instanceof DOMDocumentFragment) ? $data->firstChild : $data;
			assert(
				($wrapper_source instanceof DOMNode),
				new UnexpectedValueException('wrapper source not a DOMNode!')
			);
			$wrapper = $wrapper_source->cloneNode(true);

			if ($wrapper->hasChildNodes()) {
				$deepest = $this->deepestNode($wrapper);
				// FIXME: ???
				$bottom = $deepest[0];
			} else {
				$bottom = $wrapper;
			}

			if ($m->hasChildNodes()) {
				while ($m->firstChild) {
					$kid = $m->removeChild($m->firstChild);
					$bottom->appendChild($kid);
				}
			}

			$m->appendChild($wrapper);
		}

		return $this;
	}

	/**
	 * Reduce the set of matches to the deepest child node in the tree.
	 *
	 * This loops through the matches and looks for the deepest child node of all of
	 * the matches. "Deepest", here, is relative to the nodes in the list. It is
	 * calculated as the distance from the starting node to the most distant child
	 * node. In other words, it is not necessarily the farthest node from the root
	 * element, but the farthest note from the matched element.
	 *
	 * In the case where there are multiple nodes at the same depth, all of the
	 * nodes at that depth will be included.
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery wrapping the single deepest node
	 */
	public function deepest() : DOMQuery
	{
		$deepest = 0;
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$winner = new SplObjectStorage();
		foreach ($this->getMatches() as $m) {
			$local_deepest = 0;
			$local_ele = $this->deepestNode($m, 0, null, $local_deepest);

			// Replace with the new deepest.
			if ($local_deepest > $deepest) {
				/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
				$winner = new SplObjectStorage();
				foreach ($local_ele as $lele) {
					$winner->attach($lele);
				}
				$deepest = $local_deepest;
			} // Augument with other equally deep elements.
			elseif ($local_deepest === $deepest) {
				foreach ($local_ele as $lele) {
					$winner->attach($lele);
				}
			}
		}

		return $this->inst($winner, null);
	}

	/**
	 * Add a class to all elements in the current DOMQuery.
	 *
	 * This searchers for a class attribute on each item wrapped by the current
	 * DOMNode object. If no attribute is found, a new one is added and its value
	 * is set to $class. If a class attribute is found, then the value is appended
	 * on to the end.
	 *
	 * @param string $class
	 *  The name of the class
	 *
	 * @return \QueryPath\DOMQuery
	 *  Returns the DOMQuery object
	 *
	 * @see css()
	 * @see attr()
	 * @see removeClass()
	 * @see hasClass()
	 */
	public function addClass($class)
	{
		foreach ($this->getMatches() as $m) {
			assert(
				$m instanceof DOMElement,
				new UnexpectedValueException(
					'match not an Element!'
				)
			);
			if ($m->hasAttribute('class')) {
				$val = $m->getAttribute('class');
				$m->setAttribute('class', $val . ' ' . $class);
			} else {
				$m->setAttribute('class', $class);
			}
		}

		return $this;
	}

	/**
	 * Remove the named class from any element in the DOMQuery that has it.
	 *
	 * This may result in the entire class attribute being removed. If there
	 * are other items in the class attribute, though, they will not be removed.
	 *
	 * Example:
	 * Consider this XML:
	 *
	 * @code
	 * <element class="first second"/>
	 * @endcode
	 *
	 * Executing this fragment of code will remove only the 'first' class:
	 * @code
	 * qp(document, 'element')->removeClass('first');
	 * @endcode
	 *
	 * The resulting XML will be:
	 * @code
	 * <element class="second"/>
	 * @endcode
	 *
	 * To remove the entire 'class' attribute, you should use {@see removeAttr()}.
	 *
	 * @param string|false $class
	 *  The class name to remove
	 *
	 * @return \QueryPath\DOMQuery
	 *  The modified DOMNode object
	 *
	 * @see attr()
	 * @see addClass()
	 * @see hasClass()
	 */
	public function removeClass(string|bool $class = false) : DOMQuery
	{
		if (empty($class)) {
			foreach ($this->getMatches() as $m) {
				assert(
					$m instanceof DOMElement,
					new UnexpectedValueException(
						'match not an Element!'
					)
				);
				$m->removeAttribute('class');
			}
		} else {
			$to_remove = array_filter(explode(' ', $class));
			foreach ($this->getMatches() as $m) {
				if ( ! ($m instanceof DOMElement)) {
					continue;
				}
				if ($m->hasAttribute('class')) {
					$vals = array_filter(explode(' ', $m->getAttribute('class')));
					$buf = [];
					foreach ($vals as $v) {
						if ( ! in_array($v, $to_remove, true)) {
							$buf[] = $v;
						}
					}
					if (empty($buf)) {
						$m->removeAttribute('class');
					} else {
						$m->setAttribute('class', implode(' ', $buf));
					}
				}
			}
		}

		return $this;
	}

	/**
	 * Detach any items from the list if they match the selector.
	 *
	 * In other words, each item that matches the selector will be removed
	 * from the DOM document. The returned DOMQuery wraps the list of
	 * removed elements.
	 *
	 * If no selector is specified, this will remove all current matches from
	 * the document.
	 *
	 * @param string|null $selector
	 *  A CSS Selector
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  The Query path wrapping a list of removed items
	 *
	 * @see    replaceAll()
	 * @see    replaceWith()
	 * @see    removeChildren()
	 * @since  2.1
	 *
	 * @author eabrand
	 */
	public function detach(string $selector = null) : DOMQuery
	{
		if (null !== $selector) {
			$this->find($selector);
		}

		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		$this->last = $this->matches;
		foreach ($this->getMatches() as $item) {
			if ( ! ($item instanceof DOMNode)) {
				continue;
			}
			assert(
				$item->parentNode instanceof DOMNode,
				new UnexpectedValueException(
					'parentNode not found!'
				)
			);
			// The item returned is (according to docs) different from
			// the one passed in, so we have to re-store it.
			$found->attach($item->parentNode->removeChild($item));
		}

		return $this->inst($found, null);
	}

	/**
	 * Attach any items from the list if they match the selector.
	 *
	 * If no selector is specified, this will remove all current matches from
	 * the document.
	 *
	 * @param DOMQuery $dest
	 *  A DOMQuery Selector
	 *
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  The Query path wrapping a list of removed items
	 *
	 * @see    replaceAll()
	 * @see    replaceWith()
	 * @see    removeChildren()
	 * @since  2.1
	 *
	 * @author eabrand
	 */
	public function attach(DOMQuery $dest) : DOMQuery
	{
		foreach ($this->last ?? [] as $m) {
			$dest->append($m);
		}

		return $this;
	}

	/**
	 * Append the current elements to the destination passed into the function.
	 *
	 * This cycles through all of the current matches and appends them to
	 * the context given in $destination. If a selector is provided then the
	 * $destination is queried (using that selector) prior to the data being
	 * appended. The data is then appended to the found items.
	 *
	 * @param DOMQuery $dest
	 *  A DOMQuery object that will be appended to
	 *
	 *  Thrown if $data is an unsupported object type
	 *
	 * @throws Exception
	 *
	 * @return \QueryPath\DOMQuery
	 *  The original DOMQuery, unaltered. Only the destination DOMQuery will
	 *  be modified.
	 *
	 * @see append()
	 * @see prependTo()
	 */
	public function appendTo(DOMQuery $dest) : DOMQuery
	{
		foreach ($this->getMatches() as $m) {
			$dest->append($m);
		}

		return $this;
	}

	/**
	 * Remove any items from the list if they match the selector.
	 *
	 * In other words, each item that matches the selector will be remove
	 * from the DOM document. The returned DOMQuery wraps the list of
	 * removed elements.
	 *
	 * If no selector is specified, this will remove all current matches from
	 * the document.
	 *
	 * @param string|null $selector
	 *  A CSS Selector
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  The Query path wrapping a list of removed items
	 *
	 * @see replaceAll()
	 * @see replaceWith()
	 * @see removeChildren()
	 */
	public function remove(string $selector = null) : DOMQuery
	{
		if ( ! empty($selector)) {
			// Do a non-destructive find.
			$query = new QueryPathEventHandler($this->getMatches());
			$query->find($selector);
			$matches = $query->getMatches();
		} else {
			$matches = $this->getMatches();
		}

		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$found = new SplObjectStorage();
		foreach ($matches as $item) {
			if ( ! ($item instanceof DOMNode)) {
				continue;
			}
			assert(
				$item->parentNode instanceof DOMNode,
				new UnexpectedValueException(
					'parentNode not found!'
				)
			);
			// The item returned is (according to docs) different from
			// the one passed in, so we have to re-store it.
			$found->attach($item->parentNode->removeChild($item));
		}

		// Return a clone DOMQuery with just the removed items. If
		// no items are found, this will return an empty DOMQuery.
		return 0 === count($found) ? new static() : new static($found);
	}

	/**
	 * This replaces everything that matches the selector with the first value
	 * in the current list.
	 *
	 * This is the reverse of replaceWith.
	 *
	 * Unlike jQuery, DOMQuery cannot assume a default document. Consequently,
	 * you must specify the intended destination document. If it is omitted, the
	 * present document is assumed to be tthe document. However, that can result
	 * in undefined behavior if the selector and the replacement are not sufficiently
	 * distinct.
	 *
	 * @param string $selector
	 *  The selector
	 * @param DOMDocument $document
	 *  The destination document
	 *
	 * @throws ParseException
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery wrapping the modified document
	 *
	 * @deprecated due to the fact that this is not a particularly friendly method,
	 *  and that it can be easily replicated using {@see replaceWith()}, it is to be
	 *  considered deprecated
	 * @see        remove()
	 * @see        replaceWith()
	 */
	public function replaceAll(string $selector, DOMDocument $document) : DOMQuery
	{
		$replacement = $this->getMatches()->count() > 0 ? $this->getFirstMatch() : $this->document()->createTextNode('');
		assert(
			$replacement instanceof DOMNode,
			new UnexpectedValueException(
				'replacement not a DOMNode!'
			)
		);

		$c = new QueryPathEventHandler($document);
		$c->find($selector);
		$temp = $c->getMatches();
		foreach ($temp as $item) {
			if ( ! ($item instanceof DOMNode)) {
				continue;
			}
			$node = $replacement->cloneNode();
			$node = $document->importNode($node);
			assert(
				$item->parentNode instanceof DOMNode,
				new UnexpectedValueException(
					'parentNode not found!'
				)
			);
			$item->parentNode->replaceChild($node, $item);
		}

		return QueryPath::with($document, null, $this->options);
	}

	/**
	 * Add more elements to the current set of matches.
	 *
	 * This begins the new query at the top of the DOM again. The results found
	 * when running this selector are then merged into the existing results. In
	 * this way, you can add additional elements to the existing set.
	 *
	 * @param string $selector
	 *  A valid selector
	 *
	 * @return \QueryPath\DOMQuery
	 *  The DOMQuery object with the newly added elements
	 *
	 * @see append()
	 * @see after()
	 * @see andSelf()
	 * @see end()
	 */
	public function add(string $selector) : DOMQuery
	{
		// This is destructive, so we need to set $last:
		$this->last = $this->matches;

		foreach (QueryPath::with($this->document(), $selector, $this->options)->get() as $item) {
			$this->getMatches()->attach($item);
		}

		return $this;
	}

	/**
	 * Remove all child nodes.
	 *
	 * This is equivalent to jQuery's empty() function. (However, empty() is a
	 * PHP built-in, and cannot be used as a method name.)
	 *
	 * @return $this
	 *  The DOMQuery object with the child nodes removed
	 *
	 * @see replaceWith()
	 * @see replaceAll()
	 * @see remove()
	 */
	public function removeChildren() : DOMQuery
	{
		foreach ($this->getMatches() as $m) {
			if ( ! ($m instanceof DOMNode)) {
				continue;
			}
			while ($kid = $m->firstChild) {
				$m->removeChild($kid);
			}
		}

		return $this;
	}

	/**
	 * Get/set an attribute.
	 * - If no parameters are specified, this returns an associative array of all
	 *   name/value pairs.
	 * - If both $name and $value are set, then this will set the attribute name/value
	 *   pair for all items in this object.
	 * - If $name is set, and is an array, then
	 *   all attributes in the array will be set for all items in this object.
	 * - If $name is a string and is set, then the attribute value will be returned.
	 *
	 * When an attribute value is retrieved, only the attribute value of the FIRST
	 * match is returned.
	 *
	 * @template T0 as array|string|null
	 * @template T1 as string|null
	 *
	 * @param T0 $name
	 *   The name of the attribute or an associative array of name/value pairs
	 * @param T1 $value
	 *   A value (used only when setting an individual property)
	 *
	 * @return (T0 is null ? (array<string, string>|null) : (T0 is array ? \QueryPath\DOMQuery : (T1 is string ? DOMQuery : (string|int|null))))
	 *   If this was a setter request, return the DOMQuery object. If this was
	 *   an access request (getter), return the string value.
	 *
	 * @see removeAttr()
	 * @see tag()
	 * @see hasAttr()
	 * @see hasClass()
	 */
	public function attr(array|string $name = null, string $value = null) : array|DOMQuery|string|int|null
	{
		// Default case: Return all attributes as an assoc array.
		if (is_null($name)) {
			if (0 === $this->getMatches()->count()) {
				return null;
			}
			$ele = $this->getFirstMatch();

			assert(
				$ele instanceof DOMElement,
				new UnexpectedValueException(
					'ele not a DOMElement!'
				)
			);

			/** @var array<string, string> */
			$buffer = [];

			// This does not appear to be part of the DOM
			// spec. Nor is it documented. But it works.
			foreach ($ele->attributes as $name => $attrNode) {
				$buffer[$name] = $attrNode->value;
			}

			return $buffer;
		}

		// multi-setter
		if (is_array($name)) {
			/** @var array<string, string> */
			$name = $name;

			foreach ($name as $k => $v) {
				foreach ($this->getMatches() as $m) {
					if ( ! ($m instanceof DOMElement)) {
						continue;
					}
					$m->setAttribute($k, $v);
				}
			}

			return $this;
		}
		// setter
		if (isset($value)) {
			foreach ($this->getMatches() as $m) {
				if ( ! ($m instanceof DOMElement)) {
					continue;
				}
				$m->setAttribute((string) $name, $value);
			}

			return $this;
		}

		//getter
		if (0 === $this->getMatches()->count()) {
			return null;
		}

		$firstMatch = $this->getFirstMatch();

		assert(
			! is_null($firstMatch),
			new UnexpectedValueException(
				'firstMatch was null!'
			)
		);

		// Special node type handler:
		if ('nodeType' === $name) {
			return $firstMatch->nodeType;
		}

		assert(
			$firstMatch instanceof DOMElement,
			new UnexpectedValueException(
				'firstMatch not a DOMElement!'
			)
		);

		// Always return first match's attr.
		return $firstMatch->getAttribute((string) $name);
	}

	/**
	 * Set/get a CSS value for the current element(s).
	 * This sets the CSS value for each element in the DOMQuery object.
	 * It does this by setting (or getting) the style attribute (without a namespace).
	 *
	 * For example, consider this code:
	 *
	 * @code
	 * <?php
	 * qp(HTML_STUB, 'body')->css('background-color','red')->html();
	 * ?>
	 * @endcode
	 * This will return the following HTML:
	 * @code
	 * <body style="background-color: red"/>
	 * @endcode
	 *
	 * If no parameters are passed into this function, then the current style
	 * element will be returned unparsed. Example:
	 * @code
	 * <?php
	 * qp(HTML_STUB, 'body')->css('background-color','red')->css();
	 * ?>
	 * @endcode
	 * This will return the following:
	 * @code
	 * background-color: red
	 * @endcode
	 *
	 * As of QueryPath 2.1, existing style attributes will be merged with new attributes.
	 * (In previous versions of QueryPath, a call to css() overwrite the existing style
	 * values).
	 *
	 * @template T as string|array<string, string>|null
	 *
	 * @param T $name
	 *  If this is a string, it will be used as a CSS name. If it is an array,
	 *  this will assume it is an array of name/value pairs of CSS rules. It will
	 *  apply all rules to all elements in the set.
	 * @param string $value
	 *  The value to set. This is only set if $name is a string.
	 *
	 * @return (T is null ? (string|int|null) : DOMQuery)
	 */
	public function css(string|array|null $name = null, string $value = '') : DOMQuery|string|int|null
	{
		if (empty($name)) {
			return $this->attr('style');
		}

		// Get any existing CSS.
		/** @var array<string, string> */
		$css = [];
		foreach ($this->getMatches() as $match) {
			if ( ! ($match instanceof DOMElement)) {
				continue;
			}
			$style = $match->getAttribute('style');
			if ( ! empty($style)) {
				// XXX: Is this sufficient?
				$style_array = explode(';', $style);
				foreach ($style_array as $item) {
					$item = trim($item);

					// Skip empty attributes.
					if ('' === $item) {
						continue;
					}

					[$css_att, $css_val] = explode(':', $item, 2);
					$css[$css_att] = trim($css_val);
				}
			}
		}

		if (is_array($name)) {
			// Use array_merge instead of + to preserve order.
			$css = array_merge($css, $name);
		} else {
			$css[$name] = $value;
		}

		// Collapse CSS into a string.
		$format = '%s: %s;';
		$css_string = '';
		/** @var string */
		foreach ($css as $n => $v) {
			$css_string .= sprintf($format, $n, trim($v));
		}

		$this->attr('style', $css_string);

		return $this;
	}
}
