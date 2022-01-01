<?php

declare(strict_types=1);
/** @file
 * Traverse a DOM.
 */

namespace QueryPath\CSS;

use function count;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use QueryPath\CSS\DOMTraverser\PseudoClass;
use QueryPath\CSS\DOMTraverser\Util;
use QueryPath\TextContent;
use SplObjectStorage;
use const STDOUT;
use function strlen;
use const XML_ELEMENT_NODE;

/**
 * Traverse a DOM, finding matches to the selector.
 *
 * This traverses a DOMDocument and attempts to find
 * matches to the provided selector.
 *
 * \b How this works
 *
 * This performs a bottom-up search. On the first pass,
 * it attempts to find all of the matching elements for the
 * last simple selector in a selector.
 *
 * Subsequent passes attempt to eliminate matches from the
 * initial matching set.
 *
 * Example:
 *
 * Say we begin with the selector `foo.bar baz`. This is processed
 * as follows:
 *
 * - First, find all baz elements.
 * - Next, for any baz element that does not have foo as an ancestor,
 *   eliminate it from the matches.
 * - Finally, for those that have foo as an ancestor, does that foo
 *   also have a class baz? If not, it is removed from the matches.
 *
 * \b Extrapolation
 *
 * Partial simple selectors are almost always expanded to include an
 * element.
 *
 * Examples:
 *
 * - `:first` is expanded to `*:first`
 * - `.bar` is expanded to `*.bar`.
 * - `.outer .inner` is expanded to `*.outer *.inner`
 *
 * The exception is that IDs are sometimes not expanded, e.g.:
 *
 * - `#myElement` does not get expanded
 * - `#myElement .class` \i may be expanded to `*#myElement *.class`
 *   (which will obviously not perform well).
 */
class DOMTraverser implements Traverser
{
	/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
	protected SplObjectStorage $matches;
	protected ?DOMDocument $dom = null;
	protected bool $initialized;
	protected PseudoClass $psHandler;
	protected ?DOMNode $scopeNode = null;

	/**
	 * Build a new DOMTraverser.
	 *
	 * This requires a DOM-like object or collection of DOM nodes.
	 *
	 * @param SplObjectStorage<DOMNode|TextContent, mixed> $splos
	 */
	public function __construct(SplObjectStorage $splos, bool $initialized = false, DOMNode $scopeNode = null)
	{
		$this->psHandler = new PseudoClass();
		$this->initialized = $initialized;

		// Re-use the initial splos
		$this->matches = $splos;

		if (0 !== count($splos)) {
			$splos->rewind();
			$first = $splos->current();
			if ($first instanceof DOMDocument) {
				$this->dom = $first; //->documentElement;
			} else {
				$this->dom = $first->ownerDocument; //->documentElement;
			}

			$this->scopeNode = $scopeNode;
			if (empty($scopeNode)) {
				$this->scopeNode = $this->dom->documentElement ?? null;
			}
		}
	}

	/**
	 * Given a selector, find the matches in the given DOM.
	 *
	 * This is the main function for querying the DOM using a CSS
	 * selector.
	 *
	 * @param string $selector
	 *   The selector
	 *
	 * @throws ParseException
	 *
	 * @return DOMTraverser a list of matched
	 *   DOMNode objects
	 */
	public function find(string $selector) : DOMTraverser
	{
		// Setup
		$handler = new Selector();
		$parser = new Parser($selector, $handler);
		$parser->parse();

		//$selector = $handler->toArray();
		$found = $this->newMatches();
		foreach ($handler as $selectorGroup) {
			// Initialize matches if necessary.
			if ($this->initialized) {
				$candidates = $this->matches;
			} else {
				$candidates = $this->initialMatch($selectorGroup[0], $this->matches);
			}

			/** @var DOMElement $candidate */
			foreach ($candidates as $candidate) {
				if ($this->matchesSelector($candidate, $selectorGroup)) {
					$found->attach($candidate);
				}
			}
		}
		$this->setMatches($found);

		return $this;
	}

	/**
	 * @return SplObjectStorage<DOMNode|TextContent, mixed>
	 */
	public function matches() : SplObjectStorage
	{
		return $this->matches;
	}

	/**
	 * Check whether the given node matches the given selector.
	 *
	 * A selector is a group of one or more simple selectors combined
	 * by combinators. This determines if a given selector
	 * matches the given node.
	 *
	 * @attention
	 * Evaluation of selectors is done recursively. Thus the length
	 * of the selector is limited to the recursion depth allowed by
	 * the PHP configuration. This should only cause problems for
	 * absolutely huge selectors or for versions of PHP tuned to
	 * strictly limit recursion depth.
	 *
	 * @param DOMElement $node
	 *   The DOMNode to check
	 * @param array{0: SimpleSelector}|array<int, SimpleSelector> $selector
	 *
	 * @return bool
	 *   A boolean TRUE if the node matches, false otherwise
	 */
	public function matchesSelector(DOMElement $node, array $selector) : bool
	{
		return $this->matchesSimpleSelector($node, $selector, 0);
	}

	/**
	 * Performs a match check on a SimpleSelector.
	 *
	 * Where matchesSelector() does a check on an entire selector,
	 * this checks only a simple selector (plus an optional
	 * combinator).
	 *
	 * @param array<int, SimpleSelector> $selectors
	 *
	 * @throws NotImplementedException
	 *
	 * @return bool
	 *   A boolean TRUE if the node matches, false otherwise
	 */
	public function matchesSimpleSelector(DOMNode $node, array $selectors, int $index) : bool
	{
		if ( ! ($node instanceof DOMElement)) {
			return false;
		}

		$selector = $selectors[$index];
		// Note that this will short circuit as soon as one of these
		// returns FALSE.
		$result = $this->matchElement($node, $selector->element, $selector->ns)
			&& $this->matchAttributes($node, $selector->attributes)
			&& $this->matchId($node, $selector->id)
			&& $this->matchClasses($node, $selector->classes)
			&& $this->matchPseudoClasses($node, $selector->pseudoClasses)
			&& $this->matchPseudoElements($node, $selector->pseudoElements);

		$isNextRule = isset($selectors[++$index]);

		if ($isNextRule && $result) {
			$result = $this->combine($node, $selectors, $index);
		}

		return $result;
	}

	/**
	 * Combine the next selector with the given match
	 * using the next combinator.
	 *
	 * If the next selector is combined with another
	 * selector, that will be evaluated too, and so on.
	 * So if this function returns TRUE, it means that all
	 * child selectors are also matches.
	 *
	 * @param DOMElement $node
	 *   The DOMNode to test
	 * @param array<int, SimpleSelector> $selectors
	 *   The array of simple selectors
	 * @param int $index
	 *   The index of the current selector
	 *
	 * @return bool
	 *   TRUE if the next selector(s) match
	 */
	public function combine(DOMElement $node, array $selectors, int $index) : bool
	{
		$selector = $selectors[$index];
		switch ($selector->combinator) {
			case SimpleSelector::ADJACENT:
				return $this->combineAdjacent($node, $selectors, $index);
			case SimpleSelector::SIBLING:
				return $this->combineSibling($node, $selectors, $index);
			case SimpleSelector::DIRECT_DESCENDANT:
				return $this->combineDirectDescendant($node, $selectors, $index);
			case SimpleSelector::ANY_DESCENDANT:
				return $this->combineAnyDescendant($node, $selectors, $index);
			case SimpleSelector::ANOTHER_SELECTOR:
				// fprintf(STDOUT, "Next selector: %s\n", $selectors[$index]);
				return $this->matchesSimpleSelector($node, $selectors, $index);
		}

		return false;
	}

	/**
	 * Process an Adjacent Sibling.
	 *
	 * The spec does not indicate whether Adjacent should ignore non-Element
	 * nodes, so we choose to ignore them.
	 *
	 * @param DOMNode $node
	 *   A DOM Node
	 * @param array<int, SimpleSelector> $selectors
	 *   The selectors array
	 * @param int $index
	 *   The current index to the operative simple selector in the selectors
	 *   array
	 *
	 * @return bool
	 *   TRUE if the combination matches, FALSE otherwise
	 */
	public function combineAdjacent(DOMNode $node, array $selectors, int $index) : bool
	{
		while ( ! empty($node->previousSibling)) {
			$node = $node->previousSibling;
			if (XML_ELEMENT_NODE == $node->nodeType) {
				return $this->matchesSimpleSelector($node, $selectors, $index);
			}
		}

		return false;
	}

	/**
	 * Check all siblings.
	 *
	 * According to the spec, this only tests elements LEFT of the provided
	 * node.
	 *
	 * @param DOMNode $node
	 *   A DOM Node
	 * @param array<int, SimpleSelector> $selectors
	 *   The selectors array
	 * @param int $index
	 *   The current index to the operative simple selector in the selectors
	 *   array
	 *
	 * @return bool
	 *   TRUE if the combination matches, FALSE otherwise
	 */
	public function combineSibling(DOMNode $node, array $selectors, int $index) : bool
	{
		while ( ! empty($node->previousSibling)) {
			$node = $node->previousSibling;
			if (XML_ELEMENT_NODE == $node->nodeType && $this->matchesSimpleSelector($node, $selectors, $index)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Handle a Direct Descendant combination.
	 *
	 * Check whether the given node is a rightly-related descendant
	 * of its parent node.
	 *
	 * @param DOMNode $node
	 *   A DOM Node
	 * @param array<int, SimpleSelector> $selectors
	 *   The selectors array
	 * @param int $index
	 *   The current index to the operative simple selector in the selectors
	 *   array
	 *
	 * @return bool
	 *   TRUE if the combination matches, FALSE otherwise
	 */
	public function combineDirectDescendant(DOMNode $node, array $selectors, int $index) : bool
	{
		$parent = $node->parentNode;
		if (empty($parent)) {
			return false;
		}

		return $this->matchesSimpleSelector($parent, $selectors, $index);
	}

	/**
	 * Handle Any Descendant combinations.
	 *
	 * This checks to see if there are any matching routes from the
	 * selector beginning at the present node.
	 *
	 * @param DOMNode $node
	 *   A DOM Node
	 * @param array<int, SimpleSelector> $selectors
	 *   The selectors array
	 * @param int $index
	 *   The current index to the operative simple selector in the selectors
	 *   array
	 *
	 * @return bool
	 *   TRUE if the combination matches, FALSE otherwise
	 */
	public function combineAnyDescendant(DOMNode $node, array $selectors, int $index) : bool
	{
		while ( ! empty($node->parentNode)) {
			$node = $node->parentNode;

			// Catch case where element is child of something
			// else. This should really only happen with a
			// document element.
			if (XML_ELEMENT_NODE != $node->nodeType) {
				continue;
			}

			if ($this->matchesSimpleSelector($node, $selectors, $index)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Attach all nodes in a node list to the given \SplObjectStorage.
	 *
	 * @param SplObjectStorage<DOMNode|TextContent, mixed> $splos
	 */
	public function attachNodeList(DOMNodeList $nodeList, SplObjectStorage $splos) : void
	{
		foreach ($nodeList as $item) {
			$splos->attach($item);
		}
	}

	public function getDocument() : ?DOMDocument
	{
		return $this->dom;
	}

	/**
	 * Get the intial match set.
	 *
	 * This should only be executed when not working with
	 * an existing match set.
	 *
	 * @param SplObjectStorage<DOMNode|TextContent, mixed> $matches
	 */
	protected function initialMatch(SimpleSelector $selector, SplObjectStorage $matches) : SplObjectStorage
	{
		$element = $selector->element;

		// If no element is specified, we have to start with the
		// entire document.
		if (null === $element) {
			$element = '*';
		}

		// We try to do some optimization here to reduce the
		// number of matches to the bare minimum. This will
		// reduce the subsequent number of operations that
		// must be performed in the query.

		// Experimental: ID queries use XPath to match, since
		// this should give us only a single matched element
		// to work with.
		if ( ! empty($selector->id)) {
			$initialMatches = $this->initialMatchOnID($selector, $matches);
		} // If a namespace is set, find the namespace matches.
		elseif ( ! empty($selector->ns)) {
			$initialMatches = $this->initialMatchOnElementNS($selector, $matches);
		}
		// If the element is a wildcard, using class can
		// substantially reduce the number of elements that
		// we start with.
		elseif ('*' === $element && ! empty($selector->classes)) {
			$initialMatches = $this->initialMatchOnClasses($selector, $matches);
		} else {
			$initialMatches = $this->initialMatchOnElement($selector, $matches);
		}

		return $initialMatches;
	}

	/**
	 * Shortcut for finding initial match by ID.
	 *
	 * If the element is set to '*' and an ID is
	 * set, then this should be used to find by ID,
	 * which will drastically reduce the amount of
	 * comparison operations done in PHP.
	 *
	 * @param SplObjectStorage<DOMNode|TextContent, mixed> $matches
	 */
	protected function initialMatchOnID(SimpleSelector $selector, SplObjectStorage $matches) : SplObjectStorage
	{
		$id = $selector->id;
		$found = $this->newMatches();

		// Issue #145: DOMXPath will through an exception if the DOM is
		// not set.
		if ( ! ($this->dom instanceof DOMDocument)) {
			return $found;
		}
		$baseQuery = ".//*[@id='{$id}']";
		$xpath = new DOMXPath($this->dom);

		// Now we try to find any matching IDs.
		/** @var DOMElement $node */
		foreach ($matches as $node) {
			if ($node->getAttribute('id') === $id) {
				$found->attach($node);
			}

			$nl = $this->initialXpathQuery($xpath, $node, $baseQuery);
			$this->attachNodeList($nl, $found);
		}
		// Unset the ID selector.
		$selector->id = null;

		return $found;
	}

	/**
	 * Shortcut for setting the intial match.
	 *
	 * This shortcut should only be used when the initial
	 * element is '*' and there are classes set.
	 *
	 * In any other case, the element finding algo is
	 * faster and should be used instead.
	 *
	 * @param SplObjectStorage<DOMNode|TextContent, mixed> $matches
	 */
	protected function initialMatchOnClasses(SimpleSelector $selector, SplObjectStorage $matches) : SplObjectStorage
	{
		$found = $this->newMatches();

		// Issue #145: DOMXPath will through an exception if the DOM is
		// not set.
		if ( ! ($this->dom instanceof DOMDocument)) {
			return $found;
		}
		$baseQuery = './/*[@class]';
		$xpath = new DOMXPath($this->dom);

		// Now we try to find any matching IDs.
		/** @var DOMElement $node */
		foreach ($matches as $node) {
			// Refactor me!
			if ($node->hasAttribute('class')) {
				$intersect = array_intersect($selector->classes, explode(' ', $node->getAttribute('class')));
				if (count($intersect) === count($selector->classes)) {
					$found->attach($node);
				}
			}

			$nl = $this->initialXpathQuery($xpath, $node, $baseQuery);
			/** @var DOMElement $subNode */
			foreach ($nl as $subNode) {
				$classes = $subNode->getAttribute('class');
				$classArray = explode(' ', $classes);

				$intersect = array_intersect($selector->classes, $classArray);
				if (count($intersect) === count($selector->classes)) {
					$found->attach($subNode);
				}
			}
		}

		// Unset the classes selector.
		$selector->classes = [];

		return $found;
	}

	/**
	 * Shortcut for setting the initial match.
	 *
	 * @param SplObjectStorage<DOMNode|TextContent, mixed> $matches
	 *
	 * @return SplObjectStorage<DOMNode|TextContent, mixed>
	 */
	protected function initialMatchOnElement(SimpleSelector $selector, SplObjectStorage $matches) : SplObjectStorage
	{
		$element = $selector->element;
		if (null === $element) {
			$element = '*';
		}
		$found = $this->newMatches();
		foreach ($matches as $node) {
			if ( ! ($node instanceof DOMElement)) {
				continue;
			}
			// Capture the case where the initial element is the root element.
			if ($node->tagName === $element
				|| ('*' === $element && $node->parentNode instanceof DOMDocument)) {
				$found->attach($node);
			}
			$nl = $node->getElementsByTagName($element);
			$this->attachNodeList($nl, $found);
		}

		$selector->element = null;

		return $found;
	}

	/**
	 * Get elements and filter by namespace.
	 *
	 * @param SplObjectStorage<DOMNode|TextContent, mixed> $matches
	 */
	protected function initialMatchOnElementNS(SimpleSelector $selector, SplObjectStorage $matches) : SplObjectStorage
	{
		$ns = $selector->ns;

		$elements = $this->initialMatchOnElement($selector, $matches);

		// "any namespace" matches anything.
		if ('*' === $ns) {
			return $elements;
		}

		// Loop through and make a list of items that need to be filtered
		// out, then filter them. This is required b/c ObjectStorage iterates
		// wrongly when an item is detached in an access loop.
		$detach = [];
		foreach ($elements as $node) {
			if ( ! ($node instanceof DOMNode)) {
				continue;
			}
			// This lookup must be done PER NODE.
			$nsuri = $node->lookupNamespaceURI($ns ?? '');
			if (empty($nsuri) || $node->namespaceURI !== $nsuri) {
				$detach[] = $node;
			}
		}
		foreach ($detach as $rem) {
			$elements->detach($rem);
		}
		$selector->ns = null;

		return $elements;
	}

	/**
	 * Checks to see if the DOMNode matches the given element selector.
	 *
	 * This handles the following cases:
	 *
	 * - element (foo)
	 * - namespaced element (ns|foo)
	 * - namespaced wildcard (ns|*)
	 * - wildcard (* or *|*)
	 */
	protected function matchElement(DOMElement $node, ?string $element, string $ns = null) : bool
	{
		if (empty($element)) {
			return true;
		}

		// Handle namespace.
		if ( ! empty($ns) && '*' !== $ns) {
			// Check whether we have a matching NS URI.
			$nsuri = $node->lookupNamespaceURI($ns);
			if (empty($nsuri) || $node->namespaceURI !== $nsuri) {
				return false;
			}
		}

		// Compare local name to given element name.
		return '*' === $element || $node->localName === $element;
	}

	/**
	 * Get a list of ancestors to the present node.
	 *
	 * @return list<DOMNode>
	 */
	protected function ancestors(DOMNode $node) : array
	{
		$buffer = [];
		$parent = $node;
		while (($parent = $parent->parentNode) !== null) {
			$buffer[] = $parent;
		}

		return $buffer;
	}

	/**
	 * Check to see if DOMNode has all of the given attributes.
	 *
	 * This can handle namespaced attributes, including namespace
	 * wildcards.
	 *
	 * @param array{
	 *	name:string,
	 *	ns?:string,
	 *	op:int|null
	 * }[] $attributes
	 */
	protected function matchAttributes(DOMElement $node, array $attributes) : bool
	{
		if (empty($attributes)) {
			return true;
		}

		foreach ($attributes as $attr) {
			$val = $attr['value'] ?? null;

			// Namespaced attributes.
			if (isset($attr['ns']) && '*' !== $attr['ns']) {
				$nsuri = $node->lookupNamespaceURI($attr['ns']);
				if (empty($nsuri) || ! $node->hasAttributeNS($nsuri, $attr['name'])) {
					return false;
				}
				$matches = Util::matchesAttributeNS($node, $attr['name'], $nsuri, $val, $attr['op']);
			} elseif (isset($attr['ns']) && '*' === $attr['ns'] && $node->hasAttributes()) {
				// Cycle through all of the attributes in the node. Note that
				// these are DOMAttr objects.
				$matches = false;
				$name = $attr['name'];
				foreach ($node->attributes as $attrNode) {
					if ($attrNode->localName === $name) {
						$nsuri = $attrNode->namespaceURI;
						$matches = Util::matchesAttributeNS($node, $name, $nsuri, $val, $attr['op']);
					}
				}
			} // No namespace.
			else {
				$matches = Util::matchesAttribute($node, $attr['name'], $val, $attr['op']);
			}

			if ( ! $matches) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check that the given DOMNode has the given ID.
	 *
	 * @param $id
	 */
	protected function matchId(DOMElement $node, ?string $id) : bool
	{
		if (empty($id)) {
			return true;
		}

		return $node->hasAttribute('id') && $node->getAttribute('id') === $id;
	}

	/**
	 * Check that the given DOMNode has all of the given classes.
	 *
	 * @param string[] $classes
	 */
	protected function matchClasses(DOMElement $node, array $classes) : bool
	{
		if (empty($classes)) {
			return true;
		}

		if ( ! $node->hasAttribute('class')) {
			return false;
		}

		$eleClasses = preg_split('/\s+/', $node->getAttribute('class'));
		if (empty($eleClasses)) {
			return false;
		}

		// The intersection should match the given $classes.
		$missing = array_diff($classes, array_intersect($classes, $eleClasses));

		return 0 === count($missing);
	}

	/**
	 * @param array{name:string, value?:string|null}[] $pseudoClasses
	 *
	 * @throws NotImplementedException
	 * @throws ParseException
	 */
	protected function matchPseudoClasses(DOMElement $node, array $pseudoClasses) : bool
	{
		$ret = 1;
		foreach ($pseudoClasses as $pseudoClass) {
			$name = $pseudoClass['name'];
			// Avoid E_STRICT violation.
			$value = $pseudoClass['value'] ?? null;
			$ret &= (int) $this->psHandler->elementMatches($name, $node, $this->scopeNode, $value);
		}

		return (bool) $ret;
	}

	/**
	 * Test whether the given node matches the pseudoElements.
	 *
	 * If any pseudo-elements are passed, this will test to see
	 * <i>if conditions obtain that would allow the pseudo-element
	 * to be created</i>. This does not modify the match in any way.
	 *
	 * @param string[] $pseudoElements
	 *
	 * @throws NotImplementedException
	 */
	protected function matchPseudoElements(DOMElement $node, array $pseudoElements) : bool
	{
		if (empty($pseudoElements)) {
			return true;
		}

		foreach ($pseudoElements as $pse) {
			switch ($pse) {
				case 'first-line':
				case 'first-letter':
				case 'before':
				case 'after':
					return strlen($node->textContent) > 0;
				case 'selection':
					throw new \QueryPath\CSS\NotImplementedException("::$pse is not implemented.");
			}
		}

		return false;
	}

	/**
	 * @return SplObjectStorage<DOMNode|TextContent, mixed>
	 */
	protected function newMatches() : SplObjectStorage
	{
		return new SplObjectStorage();
	}

	/**
	 * Get the internal match set.
	 * Internal utility function.
	 *
	 * @return SplObjectStorage<DOMNode|TextContent, mixed>
	 */
	protected function getMatches() : SplObjectStorage
	{
		return $this->matches();
	}

	/**
	 * Set the internal match set.
	 *
	 * Internal utility function.
	 *
	 * @param SplObjectStorage<DOMNode|TextContent, mixed> $matches
	 */
	protected function setMatches(SplObjectStorage $matches) : void
	{
		$this->matches = $matches;
	}

	/**
	 * Internal xpath query.
	 *
	 * This is optimized for very specific use, and is not a general
	 * purpose function.
	 */
	private function initialXpathQuery(DOMXPath $xpath, DOMElement $node, string $query) : DOMNodeList
	{
		// This works around a bug in which the document element
		// does not correctly search with the $baseQuery.
		if (isset($this->dom) && $node->isSameNode($this->dom->documentElement)) {
			$query = mb_substr($query, 1);
		}

		return $xpath->query($query, $node);
	}
}
