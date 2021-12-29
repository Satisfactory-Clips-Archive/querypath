<?php

declare(strict_types=1);
/** @file
 * XML extensions. See QPXML.
 */

namespace QueryPath\Extension;

use function assert;
use function in_array;
use DOMDocument;
use DOMNode;
use DOMElement;
use QueryPath\DOMQuery;
use QueryPath\Extension;
use QueryPath\Query;
use QueryPath\QueryPath;
use UnexpectedValueException;
use const XML_CDATA_SECTION_NODE;
use const XML_COMMENT_NODE;
use const XML_PI_NODE;

/**
 * Provide QueryPath with additional XML tools.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @author  Xander Guzman <theshadow@shadowpedia.info>
 * @license MIT
 *
 * @see     QueryPath::Extension
 * @see     QueryPath::ExtensionRegistry::extend()
 * @see     QPXML
 * @ingroup querypath_extensions
 */
class QPXML implements Extension
{
	public function __construct(
		protected DOMQuery $qp
	) {
	}

	public function schema(string $file) : void
	{
		$doc = $this->qp->branch()->top()->get(0)->ownerDocument ?? null;
		assert(
			($doc instanceof DOMDocument),
			new UnexpectedValueException(
				'ownerDocument missing!'
			)
		);

		if ( ! $doc->schemaValidate($file)) {
			throw new \QueryPath\Exception('Document did not validate against the schema.');
		}
	}

	/**
	 * Get or set a CDATA section.
	 *
	 * If this is given text, it will create a CDATA section in each matched element,
	 * setting that item's value to $text.
	 *
	 * If no parameter is passed in, this will return the first CDATA section that it
	 * finds in the matched elements.
	 *
	 * @template T as string|null
	 *
	 * @param T $text
	 *  The text data to insert into the current matches. If this is NULL, then the first
	 *  CDATA will be returned.
	 *
	 * @return (T is string ? DOMQuery : string|null)
	 *  If $text is not NULL, this will return a {@link QueryPath}. Otherwise, it will
	 *  return a string. If no CDATA is found, this will return NULL.
	 *
	 * @see comment()
	 * @see QueryPath::text()
	 * @see QueryPath::html()
	 */
	public function cdata(string $text = null) : DOMQuery|string|null
	{
		if (isset($text)) {
			// Add this text as CDATA in the current elements.
			foreach ($this->qp->get() as $element) {
				assert(
					($element instanceof DOMNode),
					new UnexpectedValueException(
						'Element must be an instance of DOMNode!'
					)
				);
				assert(
					($element->ownerDocument instanceof DOMDocument),
					new UnexpectedValueException(
						'ownerDocument missing!'
					)
				);
				$cdata = $element->ownerDocument->createCDATASection($text);
				$element->appendChild($cdata);
			}

			return $this->qp;
		}

		// Look for CDATA sections.
		/** @var DOMNode */
		foreach ($this->qp->get() as $ele) {
			foreach ($ele->childNodes as $node) {
				if (XML_CDATA_SECTION_NODE === $node->nodeType) {
					// Return first match.
					return $node->textContent;
				}
			}
		}

		// Nothing found
		return null;
	}

	/**
	 * Get or set a comment.
	 *
	 * This function is used to get or set comments in an XML or HTML document.
	 * If a $text value is passed in (and is not NULL), then this will add a comment
	 * (with the value $text) to every match in the set.
	 *
	 * If no text is passed in, this will return the first comment in the set of matches.
	 * If no comments are found, NULL will be returned.
	 *
	 * @param string $text
	 *  The text of the comment. If set, a new comment will be created in every item
	 *  wrapped by the current {@link QueryPath}.
	 *
	 * @return mixed
	 *  If $text is set, this will return a {@link QueryPath}. If no text is set, this
	 *  will search for a comment and attempt to return the string value of the first
	 *  comment it finds. If no comment is found, NULL will be returned.
	 *
	 * @see cdata()
	 */
	public function comment($text = null)
	{
		if (isset($text)) {
			/** @var DOMNode */
			foreach ($this->qp->get() as $element) {
				assert(
					($element->ownerDocument instanceof DOMDocument),
					new UnexpectedValueException(
						'ownerDocument missing!'
					)
				);
				$comment = $element->ownerDocument->createComment($text);
				$element->appendChild($comment);
			}

			return $this->qp;
		}
		/** @var DOMNode */
		foreach ($this->qp->get() as $ele) {
			foreach ($ele->childNodes as $node) {
				if (XML_COMMENT_NODE == $node->nodeType) {
					// Return first match.
					return $node->textContent;
				}
			}
		}
	}

	/**
	 * Get or set a processor instruction.
	 *
	 * @template T as string|null
	 *
	 * @param T $text
	 *
	 * @return (T is string ? DOMQuery : string|null)
	 */
	public function pi(string $prefix = null, string $text = null) : DOMQuery|string|null
	{
		if (isset($text)) {
			/** @var DOMNode */
			foreach ($this->qp->get() as $element) {
				assert(
					($element->ownerDocument instanceof DOMDocument),
					new UnexpectedValueException(
						'ownerDocument missing!'
					)
				);
				$comment = $element->ownerDocument->createProcessingInstruction($prefix ?? '', $text);
				$element->appendChild($comment);
			}

			return $this->qp;
		}
		/** @var DOMNode */
		foreach ($this->qp->get() as $ele) {
			foreach ($ele->childNodes as $node) {
				if (XML_PI_NODE == $node->nodeType) {
					if (isset($prefix)) {
						if ( ! ($node instanceof DOMElement)) {
							continue;
						}
						if ($node->tagName == $prefix) {
							return $node->textContent;
						}
					} else {
						// Return first match.
						return $node->textContent;
					}
				}
			} // foreach
		} // foreach

		return null;
	}

	public function toXml() : string
	{
		return $this->qp->document()->saveXml();
	}

	/**
	 * Create a NIL element.
	 */
	public function createNilElement(string $text, string $value) : DOMQuery
	{
		$value = ($value) ? 'true' : 'false';
		$element = $this->createElement($text);
		$element->attr('xsi:nil', $value);

		return $element;
	}

	/**
	 * Create an element with the given namespace.
	 *
	 * @param string|null $nsUri
	 *   The namespace URI for the given element
	 */
	public function createElement(string $text, string $nsUri = null) : DOMQuery
	{
		foreach ($this->qp->get() as $element) {
			assert(
				($element->ownerDocument instanceof DOMDocument),
				new UnexpectedValueException(
					'ownerDocument not found!'
				)
			);
			if (null === $nsUri && false !== strpos($text, ':')) {
				$text_parts = explode(':', $text);
				$ns = array_shift($text_parts);
				$nsUri = $element->ownerDocument->lookupNamespaceURI($ns);
			}
			if (null !== $nsUri) {
				$node = $element->ownerDocument->createElementNS(
						$nsUri,
						$text
					);
			} else {
				$node = $element->ownerDocument->createElement($text);
			}

			return QueryPath::with($node);
		}

		return new DOMQuery();
	}

	/**
	 * Append an element.
	 */
	public function appendElement(string $text) : DOMQuery
	{
		assert(
			in_array(self::class, class_uses($this->qp), true),
			new UnexpectedValueException(
				'Query implementation does not have QPXML extension!'
			)
		);

		foreach ($this->qp->get() as $element) {
			/** @var DOMQuery */
			$node = $this->qp->createElement($text);
			QueryPath::with($element)->append($node);
		}

		return $this->qp;
	}
}
