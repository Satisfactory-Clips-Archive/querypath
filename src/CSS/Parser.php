<?php

declare(strict_types=1);
/**
 * @file
 *
 * The CSS parser
 */

namespace QueryPath\CSS;

use function in_array;
use QueryPath\Exception;

/**
 * Parse a CSS selector.
 *
 * In CSS, a selector is used to identify which element or elements
 * in a DOM are being selected for the application of a particular style.
 * Effectively, selectors function as a query language for a structured
 * document -- almost always HTML or XML.
 *
 * This class provides an event-based parser for CSS selectors. It can be
 * used, for example, as a basis for writing a DOM query engine based on
 * CSS.
 *
 * @ingroup querypath_css
 */
class Parser
{
	protected Scanner $scanner;
	protected EventHandler $handler;

	private bool $strict = false;

	/**
	 * Construct a new CSS parser object. This will attempt to
	 * parse the string as a CSS selector. As it parses, it will
	 * send events to the EventHandler implementation.
	 */
	public function __construct(string $string, EventHandler $handler)
	{
		$is = new InputStream($string);
		$this->scanner = new Scanner($is);
		$this->handler = $handler;
	}

	/**
	 * Parse the selector.
	 *
	 * This begins an event-based parsing process that will
	 * fire events as the selector is handled. A EventHandler
	 * implementation will be responsible for handling the events.
	 *
	 * @throws Exception
	 * @throws ParseException
	 */
	public function parse() : void
	{
		$this->getScanner()->nextToken();

		while (false !== $this->getScanner()->token) {
			// Primitive recursion detection.
			$position = $this->getScanner()->position();

			$this->selector();

			$finalPosition = $this->getScanner()->position();
			if (false !== $this->getScanner()->token && $finalPosition === $position) {
				// If we get here, then the scanner did not pop a single character
				// off of the input stream during a full run of the parser, which
				// means that the current input does not match any recognizable
				// pattern.
				throw new ParseException('CSS selector is not well formed.');
			}
		}
	}

	public function getScanner() : Scanner
	{
		return $this->scanner;
	}

	/**
	 * Handle an entire CSS selector.
	 *
	 * @throws Exception
	 * @throws ParseException
	 */
	private function selector() : void
	{
		$this->consumeWhitespace(); // Remove leading whitespace
		$this->simpleSelectors();
		$this->combinator();
	}

	/**
	 * Consume whitespace and return a count of the number of whitespace consumed.
	 *
	 * @throws \QueryPath\CSS\ParseException
	 * @throws Exception
	 */
	private function consumeWhitespace() : int
	{
		$white = 0;
		while (Token::WHITE === $this->getScanner()->token) {
			$this->getScanner()->nextToken();
			++$white;
		}

		return $white;
	}

	/**
	 * Handle one of the five combinators: '>', '+', ' ', '~', and ','.
	 * This will call the appropriate event handlers.
	 *
	 * @see EventHandler::directDescendant(),
	 * @see EventHandler::adjacent(),
	 * @see EventHandler::anyDescendant(),
	 * @see EventHandler::anotherSelector().
	 *
	 * @throws \QueryPath\Exception
	 * @throws ParseException
	 */
	private function combinator() : void
	{
		/*
		 * Problem: ' ' and ' > ' are both valid combinators.
		 * So we have to track whitespace consumption to see
		 * if we are hitting the ' ' combinator or if the
		 * selector just has whitespace padding another combinator.
		 */

		// Flag to indicate that post-checks need doing
		$inCombinator = false;
		$white = $this->consumeWhitespace();
		$t = $this->getScanner()->token;

		if (Token::RANGLE === $t) {
			$this->handler->directDescendant();
			$this->getScanner()->nextToken();
			$inCombinator = true;
		//$this->simpleSelectors();
		} elseif (Token::PLUS === $t) {
			$this->handler->adjacent();
			$this->getScanner()->nextToken();
			$inCombinator = true;
		//$this->simpleSelectors();
		} elseif (Token::COMMA === $t) {
			$this->handler->anotherSelector();
			$this->getScanner()->nextToken();
			$inCombinator = true;
		//$this->getScanner()->selectors();
		} elseif (Token::TILDE === $t) {
			$this->handler->sibling();
			$this->getScanner()->nextToken();
			$inCombinator = true;
		}

		// Check that we don't get two combinators in a row.
		if ($inCombinator) {
			$this->consumeWhitespace();
			if ($this->isCombinator($this->getScanner()->token)) {
				throw new ParseException('Illegal combinator: Cannot have two combinators in sequence.');
			}
		} // Check to see if we have whitespace combinator:
		elseif ($white > 0) {
			$this->handler->anyDescendant();
		}
	}

	/**
	 * Check if the token is a combinator.
	 *
	 * @psalm-assert-if-true Token::PLUS|Token::RANGLE|Token::COMMA|Token::TILDE $tok
	 */
	private function isCombinator(int|false|null $tok) : bool
	{
		return in_array($tok, [Token::PLUS, Token::RANGLE, Token::COMMA, Token::TILDE], true);
	}

	/**
	 * Handle a simple selector.
	 *
	 * @throws ParseException
	 */
	private function simpleSelectors() : void
	{
		$this->allElements();
		$this->elementName();
		$this->elementClass();
		$this->elementID();
		$this->pseudoClass();
		$this->attribute();
	}

	/**
	 * Handles CSS ID selectors.
	 * This will call EventHandler::elementID().
	 *
	 * @throws \QueryPath\CSS\ParseException
	 * @throws Exception
	 */
	private function elementID() : void
	{
		if (Token::OCTO === $this->getScanner()->token) {
			$this->getScanner()->nextToken();
			if (Token::CHAR !== $this->getScanner()->token) {
				throw new ParseException('Expected string after #');
			}
			$id = $this->getScanner()->getNameString();
			$this->handler->elementID($id);
		}
	}

	/**
	 * Handles CSS class selectors.
	 * This will call the EventHandler::elementClass() method.
	 */
	private function elementClass() : void
	{
		if (Token::DOT == $this->getScanner()->token) {
			$this->getScanner()->nextToken();
			$this->consumeWhitespace(); // We're very fault tolerent. This should prob through error.
			$cssClass = $this->getScanner()->getNameString();
			$this->handler->elementClass($cssClass);
		}
	}

	/**
	 * Handle a pseudo-class and pseudo-element.
	 *
	 * CSS 3 selectors support separate pseudo-elements, using :: instead
	 * of : for separator. This is now supported, and calls the pseudoElement
	 * handler, EventHandler::pseudoElement().
	 *
	 * This will call EventHandler::pseudoClass() when a
	 * pseudo-class is parsed.
	 *
	 * @param mixed $restricted
	 *
	 * @throws Exception
	 * @throws ParseException
	 */
	private function pseudoClass($restricted = false) : void
	{
		if (Token::COLON === $this->getScanner()->token) {
			// Check for CSS 3 pseudo element:
			$isPseudoElement = false;
			if (Token::COLON === $this->getScanner()->nextToken()) {
				$isPseudoElement = true;
				$this->getScanner()->nextToken();
			}

			$name = $this->getScanner()->getNameString();
			if ($restricted && 'not' === $name) {
				throw new ParseException("The 'not' pseudo-class is illegal in this context.");
			}

			$value = null;
			if (Token::LPAREN === $this->getScanner()->token) {
				if ($isPseudoElement) {
					throw new ParseException('Illegal left paren. Pseudo-Element cannot have arguments.');
				}
				$value = $this->pseudoClassValue();
			}

			// FIXME: This should throw errors when pseudo element has values.
			if ($isPseudoElement) {
				if ($restricted) {
					throw new ParseException('Pseudo-Elements are illegal in this context.');
				}
				$this->handler->pseudoElement($name);
				$this->consumeWhitespace();

				// Per the spec, pseudo-elements must be the last items in a selector, so we
				// check to make sure that we are either at the end of the stream or that a
				// new selector is starting. Only one pseudo-element is allowed per selector.
				if (false !== $this->getScanner()->token && Token::COMMA !== $this->getScanner()->token) {
					throw new ParseException('A Pseudo-Element must be the last item in a selector.');
				}
			} else {
				$this->handler->pseudoClass($name, $value);
			}
		}
	}

	/**
	 * Get the value of a pseudo-classes.
	 *
	 * @return string|null
	 *  Returns the value found from a pseudo-class
	 *
	 * @todo Pseudoclasses can be passed pseudo-elements and
	 *  other pseudo-classes as values, which means :pseudo(::pseudo)
	 *  is legal.
	 */
	private function pseudoClassValue() : ?string
	{
		if (Token::LPAREN === $this->getScanner()->token) {
			$buf = $this->getScanner()->getPseudoClassString();

			return $buf;
		}

		return null;
	}

	/**
	 * Handle element names.
	 * This will call the EventHandler::elementName().
	 *
	 * This handles:
	 * <code>
	 *  name (EventHandler::element())
	 *  |name (EventHandler::element())
	 *  ns|name (EventHandler::elementNS())
	 *  ns|* (EventHandler::elementNS())
	 * </code>
	 */
	private function elementName() : void
	{
		if (Token::PIPE === $this->getScanner()->token) {
			// We have '|name', which is equiv to 'name'
			$this->getScanner()->nextToken();
			$this->consumeWhitespace();
			$elementName = $this->getScanner()->getNameString();
			$this->handler->element($elementName);
		} elseif (Token::CHAR === $this->getScanner()->token) {
			$elementName = $this->getScanner()->getNameString();
			if (Token::PIPE == $this->getScanner()->token) {
				// Get ns|name
				$elementNS = $elementName;
				$this->getScanner()->nextToken();
				$this->consumeWhitespace();
				if (Token::STAR === $this->getScanner()->token) {
					// We have ns|*
					$this->handler->anyElementInNS($elementNS);
					$this->getScanner()->nextToken();
				} elseif (Token::CHAR !== $this->getScanner()->token) {
					$this->throwError(Token::CHAR, $this->getScanner()->token);
				} else {
					$elementName = $this->getScanner()->getNameString();
					// We have ns|name
					$this->handler->elementNS($elementName, $elementNS);
				}
			} else {
				$this->handler->element($elementName);
			}
		}
	}

	/**
	 * Check for all elements designators. Due to the new CSS 3 namespace
	 * support, this is slightly more complicated, now, as it handles
	 * the *|name and *|* cases as well as *.
	 *
	 * Calls EventHandler::anyElement() or EventHandler::elementName().
	 */
	private function allElements() : void
	{
		if (Token::STAR === $this->getScanner()->token) {
			$this->getScanner()->nextToken();
			if (Token::PIPE === $this->getScanner()->token) {
				$this->getScanner()->nextToken();
				if (Token::STAR === $this->getScanner()->token) {
					// We got *|*. According to spec, this requires
					// that the element has a namespace, so we pass it on
					// to the handler:
					$this->getScanner()->nextToken();
					$this->handler->anyElementInNS('*');
				} else {
					// We got *|name, which means the name MUST be in a namespce,
					// so we pass this off to elementNameNS().
					$name = $this->getScanner()->getNameString();
					$this->handler->elementNS($name, '*');
				}
			} else {
				$this->handler->anyElement();
			}
		}
	}

	/**
	 * Handler an attribute.
	 * An attribute can be in one of two forms:
	 * <code>[attrName]</code>
	 * or
	 * <code>[attrName="AttrValue"]</code>.
	 *
	 * This may call the following event handlers: EventHandler::attribute().
	 *
	 * @throws \QueryPath\CSS\ParseException
	 * @throws Exception
	 */
	private function attribute() : void
	{
		if (Token::LSQUARE === $this->getScanner()->token) {
			$attrVal = $op = $ns = null;

			$this->getScanner()->nextToken();
			$this->consumeWhitespace();

			if (Token::AT === $this->getScanner()->token) {
				if ($this->strict) {
					throw new ParseException('The @ is illegal in attributes.');
				}

				$this->getScanner()->nextToken();
				$this->consumeWhitespace();
			}

			if (Token::STAR === $this->getScanner()->token) {
				// Global namespace... requires that attr be prefixed,
				// so we pass this on to a namespace handler.
				$ns = '*';
				$this->getScanner()->nextToken();
			}
			if (Token::PIPE === $this->getScanner()->token) {
				// Skip this. It's a global namespace.
				$this->getScanner()->nextToken();
				$this->consumeWhitespace();
			}

			$attrName = $this->getScanner()->getNameString();
			$this->consumeWhitespace();

			// Check for namespace attribute: ns|attr. We have to peek() to make
			// sure that we haven't hit the |= operator, which looks the same.
			if (Token::PIPE === $this->getScanner()->token && '=' !== $this->getScanner()->peek()) {
				// We have a namespaced attribute.
				$ns = $attrName;
				$this->getScanner()->nextToken();
				$attrName = $this->getScanner()->getNameString();
				$this->consumeWhitespace();
			}

			// Note: We require that operators do not have spaces
			// between characters, e.g. ~= , not ~ =.

			// Get the operator:
			switch ($this->getScanner()->token) {
				case Token::EQ:
					$this->consumeWhitespace();
					$op = EventHandler::IS_EXACTLY;
					break;
				case Token::TILDE:
					if (Token::EQ !== $this->getScanner()->nextToken()) {
						$this->throwError(Token::EQ, $this->getScanner()->token);
					}
					$op = EventHandler::CONTAINS_WITH_SPACE;
					break;
				case Token::PIPE:
					if (Token::EQ !== $this->getScanner()->nextToken()) {
						$this->throwError(Token::EQ, $this->getScanner()->token);
					}
					$op = EventHandler::CONTAINS_WITH_HYPHEN;
					break;
				case Token::STAR:
					if (Token::EQ !== $this->getScanner()->nextToken()) {
						$this->throwError(Token::EQ, $this->getScanner()->token);
					}
					$op = EventHandler::CONTAINS_IN_STRING;
					break;
				case Token::DOLLAR:
					if (Token::EQ !== $this->getScanner()->nextToken()) {
						$this->throwError(Token::EQ, $this->getScanner()->token);
					}
					$op = EventHandler::ENDS_WITH;
					break;
				case Token::CARAT:
					if (Token::EQ !== $this->getScanner()->nextToken()) {
						$this->throwError(Token::EQ, $this->getScanner()->token);
					}
					$op = EventHandler::BEGINS_WITH;
					break;
			}

			if (isset($op)) {
				// Consume '=' and go on.
				$this->getScanner()->nextToken();
				$this->consumeWhitespace();

				// So... here we have a problem. The grammer suggests that the
				// value here is String1 or String2, both of which are enclosed
				// in quotes of some sort, and both of which allow lots of special
				// characters. But the spec itself includes examples like this:
				//   [lang=fr]
				// So some bareword support is assumed. To get around this, we assume
				// that bare words follow the NAME rules, while quoted strings follow
				// the String1/String2 rules.

				if (Token::QUOTE === $this->getScanner()->token || Token::SQUOTE === $this->getScanner()->token) {
					$attrVal = $this->getScanner()->getQuotedString();
				} else {
					$attrVal = $this->getScanner()->getNameString();
				}
			}

			$this->consumeWhitespace();

			if (Token::RSQUARE !== $this->getScanner()->token) {
				$this->throwError(Token::RSQUARE, $this->getScanner()->token);
			}

			if (isset($ns)) {
				$this->handler->attributeNS($attrName, $ns, $attrVal, $op);
			} elseif (isset($attrVal)) {
				$this->handler->attribute($attrName, $attrVal, $op);
			} else {
				$this->handler->attribute($attrName);
			}
			$this->getScanner()->nextToken();
		}
	}

	/**
	 * Utility for throwing a consistantly-formatted parse error.
	 */
	private function throwError(string|int|false $expected, string|int|false|null $got) : void
	{
		$filter = sprintf('Expected %s, got %s', Token::name($expected), Token::name($got ?? ''));
		throw new ParseException($filter);
	}
}
