<?php

declare(strict_types=1);

namespace QueryPath;

use function assert;
use BadMethodCallException;
use Countable;
use DOMDocument;
use DOMDocumentFragment;
use DOMNode;
use Exception;
use const FILTER_FLAG_ENCODE_LOW;
use const FILTER_UNSAFE_RAW;
use function function_exists;
use function is_array;
use function is_object;
use function is_string;
use IteratorAggregate;
use QueryPath\CSS\DOMTraverser;
use SimpleXMLElement;
use SplObjectStorage;
use function strlen;
use Traversable;
use UnexpectedValueException;
use const XML_ELEMENT_NODE;

/**
 * Class DOM.
 *
 * @property array|SplObjectStorage|Traversable matches
 */
abstract class DOM implements Query, IteratorAggregate, Countable
{
	/**
	 * Default parser flags.
	 *
	 * These are flags that will be used if no global or local flags override them.
	 *
	 * @since 2.0
	 */
	public const DEFAULT_PARSER_FLAGS = 0;

	public const JS_CSS_ESCAPE_CDATA = '\\1';
	public const JS_CSS_ESCAPE_CDATA_CCOMMENT = '/* \\1 */';
	public const JS_CSS_ESCAPE_CDATA_DOUBLESLASH = '// \\1';
	public const JS_CSS_ESCAPE_NONE = '';

	/**
	 * The SplObjectStorage of matches.
	 *
	 * @var SplObjectStorage<DOMNode|TextContent, mixed>|null
	 */
	protected ?SplObjectStorage $matches = null;

	/**
	 * The number of current matches.
	 *
	 * @see count()
	 */
	public int $length = 0;

	/**
	 * The last SplObjectStorage of matches.
	 *
	 * @var SplObjectStorage<DOMNode|TextContent, mixed>|null
	 */
	protected ?SplObjectStorage $last = null; // Last set of matches.

	protected int $errTypes = 771; //E_ERROR; | E_USER_ERROR;

	protected DOMDocument|\Masterminds\HTML5|null $document;

	/**
	 * The base DOMDocument.
	 *
	 * @var array{
	 *	context?: resource,
	 *	encoding?: string,
	 *	use_parser?: 'xml'|'html',
	 *	parser_flags: int|null,
	 *	omit_xml_declaration: bool,
	 *	replace_entities: bool,
	 *	exception_level: int,
	 *	ignore_parser_warnings: bool,
	 *	escape_xhtml_js_css_sections: string,
	 *	convert_from_encoding?: string,
	 *	convert_to_encoding?: string
	 * }
	 */
	protected array $options = [
		'parser_flags' => null,
		'omit_xml_declaration' => false,
		'replace_entities' => false,
		'exception_level' => 771, // E_ERROR | E_USER_ERROR | E_USER_WARNING | E_WARNING
		'ignore_parser_warnings' => false,
		'escape_xhtml_js_css_sections' => self::JS_CSS_ESCAPE_CDATA_CCOMMENT,
	];

	/**
	 * Constructor.
	 *
	 * Typically, a new DOMQuery is created by QueryPath::with(), QueryPath::withHTML(),
	 * qp(), or htmlqp().
	 *
	 * @param DOM|SplObjectStorage<DOMNode|TextContent, mixed>|DOMDocument|DOMNode|\Masterminds\HTML5|SimpleXMLElement|list<DOMNode>|string|null $document
	 *   A document-like object
	 * @param string $string
	 *   A CSS 3 Selector
	 * @param array{
	 *	context?: resource,
	 *	encoding? : string,
	 *	use_parser?: 'xml'|'html',
	 *	parser_flags?: int|null,
	 *	omit_xml_declaration?: bool,
	 *	replace_entities?: bool,
	 *	exception_level?: int,
	 *	ignore_parser_warnings?: bool,
	 *	escape_xhtml_js_css_sections?: string,
	 *	convert_from_encoding?: string,
	 *	convert_to_encoding?: string
	 * } $options
	 *   An associative array of options
	 *
	 * @see qp()
	 *
	 * @throws Exception
	 */
	public function __construct(DOM|SplObjectStorage|DOMDocument|DOMNode|\Masterminds\HTML5|SimpleXMLElement|array|string|null $document = null, $string = null, array $options = [])
	{
		$string = trim($string ?? '');
		$this->options = $options + Options::get() + $this->options;

		$parser_flags = $options['parser_flags'] ?? self::DEFAULT_PARSER_FLAGS;
		if ( ! empty($this->options['ignore_parser_warnings'])) {
			// Don't convert parser warnings into exceptions.
			$this->errTypes = 257; //E_ERROR | E_USER_ERROR;
		} elseif (isset($this->options['exception_level'])) {
			// Set the error level at which exceptions will be thrown. By default,
			// QueryPath will throw exceptions for
			// E_ERROR | E_USER_ERROR | E_WARNING | E_USER_WARNING.
			$this->errTypes = $this->options['exception_level'];
		}

		// Empty: Just create an empty QP.
		if (empty($document)) {
			$this->document = isset($this->options['encoding']) ? new DOMDocument('1.0',
				$this->options['encoding']) : new DOMDocument();
			$this->setMatches(new SplObjectStorage());
		} // Figure out if document is DOM, HTML/XML, or a filename
		elseif (is_object($document)) {
			// This is the most frequent object type.
			if ($document instanceof SplObjectStorage) {
				$this->matches = $document;
				if (0 !== $document->count()) {
					$first = $this->getFirstMatch();
					if ( ! empty($first->ownerDocument)) {
						$this->document = $first->ownerDocument;
					}
				}
			} elseif ($document instanceof self) {
				//$this->matches = $document->get(NULL, TRUE);
				$this->setMatches($document->get(null, true));
				if ($this->getMatches()->count() > 0) {
					$this->document = $this->getFirstMatch()->ownerDocument ?? null;
				}
			} elseif ($document instanceof DOMDocument) {
				$this->document = $document;
				//$this->matches = $this->matches($document->documentElement);
				$this->setMatches($document->documentElement);
			} elseif ($document instanceof DOMNode) {
				$this->document = $document->ownerDocument;
				//$this->matches = array($document);
				$this->setMatches($document);
			} elseif ($document instanceof \Masterminds\HTML5) {
				$this->document = $document;
				assert(
					isset($document->documentElement),
					new UnexpectedValueException(
						'$document->documentElement was not present'
					)
				);
				assert(
					$document->documentElement instanceof DOMNode,
					new UnexpectedValueException(
						'$document->documentElement was not a DOMNode'
					)
				);
				$this->setMatches($document->documentElement);
			} else { // SimpleXMLElement
				$import = dom_import_simplexml($document);
				$this->document = $import->ownerDocument;
				//$this->matches = array($import);
				$this->setMatches($import);
			}
		} elseif (is_array($document)) {
			//trigger_error('Detected deprecated array support', E_USER_NOTICE);
			if ((($document[0] ?? null) instanceof DOMNode)) {
				/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
				$found = new SplObjectStorage();
				foreach ($document as $item) {
					$found->attach($item);
				}
				//$this->matches = $found;
				$this->setMatches($found);
				$this->document = $this->getFirstMatch()->ownerDocument ?? null;
			}
		} elseif ($this->isXMLish($document)) {
			// $document is a string with XML
			$this->document = $this->parseXMLString($document);
			assert(
				$this->document()->documentElement instanceof DOMNode,
				new UnexpectedValueException(
					'$document->documentElement was not a DOMNode'
				)
			);
			$this->setMatches($this->document()->documentElement);
		} else {
			// $document is a filename
			$context = empty($options['context']) ? null : $options['context'];
			$this->document = $this->parseXMLFile($document, $parser_flags, $context);
			assert(
				$this->document()->documentElement instanceof DOMNode,
				new UnexpectedValueException(
					'$document->documentElement was not a DOMNode'
				)
			);
			$this->setMatches($this->document()->documentElement);
		}

		// Globally set the output option.
		$this->document()->formatOutput = true;
		if (isset($this->options['format_output']) && false === $this->options['format_output']) {
			$this->document()->formatOutput = false;
		}

		// Do a find if the second param was set.
		if (strlen($string) > 0) {
			// We don't issue a find because that creates a new DOMQuery.
			//$this->find($string);

			$query = new DOMTraverser($this->getMatches());
			$query->find($string);
			$this->setMatches($query->matches());
		}
	}

	/**
	 * @template T as int|null
	 *
	 * @param T $index
	 *
	 * @return (T is int ? DOMNode|TextContent|null : (SplObjectStorage<DOMNode|TextContent, mixed>|list<DOMNode>))
	 */
	abstract public function get(
		?int $index = null,
		bool $asObject = false
	) : SplObjectStorage|array|DOMNode|TextContent|null;

	/**
	 * Get the DOMDocument that we currently work with.
	 *
	 * This returns the current DOMDocument. Any changes made to this document will be
	 * accessible to DOMQuery, as both will share access to the same object.
	 */
	public function document() : DOMDocument
	{
		assert(
			$this->document instanceof DOMDocument,
			new UnexpectedValueException('document somehow null!')
		);

		return $this->document;
	}

	/**
	 * EXPERT: Be very, very careful using this.
	 * A utility function for setting the current set of matches.
	 * It makes sure the last matches buffer is set (for end() and andSelf()).
	 *
	 * @since 2.0
	 *
	 * @param SplObjectStorage<DOMNode|TextContent, mixed>|list<DOMNode>|DOMNode|TextContent|null $matches
	 */
	public function setMatches(SplObjectStorage|array|DOMNode|TextContent|null $matches) : void
	{
		// This causes a lot of overhead....
		//if ($unique) $matches = self::unique($matches);
		$this->last = $this->matches;

		// Just set current matches.
		if ($matches instanceof SplObjectStorage) {
			$this->matches = $matches;
		} // This is likely legacy code that needs conversion.
		elseif (is_array($matches)) {
			trigger_error('Legacy array detected.');
			/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
			$tmp = new SplObjectStorage();
			foreach ($matches as $m) {
				$tmp->attach($m);
			}
			$this->matches = $tmp;
		}
		// For non-arrays, try to create a new match set and
		// add this object.
		else {
			/** @var SplObjectStorage<DOMNode|TextContent, mixed> */
			$found = new SplObjectStorage();
			if ($matches instanceof DOMNode || $matches instanceof TextContent) {
				$found->attach($matches);
			}
			$this->matches = $found;
		}

		// EXPERIMENTAL: Support for qp()->length.
		$this->length = $this->getMatches()->count();
	}

	/**
	 * @return SplObjectStorage<DOMNode|TextContent, mixed>
	 */
	public function getMatches() : SplObjectStorage
	{
		if (null === $this->matches) {
			throw new BadMethodCallException(
				'Matches not set prior to call!'
			);
		}

		return $this->matches;
	}

	/**
	 * A depth-checking function. Typically, it only needs to be
	 * invoked with the first parameter. The rest are used for recursion.
	 *
	 * @see deepest();
	 *
	 * @template T as DOMNode|TextContent
	 *
	 * @param T $ele
	 *  The element
	 * @param int $depth
	 *  The depth guage
	 * @param list<DOMNode>|null $current
	 *  The current set
	 * @param int $deepest
	 *  A reference to the current deepest node
	 *
	 * @return (T is TextContent ? array{0:TextContent} : list<DOMNode>)
	 *  Returns an array of DOM nodes
	 */
	protected function deepestNode(DOMNode|TextContent $ele, int $depth = 0, array $current = null, int &$deepest = null) : array
	{
		if ($ele instanceof TextContent) {
			return [$ele];
		}

		/** @var DOMNode */
		$ele = $ele;

		// FIXME: Should this use SplObjectStorage?
		if ( ! isset($current)) {
			/** @var list<DOMNode> */
			$current = [$ele];
		}
		if ( ! isset($deepest)) {
			$deepest = $depth;
		}
		if ($ele->hasChildNodes()) {
			/** @var DOMNode */
			foreach ($ele->childNodes as $child) {
				if (XML_ELEMENT_NODE === $child->nodeType) {
					$current = $this->deepestNode($child, $depth + 1, $current, $deepest);
				}
			}
		} elseif ($depth > $deepest) {
			$current = [$ele];
			$deepest = $depth;
		} elseif ($depth === $deepest) {
			$current[] = $ele;
		}

		return $current;
	}

	/**
	 * Prepare an item for insertion into a DOM.
	 *
	 * This handles a variety of boilerplate tasks that need doing before an
	 * indeterminate object can be inserted into a DOM tree.
	 * - If item is a string, this is converted into a document fragment and returned.
	 * - If item is a DOMQuery, then all items are retrieved and converted into
	 *   a document fragment and returned.
	 * - If the item is a DOMNode, it is imported into the current DOM if necessary.
	 * - If the item is a SimpleXMLElement, it is converted into a DOM node and then
	 *   imported.
	 *
	 * @param string|DOMQuery|DOMNode|SimpleXMLElement|TextContent|null $item
	 *  Item to prepare for insert
	 *
	 * @throws Exception
	 *  Thrown if the object passed in is not of a supprted object type
	 *
	 * @return mixed
	 *  Returns the prepared item
	 */
	protected function prepareInsert(string|DOMQuery|DOMNode|SimpleXMLElement|TextContent|null $item) : DOMDocumentFragment|DOMNode|null
	{
		if (empty($item)) {
			return null;
		} elseif ($item instanceof TextContent) {
			$item = $item->textContent;
		}

		if (is_string($item)) {
			// If configured to do so, replace all entities.
			if ($this->options['replace_entities']) {
				$item = Entities::replaceAllEntities($item);
			}

			$frag = $this->document()->createDocumentFragment();
			try {
				set_error_handler(ParseException::initializeFromError(), $this->errTypes);
				$frag->appendXML($item);
			} // Simulate a finally block.
			catch (Exception $e) {
				restore_error_handler();
				throw $e;
			}
			restore_error_handler();

			return $frag;
		}

		if ($item instanceof self) {
			if (0 === $item->count()) {
				return null;
			}

			$frag = $this->document()->createDocumentFragment();
			foreach ($item->getMatches() as $m) {
				if ( ! ($m instanceof DOMNode)) {
					continue;
				}
				$frag->appendXML($item->document()->saveXML($m));
			}

			return $frag;
		}

		if ($item instanceof DOMNode) {
			if ($item->ownerDocument !== $this->document) {
				// Deep clone this and attach it to this document
				$item = $this->document()->importNode($item, true);
			}

			return $item;
		}

		$element = dom_import_simplexml($item);

		return $this->document()->importNode($element, true);
	}

	/**
	 * Convenience function for getNthMatch(0).
	 */
	protected function getFirstMatch() : DOMNode|TextContent|null
	{
		$this->getMatches()->rewind();

		return $this->getMatches()->current();
	}

	/**
	 * Determine whether a given string looks like XML or not.
	 *
	 * Basically, this scans a portion of the supplied string, checking to see
	 * if it has a tag-like structure. It is possible to "confuse" this, which
	 * may subsequently result in parse errors, but in the vast majority of
	 * cases, this method serves as a valid inicator of whether or not the
	 * content looks like XML.
	 *
	 * Things that are intentional excluded:
	 * - plain text with no markup.
	 * - strings that look like filesystem paths.
	 *
	 * Subclasses SHOULD NOT OVERRIDE THIS. Altering it may be altering
	 * core assumptions about how things work. Instead, classes should
	 * override the constructor and pass in only one of the parsed types
	 * that this class expects.
	 */
	protected function isXMLish(string $string) : bool
	{
		return false !== strpos($string, '<') && false !== strpos($string, '>');
	}

	/**
	 * A utility function for retriving a match by index.
	 *
	 * The internal data structure used in DOMQuery does not have
	 * strong random access support, so we suppliment it with this method.
	 */
	protected function getNthMatch(int $index) : DOMNode|TextContent|null
	{
		if ($index < 0 || $index > $this->getMatches()->count()) {
			return null;
		}

		$i = 0;
		foreach ($this->getMatches() as $m) {
			if ($i++ === $index) {
				return $m;
			}
		}

		return null;
	}

	private function parseXMLString(string $string, int $flags = null) : DOMDocument
	{
		$document = new DOMDocument('1.0');
		$lead = strtolower(substr($string, 0, 5)); // <?xml
		try {
			set_error_handler(ParseException::initializeFromError(), $this->errTypes);

			if (isset($this->options['convert_to_encoding'])) {
				// Is there another way to do this?

				$from_enc = $this->options['convert_from_encoding'] ?? 'auto';
				$to_enc = $this->options['convert_to_encoding'];

				if (function_exists('mb_convert_encoding')) {
					$string = mb_convert_encoding($string, $to_enc, $from_enc);
				}
			}

			// This is to avoid cases where low ascii digits have slipped into HTML.
			// AFAIK, it should not adversly effect UTF-8 documents.
			if ( ! empty($this->options['strip_low_ascii'])) {
				$string = (string) filter_var($string, FILTER_UNSAFE_RAW, FILTER_FLAG_ENCODE_LOW);
			}

			// Allow users to override parser settings.
			$useParser = '';
			if ( ! empty($this->options['use_parser'])) {
				$useParser = $this->options['use_parser'];
			}

			// If HTML parser is requested, we use it.
			if ('html' === $useParser) {
				$document->loadHTML($string);
			} // Parse as XML if it looks like XML, or if XML parser is requested.
			elseif ('<?xml' === $lead || 'xml' === $useParser) {
				if ($this->options['replace_entities']) {
					$string = Entities::replaceAllEntities($string);
				}
				$document->loadXML($string, $flags ?? 0);
			} // In all other cases, we try the HTML parser.
			else {
				$document->loadHTML($string);
			}
		} // Emulate 'finally' behavior.
		catch (Exception $e) {
			restore_error_handler();
			throw $e;
		}
		restore_error_handler();

		return $document;
	}

	/**
	 * Parse an XML or HTML file.
	 *
	 * This attempts to autodetect the type of file, and then parse it.
	 *
	 * @param string $filename
	 *  The file name to parse
	 * @param int $flags
	 *  The OR-combined flags accepted by the DOM parser. See the PHP documentation
	 *  for DOM or for libxml.
	 * @param resource|null $context
	 *  The stream context for the file IO. If this is set, then an alternate
	 *  parsing path is followed: The file is loaded by PHP's stream-aware IO
	 *  facilities, read entirely into memory, and then handed off to
	 *  {@link parseXMLString()}. On large files, this can have a performance impact.
	 *
	 * @throws \QueryPath\ParseException
	 *  Thrown when a file cannot be loaded or parsed
	 */
	private function parseXMLFile(string $filename, int $flags = 0, $context = null) : DOMDocument
	{
		// If a context is specified, we basically have to do the reading in
		// two steps:
		if ( ! empty($context)) {
			try {
				set_error_handler(ParseException::initializeFromError(), $this->errTypes);
				$contents = file_get_contents($filename, false, $context);
			}
			// Apparently there is no 'finally' in PHP, so we have to restore the error
			// handler this way:
			catch (Exception $e) {
				restore_error_handler();
				throw $e;
			}
			restore_error_handler();

			if (false == $contents) {
				throw new \QueryPath\ParseException(sprintf('Contents of the file %s could not be retrieved.',
					$filename));
			}

			return $this->parseXMLString($contents, $flags);
		}

		$document = new DOMDocument();
		$lastDot = strrpos($filename, '.');

		$htmlExtensions = [
			'.html' => 1,
			'.htm' => 1,
		];

		// Allow users to override parser settings.
		if (empty($this->options['use_parser'])) {
			$useParser = '';
		} else {
			$useParser = $this->options['use_parser'];
		}

		$ext = false !== $lastDot ? strtolower(substr($filename, $lastDot)) : '';

		try {
			set_error_handler(ParseException::initializeFromError(), $this->errTypes);

			// If the parser is explicitly set to XML, use that parser.
			if ('xml' === $useParser) {
				$document->load($filename, $flags);
			} // Otherwise, see if it looks like HTML.
			elseif ('html' === $useParser || isset($htmlExtensions[$ext])) {
				// Try parsing it as HTML.
				$document->loadHTMLFile($filename);
			} // Default to XML.
			else {
				$document->load($filename, $flags);
			}
		} // Emulate 'finally' behavior.
		catch (Exception $e) {
			restore_error_handler();
			throw $e;
		}
		restore_error_handler();

		return $document;
	}
}
