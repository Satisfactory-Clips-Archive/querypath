<?php

declare(strict_types=1);

namespace QueryPath;

use DOMDocument;
use DOMNode;
use Masterminds\HTML5;
use SimpleXMLElement;
use SplObjectStorage;

/**
 * Interface Query.
 *
 * @method after($data)
 * @method before($data)
 *
 * @psalm-require-implements DOMQuery
 */
interface Query
{
	/**
	 * @param DOM|SplObjectStorage<DOMNode|TextContent, mixed>|DOMDocument|DOMNode|HTML5|SimpleXMLElement|list<DOMNode>|string|null $document
	 *   A document-like object
	 * @param string $string
	 *   A CSS 3 Selector
	 * @param array{
	 *	parser_flags?: int|null,
	 *	omit_xml_declaration?: bool,
	 *	replace_entities?: bool,
	 *	exception_level?: int,
	 *	ignore_parser_warnings?: bool,
	 *	escape_xhtml_js_css_sections?: string,
	 *	convert_from_encoding?: string,
	 *	convert_to_encoding?: string
	 * } $options
	 */
	public function __construct(DOM|SplObjectStorage|DOMDocument|DOMNode|HTML5|SimpleXMLElement|array|string|null $document = null, $string = null, array $options = []);

	public function find(string $selector) : DOMQuery;

	public function top(string $selector = null) : DOMQuery;

	public function next(string $selector = null) : DOMQuery;

	public function prev(string $selector = null) : DOMQuery;

	public function siblings(string $selector = null) : DOMQuery;

	public function parent(string $selector = null) : DOMQuery;

	public function children(string $selector = null) : DOMQuery;
}
