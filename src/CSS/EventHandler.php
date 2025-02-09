<?php

declare(strict_types=1);
/** @file
 * CSS selector parsing classes.
 *
 * This file contains the tools necessary for parsing CSS 3 selectors.
 * In the future it may be expanded to handle all of CSS 3.
 *
 * The parser contained herein is has an event-based API. Implementors should
 * begin by implementing the {@link EventHandler} interface. For an example
 * of how this is done, see {@link EventHandler.php}.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license MIT
 */

namespace QueryPath\CSS;

/** @addtogroup querypath_css CSS Parsing
 * QueryPath includes a CSS 3 Selector parser.
 *
 * Typically the parser is not accessed directly. Most developers will use it indirectly from
 * qp(), htmlqp(), or one of the methods on a QueryPath object.
 *
 * This parser is modular and is not tied to QueryPath, so you can use it in your
 * own (non-QueryPath) projects if you wish. To dive in, start with EventHandler, the
 * event interface that works like a SAX API for CSS selectors. If you want to check out
 * the details, check out the parser (QueryPath::CSS::Parser),  scanner
 * (QueryPath::CSS::Scanner), and token list (QueryPath::CSS::Token).
 */

/**
 * An event handler for handling CSS 3 Selector parsing.
 *
 * This provides a standard interface for CSS 3 Selector event handling. As the
 * parser parses a selector, it will fire events. Implementations of EventHandler
 * can then handle the events.
 *
 * This library is inspired by the SAX2 API for parsing XML. Each component of a
 * selector fires an event, passing the necessary data on to the event handler.
 *
 * @ingroup querypath_css
 */
interface EventHandler
{
	/** The is-exactly (=) operator. */
	public const IS_EXACTLY = 0; // =
	/** The contains-with-space operator (~=). */
	public const CONTAINS_WITH_SPACE = 1; // ~=
	/** The contains-with-hyphen operator (!=). */
	public const CONTAINS_WITH_HYPHEN = 2; // |=
	/** The contains-in-string operator (*=). */
	public const CONTAINS_IN_STRING = 3; // *=
	/** The begins-with operator (^=). */
	public const BEGINS_WITH = 4; // ^=
	/** The ends-with operator ($=). */
	public const ENDS_WITH = 5; // $=
	/** The any-element operator (*). */
	public const ANY_ELEMENT = '*';

	/**
	 * This event is fired when a CSS ID is encountered.
	 * An ID begins with an octothorp: #name.
	 *
	 * @param string $id
	 *  The ID passed in
	 */
	public function elementID(string $id) : void; // #name

	/**
	 * Handle an element name.
	 * Example: name.
	 *
	 * @param string $name
	 *  The name of the element
	 */
	public function element(string $name) : void; // name

	/**
	 * Handle a namespaced element name.
	 * example: namespace|name.
	 *
	 * @param string $name
	 *  The tag name
	 * @param string|null $namespace
	 *  The namespace identifier (Not the URI)
	 */
	public function elementNS(string $name, string $namespace = null) : void;

	/**
	 * Handle an any-element (*) operator.
	 * Example: *.
	 */
	public function anyElement() : void; // *

	/**
	 * Handle an any-element operator that is constrained to a namespace.
	 * Example: ns|*.
	 *
	 * @param string $ns
	 *  The namespace identifier (not the URI)
	 */
	public function anyElementInNS(string $ns) : void; // ns|*

	/**
	 * Handle a CSS class selector.
	 * Example: .name.
	 *
	 * @param string $name
	 *  The name of the class
	 */
	public function elementClass(string $name) : void; // .name

	/**
	 * Handle an attribute selector.
	 * Example: [name=attr]
	 * Example: [name~=attr].
	 *
	 * @param string $name
	 *  The attribute name
	 * @param string $value
	 *  The value of the attribute, if given
	 * @param int|null $operation
	 *  The operation to be used for matching. See {@link EventHandler}
	 *  constants for a list of supported operations.
	 */
	public function attribute(string $name, string $value = null, ?int $operation = EventHandler::IS_EXACTLY) : void; // [name=attr]

	/**
	 * Handle an attribute selector bound to a specific namespace.
	 * Example: [ns|name=attr]
	 * Example: [ns|name~=attr].
	 *
	 * @param string $name
	 *  The attribute name
	 * @param string $ns
	 *  The namespace identifier (not the URI)
	 * @param string $value
	 *  The value of the attribute, if given
	 * @param int|null $operation
	 *  The operation to be used for matching. See {@link EventHandler}
	 *  constants for a list of supported operations.
	 */
	public function attributeNS(string $name, string $ns, string $value = null, ?int $operation = EventHandler::IS_EXACTLY) : void;

	/**
	 * Handle a pseudo-class.
	 * Example: :name(value).
	 *
	 * @param string $name
	 *  The pseudo-class name
	 * @param string|null $value
	 *  The value, if one is found
	 */
	public function pseudoClass(string $name, string $value = null) : void; //:name(value)

	/**
	 * Handle a pseudo-element.
	 * Example: ::name.
	 *
	 * @param string $name
	 *  The pseudo-element name
	 */
	public function pseudoElement(string $name) : void; // ::name

	/**
	 * Handle a direct descendant combinator.
	 * Example: >.
	 */
	public function directDescendant() : void; // >

	/**
	 * Handle a adjacent combinator.
	 * Example: +.
	 */
	public function adjacent() : void; // +

	/**
	 * Handle an another-selector combinator.
	 * Example: ,.
	 */
	public function anotherSelector() : void; // ,

	/**
	 * Handle a sibling combinator.
	 * Example: ~.
	 */
	public function sibling() : void; // ~ combinator

	/**
	 * Handle an any-descendant combinator.
	 * Example: ' '.
	 */
	public function anyDescendant() : void; // ' ' (space) operator.
}
