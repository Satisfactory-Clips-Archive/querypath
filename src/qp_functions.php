<?php

declare(strict_types=1);
/**
 * @file
 *
 * QueryPath functions.
 *
 * This file holds the QueryPath functions, qp() and htmlqp().
 *
 * Usage:
 *
 * @code
 * <?php
 * require 'qp.php';
 *
 * qp($xml)->find('foo')->count();
 * ?>
 * @endcode
 */


use Masterminds\HTML5;
use QueryPath\DOM;
use QueryPath\DOMQuery;
use QueryPath\QueryPath;
use QueryPath\TextContent;

/** @addtogroup querypath_core Core API
 * Core classes and functions for QueryPath.
 *
 * These are the classes, objects, and functions that developers who use QueryPath
 * are likely to use. The qp() and htmlqp() functions are the best place to start,
 * while most of the frequently used methods are part of the QueryPath object.
 *
 * @param mixed|null $document
 * @param mixed|null $string
 */

/** @addtogroup querypath_util Utilities
 * Utility classes for QueryPath.
 *
 * These classes add important, but less-often used features to QueryPath. Some of
 * these are used transparently (QueryPathIterator). Others you can use directly in your
 * code (QueryPathEntities).
 */

/** @namespace QueryPath
 * The core classes that compose QueryPath.
 *
 * The QueryPath classes contain the brunt of the QueryPath code. If you are
 * interested in working with just the CSS engine, you may want to look at CssEventHandler,
 * which can be used without the rest of QueryPath. If you are interested in looking
 * carefully at QueryPath's implementation details, then the QueryPath class is where you
 * should begin. If you are interested in writing extensions, than you may want to look at
 * QueryPathExtension, and also at some of the simple extensions, such as QPXML.
 */

/**
 * Build a new Query Path.
 * This builds a new Query Path object. The new object can be used for
 * reading, search, and modifying a document.
 *
 * While it is permissible to directly create new instances of a QueryPath
 * implementation, it is not advised. Instead, you should use this function
 * as a factory.
 *
 * Example:
 *
 * @code
 * <?php
 * qp(); // New empty QueryPath
 * qp('path/to/file.xml'); // From a file
 * qp('<html><head></head><body></body></html>'); // From HTML or XML
 * qp(QueryPath::XHTML_STUB); // From a basic HTML document.
 * qp(QueryPath::XHTML_STUB, 'title'); // Create one from a basic HTML doc and position it at the title element.
 *
 * // Most of the time, methods are chained directly off of this call.
 * qp(QueryPath::XHTML_STUB, 'body')->append('<h1>Title</h1>')->addClass('body-class');
 * ?>
 * @endcode
 *
 * This function is used internally by QueryPath. Anything that modifies the
 * behavior of this function may also modify the behavior of common QueryPath
 * methods.
 *
 * <b>Types of documents that QueryPath can support</b>
 *
 *  qp() can take any of these as its first argument:
 *
 *  - A string of XML or HTML (See {@link XHTML_STUB})
 *  - A path on the file system or a URL
 *  - A {@link DOMDocument} object
 *  - A {@link SimpleXMLElement} object.
 *  - A {@link DOMNode} object.
 *  - An array of {@link DOMNode} objects (generally {@link DOMElement} nodes).
 *  - Another {@link QueryPath} object.
 *
 * Keep in mind that most features of QueryPath operate on elements. Other
 * sorts of DOMNodes might not work with all features.
 *
 * <b>Supported Options</b>
 *  - context: A stream context object. This is used to pass context info
 *    to the underlying file IO subsystem.
 *  - encoding: A valid character encoding, such as 'utf-8' or 'ISO-8859-1'.
 *    The default is system-dependant, typically UTF-8. Note that this is
 *    only used when creating new documents, not when reading existing content.
 *    (See convert_to_encoding below.)
 *  - parser_flags: An OR-combined set of parser flags. The flags supported
 *    by the DOMDocument PHP class are all supported here.
 *  - omit_xml_declaration: Boolean. If this is TRUE, then certain output
 *    methods (like {@link QueryPath::xml()}) will omit the XML declaration
 *    from the beginning of a document.
 *  - format_output: Boolean. If this is set to TRUE, QueryPath will format
 *    the HTML or XML output to make it more readible. If this is set to
 *    FALSE, QueryPath will minimize whitespace to keep the document smaller
 *    but harder to read.
 *  - replace_entities: Boolean. If this is TRUE, then any of the insertion
 *    functions (before(), append(), etc.) will replace named entities with
 *    their decimal equivalent, and will replace un-escaped ampersands with
 *    a numeric entity equivalent.
 *  - ignore_parser_warnings: Boolean. If this is TRUE, then E_WARNING messages
 *    generated by the XML parser will not cause QueryPath to throw an exception.
 *    This is useful when parsing
 *    badly mangled HTML, or when failure to find files should not result in
 *    an exception. By default, this is FALSE -- that is, parsing warnings and
 *    IO warnings throw exceptions.
 *  - convert_to_encoding: Use the MB library to convert the document to the
 *    named encoding before parsing. This is useful for old HTML (set it to
 *    iso-8859-1 for best results). If this is not supplied, no character set
 *    conversion will be performed. See {@link mb_convert_encoding()}.
 *    (QueryPath 1.3 and later)
 *  - convert_from_encoding: If 'convert_to_encoding' is set, this option can be
 *    used to explicitly define what character set the source document is using.
 *    By default, QueryPath will allow the MB library to guess the encoding.
 *    (QueryPath 1.3 and later)
 *  - strip_low_ascii: If this is set to TRUE then markup will have all low ASCII
 *    characters (<32) stripped out before parsing. This is good in cases where
 *    icky HTML has (illegal) low characters in the document.
 *  - use_parser: If 'xml', Parse the document as XML. If 'html', parse the
 *    document as HTML. Note that the XML parser is very strict, while the
 *    HTML parser is more lenient, but does enforce some of the DTD/Schema.
 *    <i>By default, QueryPath autodetects the type.</i>
 *  - escape_xhtml_js_css_sections: XHTML needs script and css sections to be
 *    escaped. Yet older readers do not handle CDATA sections, and comments do not
 *    work properly (for numerous reasons). By default, QueryPath's *XHTML methods
 *    will wrap a script body with a CDATA declaration inside of C-style comments.
 *    If you want to change this, you can set this option with one of the
 *    JS_CSS_ESCAPE_* constants, or you can write your own.
 *  - QueryPath_class: (ADVANCED) Use this to set the actual classname that
 *    {@link qp()} loads as a QueryPath instance. It is assumed that the
 *    class is either {@link QueryPath} or a subclass thereof. See the test
 *    cases for an example.
 *
 * @ingroup querypath_core
 *
 * @param DOMQuery|DOM|SplObjectStorage<DOMNode|TextContent, mixed>|DOMDocument|DOMNode|HTML5|SimpleXMLElement|list<DOMNode>|string|null $document
 *  A document in one of the forms listed above
 * @param string|null $string
 *  A CSS 3 selector
 * @param array{
 *	QueryPath_class?:class-string<DOMQuery>
 * } $options
 *  An associative array of options. Currently supported options are listed above.
 *
 * @return \QueryPath\DOMQuery
 *  Or possibly another QueryPath-like object if you overrode QueryPath_class
 */
function qp(
	DOMQuery|DOM|SplObjectStorage|DOMDocument|DOMNode|HTML5|SimpleXMLElement|array|string|null $document = null,
	string $string = null,
	array $options = []
) : \QueryPath\DOMQuery
{
	return QueryPath::with($document, $string, $options);
}

/**
 * A special-purpose version of {@link qp()} designed specifically for HTML.
 *
 * XHTML (if valid) can be easily parsed by {@link qp()} with no problems. However,
 * because of the way that libxml handles HTML, there are several common steps that
 * need to be taken to reliably parse non-XML HTML documents. This function is
 * a convenience tool for configuring QueryPath to parse HTML.
 *
 * The following options are automatically set unless overridden:
 *  - ignore_parser_warnings: TRUE
 *  - convert_to_encoding: ISO-8859-1 (the best for the HTML parser).
 *  - convert_from_encoding: auto (autodetect encoding)
 *  - use_parser: html
 *
 * Parser warning messages are also suppressed, so if the parser emits a warning,
 * the application will not be notified. This is equivalent to
 * calling @code@qp()@endcode.
 *
 * Warning: Character set conversions will only work if the Multi-Byte (mb) library
 * is installed and enabled. This is usually enabled, but not always.
 *
 * @ingroup querypath_core
 *
 * @see     qp()
 *
 * @param DOMQuery|DOM|SplObjectStorage<DOMNode|TextContent, mixed>|DOMDocument|DOMNode|HTML5|SimpleXMLElement|list<DOMNode>|string|null $document
 * @param string|null $selector
 * @param array{
 *	QueryPath_class?:class-string<DOMQuery>
 * } $options
 */
function htmlqp(
	DOMQuery|DOM|SplObjectStorage|DOMDocument|DOMNode|HTML5|SimpleXMLElement|array|string|null $document = null,
	string $selector = null,
	array $options = []
) : \QueryPath\DOMQuery
{
	return QueryPath::withHTML($document, $selector, $options);
}

/**
 * Parse HTML5 documents.
 *
 * This uses HTML5-PHP to parse the document. In actuality, this parser does
 * a fine job with pre-HTML5 documents in most cases, though really old HTML
 * (like 2.0) may have some substantial quirks.
 *
 * <b>Supported Options</b>
 * Any options supported by HTML5-PHP are allowed here. Additionally, the
 * following options have meaning to QueryPath.
 * - QueryPath_class
 *
 * @param DOMQuery|DOM|SplObjectStorage<DOMNode|TextContent, mixed>|DOMDocument|DOMNode|HTML5|SimpleXMLElement|list<DOMNode>|string|null $document
 * @param string|null $selector
 *   A CSS3 selector
 * @param array{
 *	QueryPath_class?:class-string<DOMQuery>
 * } $options
 *   An associative array of options, which is passed on into HTML5-PHP. Note
 *   that the standard QueryPath options may be ignored for this function,
 *   since it uses a different parser.
 */
function html5qp(
	DOMQuery|DOM|SplObjectStorage|DOMDocument|DOMNode|HTML5|SimpleXMLElement|array|string|null $document = null,
	string $selector = null,
	array $options = []
) : \QueryPath\DOMQuery
{
	return QueryPath::withHTML5($document, $selector, $options);
}
