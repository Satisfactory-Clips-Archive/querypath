<?php

declare(strict_types=1);
/**
 * @file
 *
 * PseudoClass class.
 *
 * This is the first pass in an experiment to break PseudoClass handling
 * out of the normal traversal. Eventually, this should become a
 * top-level pluggable registry that will allow custom pseudoclasses.
 * For now, though, we just handle the core pseudoclasses.
 */

namespace QueryPath\CSS\DOMTraverser;

use function count;
use DOMElement;
use DOMNode;
use function is_int;
use const PHP_URL_SCHEME;
use QueryPath\CSS\EventHandler;
use QueryPath\CSS\NotImplementedException;
use QueryPath\CSS\ParseException;
use QueryPath\TextContent;
use SplObjectStorage;
use const XML_ELEMENT_NODE;
use const XML_TEXT_NODE;

/**
 *  The PseudoClass handler.
 */
class PseudoClass
{
	/**
	 * Tests whether the given element matches the given pseudoclass.
	 *
	 * @param string $pseudoclass
	 *   The string name of the pseudoclass
	 * @param DOMNode $node
	 *   The DOMNode to be tested
	 * @param DOMNode|null $scope
	 *   The DOMElement that is the active root for this node
	 * @param string|null $value
	 *   The optional value string provided with this class. This is
	 *   used, for example, in an+b psuedoclasses.
	 *
	 * @throws \QueryPath\CSS\ParseException
	 * @throws NotImplementedException
	 *
	 * @return bool
	 *   TRUE if the node matches, FALSE otherwise
	 */
	public function elementMatches(string $pseudoclass, DOMNode $node, ?DOMNode $scope, string $value = null) : bool
	{
		$name = strtolower($pseudoclass);
		// Need to handle known pseudoclasses.
		switch ($name) {
			case 'current':
			case 'past':
			case 'future':
			case 'visited':
			case 'hover':
			case 'active':
			case 'focus':
			case 'animated': //  Last 3 are from jQuery
			case 'visible':
			case 'hidden':
				// These require a UA, which we don't have.
			case 'valid':
			case 'invalid':
			case 'required':
			case 'optional':
			case 'read-only':
			case 'read-write':
				// Since we don't know how to validate elements,
				// we can't supply these.
			case 'dir':
				// FIXME: I don't know how to get directionality info.
			case 'nth-column':
			case 'nth-last-column':
				// We don't know what a column is in most documents.
				// FIXME: Can we do this for HTML?
			case 'target':
				// This requires a location URL, which we don't have.
				return false;
			case 'indeterminate':
				// Because sometimes screwing with people is fun.
				return (bool) mt_rand(0, 1);
			case 'lang':
				// No value = exception.
				if ( ! isset($value)) {
					throw new NotImplementedException(':lang() requires a value.');
				}

				return ($node instanceof DOMElement) && $this->lang($node, $value);
			case 'any-link':
				return ($node instanceof DOMElement)
					&& (Util::matchesAttribute($node, 'href')
					|| Util::matchesAttribute($node, 'src')
					|| Util::matchesAttribute($node, 'link'));
			case 'link':
				return ($node instanceof DOMElement) && Util::matchesAttribute($node, 'href');
			case 'local-link':
				return ($node instanceof DOMElement) && $this->isLocalLink($node);
			case 'root':
				return isset($node->ownerDocument) && $node->isSameNode($node->ownerDocument->documentElement);

			// CSS 4 declares the :scope pseudo-class, which describes what was
			// the :x-root QueryPath extension.
			case 'x-root':
			case 'x-reset':
			case 'scope':
				return isset($scope) && $node->isSameNode($scope);
			// NON-STANDARD extensions for simple support of even and odd. These
			// are supported by jQuery, FF, and other user agents.
			case 'even':
				return ($node instanceof DOMElement) && $this->isNthChild($node, 'even');
			case 'odd':
				return ($node instanceof DOMElement) && $this->isNthChild($node, 'odd');
			case 'nth-child':
				return ($node instanceof DOMElement) && $this->isNthChild($node, $value);
			case 'nth-last-child':
				return ($node instanceof DOMElement) && $this->isNthChild($node, $value, true);
			case 'nth-of-type':
				return ($node instanceof DOMElement) && $this->isNthChild($node, $value, false, true);
			case 'nth-last-of-type':
				return ($node instanceof DOMElement) && $this->isNthChild($node, $value, true, true);
			case 'first-of-type':
				return ($node instanceof DOMElement) && $this->isFirstOfType($node);
			case 'last-of-type':
				return ($node instanceof DOMElement) && $this->isLastOfType($node);
			case 'only-of-type':
				return ($node instanceof DOMElement) && $this->isFirstOfType($node) && $this->isLastOfType($node);

			// Additional pseudo-classes defined in jQuery:
			case 'lt':
				// I'm treating this as "less than or equal to".
				$rule = sprintf('-n + %d', (int) $value);

				// $rule = '-n+15';
				return ($node instanceof DOMElement) && $this->isNthChild($node, $rule);
			case 'gt':
				// I'm treating this as "greater than"
				// return $this->nodePositionFromEnd($node) > (int) $value;
				return ($node instanceof DOMElement) && $this->nodePositionFromStart($node) > (int) $value;
			case 'nth':
			case 'eq':
				$rule = (int) $value;

				return ($node instanceof DOMElement) && $this->isNthChild($node, $rule);
			case 'first':
				return ($node instanceof DOMElement) && $this->isNthChild($node, 1);
			case 'first-child':
				return $this->isFirst($node);
			case 'last':
			case 'last-child':
				return $this->isLast($node);
			case 'only-child':
				return ($node instanceof DOMElement) && $this->isFirst($node) && $this->isLast($node);
			case 'empty':
				return $this->isEmpty($node);
			case 'parent':
				return ! $this->isEmpty($node);

			case 'enabled':
			case 'disabled':
			case 'checked':
				return ($node instanceof DOMElement) && Util::matchesAttribute($node, $name);
			case 'text':
			case 'radio':
			case 'checkbox':
			case 'file':
			case 'password':
			case 'submit':
			case 'image':
			case 'reset':
			case 'button':
				return ($node instanceof DOMElement) && Util::matchesAttribute($node, 'type', $name);

			case 'header':
				return ($node instanceof DOMElement) && $this->header($node);
			case 'has':
			case 'matches':
				return $this->has($node, $value);
			case 'not':
				if (empty($value)) {
					throw new ParseException(':not() requires a value.');
				}

				return $this->isNot($node, $value);
			// Contains == text matches.
			// In QP 2.1, this was changed.
			case 'contains':
				return $this->contains($node, $value);
			// Since QP 2.1
			case 'contains-exactly':
				return $this->containsExactly($node, $value);
			default:
				throw new ParseException('Unknown Pseudo-Class: ' . $name);
		}
	}

	/**
	 * Pseudo-class handler for :lang.
	 *
	 * Note that this does not implement the spec in its entirety because we do
	 * not presume to "know the language" of the document. If anyone is interested
	 * in making this more intelligent, please do so.
	 */
	protected function lang(DOMElement $node, ?string $value) : bool
	{
		// TODO: This checks for cases where an explicit language is
		// set. The spec seems to indicate that an element should inherit
		// language from the parent... but this is unclear.
		$operator = (false !== strpos($value ?? '', '-')) ? EventHandler::IS_EXACTLY : EventHandler::CONTAINS_WITH_HYPHEN;

		foreach ($node->attributes as $attrNode) {
			if ('lang' === $attrNode->localName) {
				if ($attrNode->nodeName === $attrNode->localName) {
					// fprintf(STDOUT, "%s in NS %s\n", $attrNode->name, $attrNode->nodeName);
					return Util::matchesAttribute($node, 'lang', $value, $operator);
				}

				$nsuri = $attrNode->namespaceURI;
				// fprintf(STDOUT, "%s in NS %s\n", $attrNode->name, $nsuri);
				return Util::matchesAttributeNS($node, 'lang', $nsuri, $value, $operator);
			}
		}

		return false;
	}

	/**
	 * Provides jQuery pseudoclass ':header'.
	 *
	 * @param $node
	 */
	protected function header(DOMElement $node) : bool
	{
		return 1 === preg_match('/^h[1-9]$/i', $node->tagName);
	}

	/**
	 * Provides pseudoclass :empty.
	 */
	protected function isEmpty(DOMNode $node) : bool
	{
		/** @var DOMNode */
		foreach ($node->childNodes as $kid) {
			// We don't want to count PIs and comments. From the spec, it
			// appears that CDATA is also not counted.
			if (XML_ELEMENT_NODE === $kid->nodeType || XML_TEXT_NODE === $kid->nodeType) {
				// As soon as we hit a FALSE, return.
				return false;
			}
		}

		return true;
	}

	/**
	 * Provides jQuery pseudoclass :first.
	 *
	 * @todo
	 *   This can be replaced by isNthChild().
	 */
	protected function isFirst(DOMNode $node) : bool
	{
		while (isset($node->previousSibling)) {
			$node = $node->previousSibling;
			if (($node instanceof DOMElement)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Fast version of first-of-type.
	 */
	protected function isFirstOfType(DOMElement $node) : bool
	{
		$type = $node->tagName;
		while (isset($node->previousSibling)) {
			$node = $node->previousSibling;
			if ( ! ($node instanceof DOMElement)) {
				continue;
			}
			if ($node->tagName === $type) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Fast version of jQuery :last.
	 */
	protected function isLast(DOMNode $node) : bool
	{
		while (isset($node->nextSibling)) {
			$node = $node->nextSibling;
			if (($node instanceof DOMElement)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Provides last-of-type.
	 */
	protected function isLastOfType(DOMElement $node) : bool
	{
		$type = $node->tagName;
		while (isset($node->nextSibling)) {
			$node = $node->nextSibling;
			if ( ! ($node instanceof DOMElement)) {
				continue;
			}
			if ($node->tagName === $type) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Provides :contains() as the original spec called for.
	 *
	 * This is an INEXACT match.
	 */
	protected function contains(DOMNode|TextContent $node, ?string $value) : bool
	{
		$text = $node->textContent;
		$value = Util::removeQuotes($value ?? '');

		return false !== stripos($text, $value);
	}

	/**
	 * Provides :contains-exactly QueryPath pseudoclass.
	 *
	 * This is an EXACT match.
	 */
	protected function containsExactly(DOMNode|TextContent $node, ?string $value) : bool
	{
		$text = $node->textContent;
		$value = Util::removeQuotes($value ?? '');

		return $text == $value;
	}

	/**
	 * Provides :has pseudoclass.
	 *
	 * @throws ParseException
	 */
	protected function has(DOMNode $node, ?string $selector) : bool
	{
		/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
		$splos = new SplObjectStorage();
		$splos->attach($node);
		$traverser = new \QueryPath\CSS\DOMTraverser($splos, true);
		$results = $traverser->find($selector ?? '')->matches();

		return count($results) > 0;
	}

	/**
	 * Provides :not pseudoclass.
	 *
	 * @throws ParseException
	 */
	protected function isNot(DOMNode $node, ?string $selector) : bool
	{
		return ! $this->has($node, $selector);
	}

	/**
	 * Get the relative position of a node in its sibling set.
	 */
	protected function nodePositionFromStart(DOMElement $node, bool $byType = false) : int
	{
		$i = 1;
		$tag = $node->tagName;
		while (isset($node->previousSibling)) {
			$node = $node->previousSibling;
			if (($node instanceof DOMElement) && ( ! $byType || $node->tagName === $tag)) {
				++$i;
			}
		}

		return $i;
	}

	/**
	 * Get the relative position of a node in its sibling set.
	 */
	protected function nodePositionFromEnd(DOMElement $node, bool $byType = false) : int
	{
		$i = 1;
		$tag = $node->tagName;
		while (isset($node->nextSibling)) {
			$node = $node->nextSibling;
			if (($node instanceof DOMElement) && ( ! $byType || $node->tagName === $tag)) {
				++$i;
			}
		}

		return $i;
	}

	/**
	 * Provides functionality for all "An+B" rules.
	 * Provides nth-child and also the functionality required for:.
	 *
	 *- nth-last-child
	 *- even
	 *- odd
	 *- first
	 *- last
	 *- eq
	 *- nth
	 *- nth-of-type
	 *- first-of-type
	 *- last-of-type
	 *- nth-last-of-type
	 *
	 * See also QueryPath::CSS::DOMTraverser::Util::parseAnB().
	 */
	protected function isNthChild(DOMElement $node, string|int|null $value, bool $reverse = false, bool $byType = false) : bool
	{
		[$groupSize, $elementInGroup] = Util::parseAnB($value);
		$parent = $node->parentNode;
		if (empty($parent)
			|| (0 === $groupSize && 0 === $elementInGroup)
			|| ($groupSize > 0 && $elementInGroup > $groupSize)
		) {
			return false;
		}

		// First we need to find the position of $node in other elements.
		if ($reverse) {
			$pos = $this->nodePositionFromEnd($node, $byType);
		} else {
			$pos = $this->nodePositionFromStart($node, $byType);
		}

		// If group size is 0, we just check to see if this
		// is the nth element:
		if (0 === $groupSize) {
			return $pos === $elementInGroup;
		}

		// Next, we normalize $elementInGroup
		if ($elementInGroup < 0) {
			$elementInGroup = $groupSize + $elementInGroup;
		}
		$prod = ($pos - $elementInGroup) / $groupSize;

		return is_int($prod) && $prod >= 0;
	}

	protected function isLocalLink(DOMElement $node) : bool
	{
		if ( ! $node->hasAttribute('href')) {
			return false;
		}
		$url = $node->getAttribute('href');
		$scheme = parse_url($url, PHP_URL_SCHEME);

		return empty($scheme) || 'file' === $scheme;
	}
}
