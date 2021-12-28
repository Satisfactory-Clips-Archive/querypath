<?php

declare(strict_types=1);
/** @file
 * Provide QueryPath with XSLT support using the PHP libxslt module.
 *
 * This is called 'QPXSL' instead of 'QPXSLT' in accordance with the name
 * of the PHP extension that provides libxslt support.
 *
 * You must have PHP XSL support for this to function.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license MIT
 *
 * @see     QueryPath::Extension
 * @see     QueryPath::ExtensionRegistry::extend()
 * @see     QPXSL
 * @see     QPXML
 */

namespace QueryPath\Extension;

use function assert;
use DOMDocument;
use DOMNode;
use Masterminds\HTML5;
use QueryPath\DOM;
use QueryPath\DOMQuery;
use QueryPath\Query;
use QueryPath\QueryPath;
use QueryPath\TextContent;
use SimpleXMLElement;
use SplObjectStorage;
use UnexpectedValueException;
use XSLTProcessor;

/**
 * Provide tools for running XSL Transformation (XSLT) on a document.
 *
 * This extension provides the {@link QPXSL::xslt()} function, which transforms
 * a source XML document into another XML document according to the rules in
 * an XSLT document.
 *
 * This QueryPath extension can be used as follows:
 * <code>
 * <?php
 * require 'QueryPath/QueryPath.php';
 * require 'QueryPath/Extension/QPXSL.php';
 *
 * qp('src.xml')->xslt('stylesheet.xml')->writeXML();
 * ?>
 *
 * This will transform src.xml according to the XSLT rules in
 * stylesheet.xml. The results are returned as a QueryPath object, which
 * is written to XML using {@link QueryPath::writeXML()}.
 * </code>
 *
 * @ingroup querypath_extensions
 */
class QPXSL implements \QueryPath\Extension
{
	public function __construct(
		protected Query $qp)
	{
	}

	/**
	 * Given an XSLT stylesheet, run a transformation.
	 *
	 * This will attempt to read the provided stylesheet and then
	 * execute it on the current source document.
	 *
	 * @param QueryPath|DOMQuery|DOM|SplObjectStorage<DOMNode|TextContent, mixed>|DOMDocument|DOMNode|HTML5|SimpleXMLElement|list<DOMNode>|string|null $style
	 *  This takes a QueryPath object or <em>any</em> of the types that the
	 *  {@link qp()} function can take
	 *
	 * @return DOMQuery
	 *  A DOMQuery object wrapping the transformed document. Note that this is a
	 *  <i>different</em> document than the original. As such, it has no history.
	 *  You cannot call {@link QueryPath::end()} to undo a transformation. (However,
	 *  the original source document will remain unchanged.)
	 */
	public function xslt(
		QueryPath|DOMQuery|DOM|SplObjectStorage|DOMDocument|DOMNode|HTML5|SimpleXMLElement|array|string|null $style
	) : DOMQuery {
		if ( ! ($style instanceof QueryPath)) {
			$style = QueryPath::with($style);
		}
		$top = $this->qp->top()->get(0);
		assert(
			($top instanceof DOMNode),
			new UnexpectedValueException('Top node not found!')
		);
		assert(
			($top->ownerDocument instanceof DOMDocument),
			new UnexpectedValueException('Top node ownerDocument not found!')
		);
		$sourceDoc = $top->ownerDocument;
		$styleDoc = $style->get(0)->ownerDocument ?? null;
		assert(
			($styleDoc instanceof DOMDocument),
			new UnexpectedValueException('$style ownerDocument not found!')
		);
		$processor = new XSLTProcessor();
		$processor->importStylesheet($styleDoc);

		return QueryPath::with($processor->transformToDoc($sourceDoc));
	}
}
