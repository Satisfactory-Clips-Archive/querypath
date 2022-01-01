<?php

declare(strict_types=1);

namespace QueryPathTests;

/** @addtogroup querypath_tests Tests
 * Unit tests and regression tests for DOMQuery.
 */

use function chr;
use function count;
use function define;
use DOMDocument;
use DOMElement;
use DOMNode;
use function in_array;
use QueryPath\DOMQuery;
use QueryPath\Query;
use QueryPath\QueryPath;
use QueryPath\TextContent;
use SplDoublyLinkedList;
use const XML_ELEMENT_NODE;

define('DATA_FILE', __DIR__ . '/../data.xml');
define('DATA_HTML_FILE', __DIR__ . '/../data.html');
define('NO_WRITE_FILE', __DIR__ . '/../no-write.xml');
define('MEDIUM_FILE', __DIR__ . '/../amplify.xml');
define('HTML_IN_XML_FILE', __DIR__ . '/../html.xml');

/**
 * Tests for DOM Query. Primarily, this is focused on the DomQueryImpl
 * class which is exposed through the DomQuery interface and the dq()
 * factory function.
 *
 * @ingroup querypath_tests
 */
class DOMQueryTest extends TestCase
{
	public const test_tests_allowed_failures = [
		'testxinclude',
		'testgetmatches',
	];

	/**
	 * @group basic
	 */
	public function testDOMQueryConstructors() : void
	{
		// From XML file
		$file = DATA_FILE;
		$qp = qp($file);
		$this->assertCount(1, $qp->get());
		$this->assertTrue($qp->get(0) instanceof DOMNode);

		// From XML file with context
		$cxt = stream_context_create();
		$qp = qp($file, null, ['context' => $cxt]);
		$this->assertCount(1, $qp->get());
		$this->assertTrue($qp->get(0) instanceof DOMNode);

		// From XML string
		$str = '<?xml version="1.0" ?><root><inner/></root>';
		$qp = qp($str);
		$this->assertCount(1, $qp->get());
		$this->assertTrue($qp->get(0) instanceof DOMNode);

		// From SimpleXML
		$str = '<?xml version="1.0" ?><root><inner/></root>';
		$qp = qp(simplexml_load_string($str));
		$this->assertCount(1, $qp->get());
		$this->assertTrue($qp->get(0) instanceof DOMNode);

		// Test from DOMDocument
		$doc = new DOMDocument();
		$doc->loadXML($str);
		$qp = qp($doc);
		$this->assertCount(1, $qp->get());
		$this->assertTrue($qp->get(0) instanceof DOMNode);

		// Now with a selector:
		$qp = qp($file, '#head');
		$this->assertCount(1, $qp->get());
		$a = $qp->get(0);
		$this->assertInstanceOf(DOMElement::class, $a);
		$this->assertSame($a->tagName, 'head');

		// Test HTML:
		$htmlFile = DATA_HTML_FILE;
		$qp = qp($htmlFile);
		$this->assertCount(1, $qp->get());
		$this->assertTrue($qp->get(0) instanceof DOMNode);

		// Test with another DOMQuery.
		$qp = qp($qp);
		$this->assertCount(1, $qp->get());
		$this->assertTrue($qp->get(0) instanceof DOMNode);

		// Test from array of DOMNodes
		$array = $qp->get();
		$qp = qp($array);
		$this->assertCount(1, $qp->get());
		$this->assertTrue($qp->get(0) instanceof DOMNode);
	}

	/**
	 * Test alternate constructors.
	 *
	 * @group basic
	 */
	public function testDOMQueryHtmlConstructors() : void
	{
		$qp = htmlqp(QueryPath::HTML_STUB);
		$this->assertCount(1, $qp->get());
		$this->assertTrue($qp->get(0) instanceof DOMNode);

		// Bad BR tag.
		$borken = '<html><head></head><body><br></body></html>';
		$qp = htmlqp($borken);
		$this->assertCount(1, $qp->get());
		$this->assertTrue($qp->get(0) instanceof DOMNode);

		// XHTML Faker
		$borken = '<?xml version="1.0"?><html><head></head><body><br></body></html>';
		$qp = htmlqp($borken);
		$this->assertCount(1, $qp->get());
		$this->assertTrue($qp->get(0) instanceof DOMNode);

		// HTML in a file that looks like XML.
		$qp = htmlqp(HTML_IN_XML_FILE);
		$this->assertCount(1, $qp->get());
		$this->assertTrue($qp->get(0) instanceof DOMNode);

		// HTML5
		$html5 = new \Masterminds\HTML5();
		$dom = $html5->loadHTML(QueryPath::HTML_STUB);
		qp($dom, 'html');

		// Stripping #13 (CR) from HTML.
		$borken = '<html><head></head><body><p>' . chr(13) . '</p><div id="after"/></body></html>';
		$borken_html = htmlqp($borken)->html();
		$this->assertIsString($borken_html);
		$this->assertFalse(strpos($borken_html, '&#13;'), 'Test that CRs are not encoded.');

		// Regression for #58: Make sure we aren't getting &#10; encoded.
		$borken = '<html><head><style>
        .BlueText {
          color:red;
        }</style><body></body></html>';

		$borken_html = htmlqp($borken)->html();
		$this->assertIsString($borken_html);
		$this->assertFalse(strpos($borken_html, '&#10;'), 'Test that LF is not encoded.');

		// Low ASCII in a file
		$borken = '<html><head></head><body><p>' . chr(27) . '</p><div id="after"/></body></html>';
		$this->assertSame(1, htmlqp($borken, '#after')->count());
	}

	public function testForTests() : void
	{
		$qp_methods = get_class_methods(DOMQuery::class);
		$test_methods = get_class_methods(DOMQueryTest::class);

		$ignore = ['__construct', '__call', '__clone', 'get', 'getOptions', 'setMatches', 'toArray', 'getIterator'];

		$test_methods = array_map('strtolower', $test_methods);

		$expected = [];
		$found = [];

		foreach ($qp_methods as $q) {
			if (in_array($q, $ignore, true)) {
				continue;
			}
			$test_method = strtolower('test' . $q);
			$expected[] = $test_method;
			if (in_array($test_method, $test_methods, true)) {
				$found[] = $test_method;
			}
		}

		$diff = array_values(array_diff($expected, $found));

		$this->assertSame(
			self::test_tests_allowed_failures,
			$diff,
			'Some test methods not implemented.'
		);
	}

	public function testHtml5() : void
	{
		$doc = qp(DATA_HTML_FILE);
		$this->assertSame('range', $doc->find('input')->attr('type'));
	}

	public function testInnerHtml5() : void
	{
		$doc = qp(DATA_HTML_FILE);
		$this->assertSame('1', $doc->find('input')->attr('min'));
	}

	public function testOptionXMLEncoding() : void
	{
		$xml = qp(null, null, ['encoding' => 'iso-8859-1'])->append('<test/>')->xml();
		$this->assertIsString($xml);
		$iso_found = 1 === preg_match('/iso-8859-1/', $xml);

		$this->assertTrue($iso_found, 'Encoding should be iso-8859-1 in ' . $xml . 'Found ' . $iso_found);

		$iso_found = 1 === preg_match('/utf-8/', $xml);
		$this->assertFalse($iso_found, 'Encoding should not be utf-8 in ' . $xml);

		$xml = qp('<?xml version="1.0" encoding="utf-8"?><test/>', null, ['encoding' => 'iso-8859-1'])->xml();
		$this->assertIsString($xml);
		$iso_found = 1 === preg_match('/utf-8/', $xml);
		$this->assertTrue($iso_found, 'Encoding should be utf-8 in ' . $xml);

		$iso_found = 1 === preg_match('/iso-8859-1/', $xml);
		$this->assertFalse($iso_found, 'Encoding should not be utf-8 in ' . $xml);
	}

	public function testQPAbstractFactory() : void
	{
		$options = ['QueryPath_class' => QueryPathExtended::class];
		$qp = qp(null, null, $options);
		$this->assertTrue($qp instanceof QueryPathExtended, 'Is instance of extending class.');
		$this->assertTrue($qp->foonator(), 'Has special foonator() function.');
	}

	public function testQPAbstractFactoryIterating() : void
	{
		$xml = '<?xml version="1.0"?><l><i/><i/><i/><i/><i/></l>';
		$options = ['QueryPath_class' => QueryPathExtended::class];
		/** @var DOMQuery */
		foreach (qp($xml, 'i', $options) as $item) {
			$this->assertTrue($item instanceof QueryPathExtended, 'Is instance of extending class.');
		}
	}

	public function testFailedCall() : void
	{
		// This should hit __call() and then fail.
		$this->expectException(\QueryPath\Exception::class);
		qp()->fooMethod();
	}

	public function testFailedHTTPLoad() : void
	{
		$this->expectException(\QueryPath\ParseException::class);
		qp('http://localhost:8877/no_such_file.xml');
	}

	public function testFailedHTTPLoadWithContext() : void
	{
		$this->expectException(\QueryPath\ParseException::class);
		qp('http://localhost:8877/no_such_file.xml', null, ['foo' => 'bar']);
	}

	public function testFailedParseHTMLElement() : void
	{
		$this->expectException(\QueryPath\ParseException::class);
		qp('<foo>&foonator;</foo>', null);
	}

	public function testFailedParseXMLElement() : void
	{
		$this->expectException(\QueryPath\ParseException::class);
		qp('<?xml version="1.0"?><foo><bar>foonator;</foo>', null);
	}

	public function testIgnoreParserWarnings() : void
	{
		$qp = @qp('<html><body><b><i>BAD!</b></i></body>', null, ['ignore_parser_warnings' => true]);
		$qp_html = $qp->html();
		$this->assertIsString($qp_html);
		$this->assertTrue(false !== strpos($qp_html, '<i>BAD!</i>'));

		\QueryPath\Options::merge(['ignore_parser_warnings' => true]);
		$qp = @qp('<html><body><b><i>BAD!</b></i></body>');
		$qp_html = $qp->html();
		$this->assertIsString($qp_html);
		$this->assertTrue(false !== strpos($qp_html, '<i>BAD!</i>'));

		$qp = @qp('<html><body><blarg>BAD!</blarg></body>');
		$qp_html = $qp->html();
		$this->assertIsString($qp_html);
		$this->assertTrue(false !== strpos($qp_html, '<blarg>BAD!</blarg>'), $qp_html);
		\QueryPath\Options::set([]); // Reset to empty options.
	}

	public function testFailedParseNonMarkup() : void
	{
		$this->expectException(\QueryPath\ParseException::class);
		qp('<23dfadf', null);
	}

	public function testFailedParseEntity() : void
	{
		$this->expectException(\QueryPath\ParseException::class);
		qp('<?xml version="1.0"?><foo>&foonator;</foo>', null);
	}

	public function testReplaceEntitiesOption() : void
	{
		$path = '<?xml version="1.0"?><root/>';
		$qp = qp($path, null, ['replace_entities' => true])->xml('<foo>&</foo>');
		$this->assertInstanceOf(DOMQuery::class, $qp);
		$xml = $qp->xml();
		$this->assertIsString($xml);
		$this->assertTrue(false !== strpos($xml, '<foo>&amp;</foo>'));

		$qp = qp($path, null, ['replace_entities' => true])->html('<foo>&</foo>');
		$this->assertInstanceOf(DOMQuery::class, $qp);
		$xml = $qp->xml();
		$this->assertIsString($xml);
		$this->assertTrue(false !== strpos($xml, '<foo>&amp;</foo>'));

		$qp = qp($path, null, ['replace_entities' => true])->xhtml('<foo>&</foo>');
		$this->assertInstanceOf(DOMQuery::class, $qp);
		$xml = $qp->xml();
		$this->assertIsString($xml);
		$this->assertTrue(false !== strpos($xml, '<foo>&amp;</foo>'));

		\QueryPath\Options::set(['replace_entities' => true]);
		$this->assertTrue(false !== strpos($xml, '<foo>&amp;</foo>'));
		\QueryPath\Options::set([]);
	}

	/**
	 * @group basic
	 */
	public function testFind() : void
	{
		$file = DATA_FILE;
		$qp = qp($file)->find('#head');
		$this->assertCount(1, $qp->get());
		$a = $qp->get(0);
		$this->assertInstanceOf(DOMElement::class, $a);
		$this->assertSame($a->tagName, 'head');

		$this->assertSame('inner', qp($file)->find('.innerClass')->tag());

		$string = '<?xml version="1.0"?><root><a/>Test</root>';
		$qp = qp($string)->find('root');
		$this->assertCount(1, $qp->get(), 'Check tag.');
		$a = $qp->get(0);
		$this->assertInstanceOf(DOMElement::class, $a);
		$this->assertSame($a->tagName, 'root');

		$string = '<?xml version="1.0"?><root class="findme">Test</root>';
		$qp = qp($string)->find('.findme');
		$this->assertCount(1, $qp->get(), 'Check class.');
		$a = $qp->get(0);
		$this->assertInstanceOf(DOMElement::class, $a);
		$this->assertSame($a->tagName, 'root');
	}

	public function testFindInPlace() : void
	{
		$file = DATA_FILE;
		$qp = qp($file)->find('#head');
		$this->assertCount(1, $qp->get());
		$a = $qp->get(0);
		$this->assertInstanceOf(DOMElement::class, $a);
		$this->assertSame($a->tagName, 'head');

		$this->assertSame('inner', qp($file)->find('.innerClass')->tag());

		$string = '<?xml version="1.0"?><root><a/>Test</root>';
		$qp = qp($string)->find('root');
		$this->assertCount(1, $qp->get(), 'Check tag.');
		$a = $qp->get(0);
		$this->assertInstanceOf(DOMElement::class, $a);
		$this->assertSame($a->tagName, 'root');

		$string = '<?xml version="1.0"?><root class="findme">Test</root>';
		$qp = qp($string)->find('.findme');
		$this->assertCount(1, $qp->get(), 'Check class.');
		$a = $qp->get(0);
		$this->assertInstanceOf(DOMElement::class, $a);
		$this->assertSame($a->tagName, 'root');
	}

	/**
	 * @group basic
	 */
	public function testTop() : void
	{
		$file = DATA_FILE;
		$qp = qp($file)->find('li');
		$this->assertGreaterThan(2, $qp->count());
		$this->assertSame(1, $qp->top()->count());

		// Added for QP 2.0
		$xml = '<?xml version="1.0"?><root><u><l/><l/><l/></u><u/></root>';
		$qp = qp($xml, 'l');
		$this->assertSame(3, $qp->count());
		$this->assertSame(2, $qp->top('u')->count());
	}

	/**
	 * @group basic
	 */
	public function testAttr() : void
	{
		$file = DATA_FILE;

		$qp = qp($file)->find('#head');
		$this->assertSame(1, $qp->count());
		$a = $qp->get(0);
		$this->assertInstanceOf(DOMElement::class, $a);
		$this->assertSame($a->getAttribute('id'), $qp->attr('id'));

		$qp->attr('foo', 'bar');
		$this->assertSame('bar', $qp->attr('foo'));

		$qp->attr(['foo2' => 'bar', 'foo3' => 'baz']);
		$this->assertSame('baz', $qp->attr('foo3'));

		// Check magic nodeType attribute:
		$this->assertSame(XML_ELEMENT_NODE, qp($file)->find('#head')->attr('nodeType'));

		// Since QP 2.1
		$xml = '<?xml version="1.0"?><root><one a1="1" a2="2" a3="3"/></root>';
		$qp = qp($xml, 'one');
		$attrs = $qp->attr();
		$this->assertIsArray($attrs);
		$this->assertCount(3, $attrs, 'Three attributes');
		$this->assertSame('1', $attrs['a1'], 'Attribute a1 has value 1.');
	}

	/**
	 * @group basic
	 */
	public function testHasAttr() : void
	{
		$xml = '<?xml version="1.0"?><root><div foo="bar"/></root>';

		$this->assertFalse(qp($xml, 'root')->hasAttr('foo'));
		$this->assertTrue(qp($xml, 'div')->hasAttr('foo'));

		$xml = '<?xml version="1.0"?><root><div foo="bar"/><div foo="baz"></div></root>';
		$this->assertTrue(qp($xml, 'div')->hasAttr('foo'));

		$xml = '<?xml version="1.0"?><root><div bar="bar"/><div foo="baz"></div></root>';
		$this->assertFalse(qp($xml, 'div')->hasAttr('foo'));

		$xml = '<?xml version="1.0"?><root><div bar="bar"/><div bAZ="baz"></div></root>';
		$this->assertFalse(qp($xml, 'div')->hasAttr('foo'));
	}

	public function testVal() : void
	{
		$qp = qp('<?xml version="1.0"?><foo><bar value="test"/></foo>', 'bar');
		$val = $qp->attr('value');
		$this->assertIsString($val);
		$this->assertSame('test', $val);

		$qp = qp('<?xml version="1.0"?><foo><bar/></foo>', 'bar')->attr('value', 'test');
		$this->assertInstanceOf(DOMQuery::class, $qp);
		$this->assertSame('test', $qp->attr('value'));
	}

	public function testCss() : void
	{
		$file = DATA_FILE;
		$this->assertSame('foo: bar;', qp($file, 'unary')->css('foo', 'bar')->attr('style'));
		$this->assertSame('foo: bar;', qp($file, 'unary')->css('foo', 'bar')->css());
		$this->assertSame('foo: bar;', qp($file, 'unary')->css(['foo' => 'bar'])->css());

		// Issue #28: Setting styles in sequence should not result in the second
		// style overwriting the first style:
		$qp = qp($file, 'unary')->css('color', 'blue')->css('background-color', 'white');

		$expects = 'color: blue;background-color: white;';
		$actual = $qp->css();
		$this->assertIsString($actual);
		$this->assertSame(bin2hex($expects), bin2hex($actual), 'Two css calls should result in two attrs.');

		// Make sure array merges work.
		$qp = qp($file, 'unary')->css('a', 'a')->css(['b' => 'b', 'c' => 'c']);
		$this->assertSame('a: a;b: b;c: c;', $qp->css());

		// Make sure that second assignment overrides first assignment.
		$qp = qp($file, 'unary')->css('a', 'a')->css(['b' => 'b', 'a' => 'c']);
		$this->assertSame('a: c;b: b;', $qp->css());
	}

	public function testRemoveAttr() : void
	{
		$file = DATA_FILE;

		$qp = qp($file, 'inner')->removeAttr('class');
		$this->assertSame(2, $qp->count());
		$element = $qp->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertFalse($element->hasAttribute('class'));
	}

	public function testEq() : void
	{
		$file = DATA_FILE;
		$qp = qp($file)->find('li')->eq(0);
		$this->assertSame(1, $qp->count());
		$this->assertSame($qp->attr('id'), 'one');
		$this->assertSame(1, qp($file, 'inner')->eq(0)->count());
		$this->assertSame(1, qp($file, 'li')->eq(0)->count());
		$this->assertSame('Hello', qp($file, 'li')->eq(0)->text());
		$this->assertSame('Last', qp($file, 'li')->eq(3)->text());
	}

	public function testIs() : void
	{
		$file = DATA_FILE;
		$this->assertTrue(qp($file)->find('#one')->is('#one'));
		$this->assertTrue(qp($file)->find('li')->is('#one'));

		$qp = qp($file)->find('#one');
		$ele = $qp->get(0);
		$this->assertInstanceOf(DOMNode::class, $ele);
		$this->assertTrue($qp->top('#one')->is($ele));

		$qp = qp($file)->find('#one');
		$ele = $qp->get(0);
		$ele2 = $qp->top('#two')->get(0);
		$this->assertInstanceOf(DOMNode::class, $ele);
		$this->assertInstanceOf(DOMNode::class, $ele2);

		/** @var SplDoublyLinkedList<mixed, DOMNode> */
		$list = new SplDoublyLinkedList();
		$list->push($ele);
		$list->push($ele2);
		$this->assertCount(2, $list);
		$this->assertTrue($qp->top('#one,#two')->is($list));
	}

	public function testIndex() : void
	{
		$xml = '<?xml version="1.0"?><foo><bar id="one"/><baz id="two"/></foo>';
		$qp = qp($xml, 'bar');
		$e1 = $qp->get(0);
		$this->assertInstanceOf(DOMElement::class, $e1);
		$this->assertSame(0, $qp->find('bar')->index($e1));
		$this->assertFalse($qp->top()->find('#two')->index($e1));
	}

	public function testFilter() : void
	{
		$file = DATA_FILE;
		$this->assertSame(1, qp($file)->filter('li')->count());
		$this->assertSame(2, qp($file, 'inner')->filter('li')->count());
		$this->assertSame('inner-two', qp($file, 'inner')->filter('li')->eq(1)->attr('id'));
	}

	public function testFilterPreg() : void
	{
		$xml = '<?xml version="1.0"?><root><div id="one">Foo</div><div>Moo</div></root>';
		$qp = qp($xml, 'div')->filterPreg('/Foo/');

		$this->assertSame(1, $qp->count());

		// Check to make sure textContent is collected correctly.
		$xml = '<?xml version="1.0"?><root><div>Hello <i>World</i></div></root>';
		$qp = qp($xml, 'div')->filterPreg('/Hello\sWorld/');

		$this->assertSame(1, $qp->count());
	}

	public function testFilterLambda() : void
	{
		$file = DATA_FILE;
		// Get all evens:
		$this->assertSame(2, qp($file, 'li')->filterLambda(
			static function (int $index) : bool {
				return (($index + 1) % 2 == 0);
			}
		)->count());
	}

	public function testFilterCallback() : void
	{
		$this->assertSame(
			2,
			qp(DATA_FILE, 'li')->filterCallback(
				static function (int $index, DOMNode|TextContent $_item) : bool {
					return ($index + 1) % 2 === 0;
				}
			)->count()
		);
	}

	public function testNot() : void
	{
		$file = DATA_FILE;

		// Test with selector
		$qp = qp($file, 'li:odd')->not('#one');
		$this->assertSame(2, $qp->count());

		// Test with DOM Element
		$qp = qp($file, 'li');
		$el = $qp->branch()->filter('#one')->get(0);
		$this->assertTrue($el instanceof DOMElement, 'Is DOM element.');
		$this->assertSame(4, $qp->not($el)->count());

		// Test with array of DOM Elements
		$qp = qp($file, 'li');
		$arr = $qp->get();
		$this->assertSame(count($arr), $qp->count());
		$this->assertIsArray($arr);
		array_shift($arr);
		$this->assertSame(1, $qp->not($arr)->count());
	}

	public function testSlice() : void
	{
		$file = DATA_FILE;
		// There are five <li> elements
		$qp = qp($file, 'li')->slice(1);
		$this->assertSame(4, $qp->count());

		// The first item in the matches should be #two.
		$this->assertSame('two', $qp->attr('id'));

		// THe last item should be #five
		$this->assertSame('five', $qp->eq(3)->attr('id'));

		// This should not throw an error.
		$this->assertSame(4, qp($file, 'li')->slice(1, 9)->count());

		$this->assertSame(0, qp($file, 'li')->slice(9)->count());

		// The first item should be #two, the last #three
		$qp = qp($file, 'li')->slice(1, 2);
		$this->assertSame(2, $qp->count());
		$this->assertSame('two', $qp->attr('id'));
		$this->assertSame('three', $qp->eq(1)->attr('id'));
	}

	/**
	 * @return callable(int, DOMNode|TextContent):(false|list<int>|int)
	 */
	public function mapCallbackFunction()
	{
		return
			/**
			 * @return false|list<int>|int
			 */
			static function (int $index, DOMNode|TextContent $_item) : bool|array|int {
				if (1 === $index) {
					return false;
				}
				if (2 === $index) {
					return [1, 2, 3];
				}

				return $index;
			}
		;
	}

	public function testMap() : void
	{
		$file = DATA_FILE;
		$fn = $this->mapCallbackFunction();
		$this->assertSame(7, qp($file, 'li')->map($fn)->count());
	}

	public function testEach() : void
	{
		$file = DATA_FILE;
		$fn = static function (int $index, DOMNode|TextContent $item) : ?bool {
			if ($index < 2) {
				qp($item)->attr('class', 'test');

				return null;
			}

			return false;
		};
		$res = qp($file, 'li')->each($fn);
		$this->assertSame(5, $res->count());
		$element = $res->get(4);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertSame('test', $res->eq(1)->attr('class'));

		// Test when each runs out of things to test before returning.
		$res = qp($file, '#one')->each($fn);
		$this->assertSame(1, $res->count());
	}

	public function testDeepest() : void
	{
		$str = '<?xml version="1.0" ?>
    <root>
      <one/>
      <one><two/></one>
      <one><two><three/></two></one>
      <one><two><three><four/></three></two></one>
      <one/>
      <one><two><three><banana/></three></two></one>
    </root>';
		$deepest = qp($str)->deepest();
		$this->assertSame(2, $deepest->count());
		$a = $deepest->get(0);
		$b = $deepest->get(1);
		$this->assertInstanceOf(DOMElement::class, $a);
		$this->assertInstanceOf(DOMElement::class, $b);
		$this->assertSame('four', $a->tagName);
		$this->assertSame('banana', $b->tagName);

		$deepest = qp($str, 'one')->deepest();
		$this->assertSame(2, $deepest->count());
		$a = $deepest->get(0);
		$b = $deepest->get(1);
		$this->assertInstanceOf(DOMElement::class, $a);
		$this->assertInstanceOf(DOMElement::class, $b);
		$this->assertSame('four', $a->tagName);
		$this->assertSame('banana', $b->tagName);

		$str = '<?xml version="1.0" ?>
    <root>
      CDATA
    </root>';
		$this->assertSame(1, qp($str)->deepest()->count());
	}

	public function testTag() : void
	{
		$file = DATA_FILE;
		$this->assertSame('li', qp($file, 'li')->tag());
	}

	public function testAppend() : void
	{
		$file = DATA_FILE;
		$this->assertSame(1, qp($file, 'unary')->append('<test/>')->find(':root > unary > test')->count());
		$qp = qp($file, '#inner-one')->append('<li id="appended"/>');

		$appended = $qp->find('#appended');
		$this->assertSame(1, $appended->count());
		$element = $appended->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertNull($element->nextSibling);

		$this->assertSame(2, qp($file, 'inner')->append('<test/>')->top()->find('test')->count());
		$this->assertSame(2,
			qp($file, 'inner')->append(qp('<?xml version="1.0"?><test/>'))->top()->find('test')->count());
		$this->assertSame(4, qp($file, 'inner')->append(qp('<?xml version="1.0"?><root><test/><test/></root>',
			'test'))->top()->find('test')->count());

		// Issue #6: This seems to break on Debian Etch systems... no idea why.
		$this->assertSame('test', qp()->append('<test/>')->top()->tag());

		// Issue #7: Failure issues warnings
		// This seems to be working as expected -- libxml emits
		// parse errors.

		// Test loading SimpleXML.
		$simp = simplexml_load_file($file);
		$qp = qp('<?xml version="1.0"?><foo/>')->append($simp);
		$this->assertSame(1, $qp->find('root')->count());

		// Test with replace entities turned on:
		$qp = qp($file, 'root', ['replace_entities' => true])->append('<p>&raquo;</p>');
		// Note that we are using a UTF-8 » character, not an ASCII 187. This seems to cause
		// problems on some Windows IDEs. So here we do it the ugly way.
		$utf8raquo = '<p>' . mb_convert_encoding(chr(187), 'utf-8', 'iso-8859-1') . '</p>';
		$this->assertSame($utf8raquo, $qp->find('p')->html(), 'Entities are decoded to UTF-8 correctly.');

		// Test with empty, mainly to make sure it doesn't explode.
		$this->assertTrue(qp($file)->append('') instanceof DOMQuery);
	}

	public function testAppendBadMarkup() : void
	{
		$this->expectException(\QueryPath\ParseException::class);
		$file = DATA_FILE;
		qp($file, 'root')->append('<foo><bar></foo>');
	}

	public function testAppendTo() : void
	{
		$file = DATA_FILE;
		$dest = qp('<?xml version="1.0"?><root><dest/></root>', 'dest');
		qp($file, 'li')->appendTo($dest);
		$this->assertSame(5, $dest->find(':root li')->count());
	}

	public function testPrepend() : void
	{
		$file = DATA_FILE;
		$this->assertSame(1, qp($file, 'unary')->prepend('<test/>')->find(':root > unary > test')->count());
		$qp = qp($file, '#inner-one')->prepend('<li id="appended"/>')->find('#appended');
		$this->assertSame(1, $qp->count());
		$element = $qp->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertNull($element->previousSibling);

		// Test repeated insert
		$this->assertSame(2, qp($file, 'inner')->prepend('<test/>')->top()->find('test')->count());
		$this->assertSame(4, qp($file, 'inner')->prepend(qp('<?xml version="1.0"?><root><test/><test/></root>',
			'test'))->top()->find('test')->count());
	}

	public function testPrependTo() : void
	{
		$file = DATA_FILE;
		$dest = qp('<?xml version="1.0"?><root><dest/></root>', 'dest');
		qp($file, 'li')->prependTo($dest);
		$this->assertSame(5, $dest->find(':root li')->count());
	}

	public function testBefore() : void
	{
		$file = DATA_FILE;
		$this->assertSame(1, qp($file, 'unary')->before('<test/>')->find(':root > test ~ unary')->count());
		$this->assertSame(1, qp($file, 'unary')->before('<test/>')->top('head ~ test')->count());
		$element = qp($file, 'unary')->before('<test/>')->top(':root > test')->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertInstanceOf(DOMElement::class, $element->nextSibling);
		$this->assertSame('unary',
			$element->nextSibling->tagName);

		// Test repeated insert
		$this->assertSame(2, qp($file, 'inner')->before('<test/>')->top()->find('test')->count());
		$this->assertSame(4, qp($file, 'inner')->before(qp('<?xml version="1.0"?><root><test/><test/></root>',
			'test'))->top()->find('test')->count());
	}

	public function testAfter() : void
	{
		$file = DATA_FILE;
		$this->assertSame(1, qp($file, 'unary')->after('<test/>')->top(':root > unary ~ test')->count());
		$element = qp($file, 'unary')->after('<test/>')->top(':root > test')->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertInstanceOf(DOMElement::class, $element->previousSibling);
		$this->assertSame('unary',
			$element->previousSibling->tagName);

		$this->assertSame(2, qp($file, 'inner')->after('<test/>')->top()->find('test')->count());
		$this->assertSame(4, qp($file, 'inner')->after(qp('<?xml version="1.0"?><root><test/><test/></root>',
			'test'))->top()->find('test')->count());
	}

	public function testInsertBefore() : void
	{
		$file = DATA_FILE;
		$dest = qp('<?xml version="1.0"?><root><dest/></root>', 'dest');
		qp($file, 'li')->insertBefore($dest);
		$this->assertSame(5, $dest->top(':root > li')->count());
		$element = $dest->end()->find('dest')->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertInstanceOf(DOMElement::class, $element->previousSibling);
		$this->assertSame('li', $element->previousSibling->tagName);
	}

	public function testInsertAfter() : void
	{
		$file = DATA_FILE;
		$dest = qp('<?xml version="1.0"?><root><dest/></root>', 'dest');
		qp($file, 'li')->insertAfter($dest);
		$this->assertSame(5, $dest->top(':root > li')->count());
	}

	public function testReplaceWith() : void
	{
		$file = DATA_FILE;
		$qp = qp($file, 'unary')->replaceWith('<test><foo/></test>')->top('test');
		$this->assertSame(1, $qp->count());

		$qp = qp($file, 'unary')->replaceWith(qp('<?xml version="1.0"?><root><test/><test/></root>', 'test'));
		$this->assertSame(2, $qp->top()->find('test')->count());
	}

	public function testReplaceAll() : void
	{
		$qp1 = qp('<?xml version="1.0"?><root><l/><l/></root>');
		$element = qp('<?xml version="1.0"?><bob><m/><m/></bob>')->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$doc = $element->ownerDocument;
		$this->assertInstanceOf(DOMDocument::class, $doc);

		$qp2 = $qp1->find('l')->replaceAll('m', $doc);

		$this->assertSame(2, $qp2->top()->find('l')->count());
	}

	public function testUnwrap() : void
	{
		// Unwrap center, and make sure junk goes away.
		$xml = '<?xml version="1.0"?><root><wrapper><center/><junk/></wrapper></root>';
		$qp = qp($xml, 'center')->unwrap();
		$this->assertSame('root', $qp->top('center')->parent()->tag());
		$this->assertSame(0, $qp->top('junk')->count());

		// Make sure it works on two nodes in the same parent.
		$xml = '<?xml version="1.0"?><root><wrapper><center id="1"/><center id="2"/></wrapper></root>';
		$qp = qp($xml, 'center')->unwrap();

		// Make sure they were unwrapped
		$this->assertSame('root', $qp->top('center')->parent()->tag());

		// Make sure both get copied.
		$this->assertSame(2, $qp->top('center')->count());

		// Make sure they are in order.
		$this->assertSame('2', $qp->top('center:last')->attr('id'));

		// Test on root element.
		$xml = '<?xml version="1.0"?><root><center/></root>';
		$qp = qp($xml, 'center')->unwrap();
		$this->assertSame('center', $qp->top()->tag());
	}

	public function testFailedUnwrap() : void
	{
		// Cannot unwrap the root element.
		$xml = '<?xml version="1.0"?><root></root>';
		$this->expectException(\QueryPath\Exception::class);
		$qp = qp($xml, 'root')->unwrap();
		$this->assertSame('center', $qp->top()->tag());
	}

	public function testWrap() : void
	{
		$file = DATA_FILE;

		$element = qp($file, 'unary')->wrap('<test id="testWrap"></test>')->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertInstanceOf(DOMDocument::class, $element->ownerDocument);
		$xml = $element->ownerDocument->saveXML();
		$element = qp($xml, '#testWrap')->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertSame(1, $element->childNodes->length);

		$element = qp($file,
			'unary')->wrap(qp('<?xml version="1.0"?><root><test id="testWrap"></test><test id="ignored"></test></root>',
			'test'))->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertInstanceOf(DOMDocument::class, $element->ownerDocument);
		$xml = $element->ownerDocument->saveXML();
		$element = qp($xml, '#testWrap')->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertSame(1, $element->childNodes->length);

		$element = qp($file, 'li')->wrap('<test class="testWrap"></test>')->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertInstanceOf(DOMDocument::class, $element->ownerDocument);
		$xml = $element->ownerDocument->saveXML();
		$this->assertSame(5, qp($xml, '.testWrap')->count());

		$element = qp($file, 'li')->wrap('<test class="testWrap"><inside><center/></inside></test>')->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertInstanceOf(DOMDocument::class, $element->ownerDocument);
		$xml = $element->ownerDocument->saveXML();
		$this->assertSame(5, qp($xml, '.testWrap > inside > center > li')->count());
	}

	public function testWrapAll() : void
	{
		$file = DATA_FILE;

		$qp = qp($file, 'unary')->wrapAll('<test id="testWrap"></test>');
		$this->assertInstanceOf(DOMQuery::class, $qp);
		$element = $qp->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertInstanceOf(DOMDocument::class, $element->ownerDocument);
		$xml = $element->ownerDocument->saveXML();
		$element = qp($xml, '#testWrap')->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertSame(1, $element->childNodes->length);

		$qp = qp($file,
			'unary')->wrapAll(qp('<?xml version="1.0"?><root><test id="testWrap"></test><test id="ignored"></test></root>',
			'test'));
		$this->assertInstanceOf(DOMQuery::class, $qp);
		$a = $qp->get(0);
		$this->assertInstanceOf(DOMNode::class, $a);
		$this->assertInstanceOf(DOMDocument::class, $a->ownerDocument);
		$xml = $a->ownerDocument->saveXML();
		$element = qp($xml, '#testWrap')->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertSame(1, $element->childNodes->length);

		$qp = qp($file, 'li')->wrapAll('<test class="testWrap"><inside><center/></inside></test>');
		$this->assertInstanceOf(DOMQuery::class, $qp);
		$a = $qp->get(0);
		$this->assertInstanceOf(DOMElement::class, $a);
		$this->assertInstanceOf(DOMDocument::class, $a->ownerDocument);
		$xml = $a->ownerDocument->saveXML();
		$this->assertSame(5, qp($xml, '.testWrap > inside > center > li')->count());
	}

	public function testWrapInner() : void
	{
		$file = DATA_FILE;

		$this->assertTrue(qp($file, '#inner-one')->wrapInner('') instanceof DOMQuery);

		$a = qp($file, '#inner-one')->wrapInner('<test class="testWrap"></test>')->get(0);
		$this->assertInstanceOf(DOMNode::class, $a);
		$this->assertInstanceOf(DOMDocument::class, $a->ownerDocument);
		$xml = $a->ownerDocument->saveXML();
		$a = qp($xml, '.testWrap')->get(0);
		$this->assertInstanceOf(DOMNode::class, $a);
		// FIXME: 9 includes text nodes. Should fix this.
		$this->assertSame(9, $a->childNodes->length);

		$element = qp($file, 'inner')->wrapInner('<test class="testWrap"></test>')->get(0);
		$this->assertInstanceOf(DOMElement::class, $element);
		$this->assertInstanceOf(DOMDocument::class, $element->ownerDocument);
		$xml = $element->ownerDocument->saveXML();
		$a = qp($xml, '.testWrap')->get(0);
		$b = qp($xml, '.testWrap')->get(1);
		$this->assertInstanceOf(DOMElement::class, $a);
		$this->assertInstanceOf(DOMElement::class, $b);
		$this->assertSame(9, $a->childNodes->length);
		$this->assertSame(3, $b->childNodes->length);

		$qp = qp($file,
			'inner')->wrapInner(qp('<?xml version="1.0"?><root><test class="testWrap"/><test class="ignored"/></root>',
			'test'));
		$this->assertSame(2, $qp->find('inner > .testWrap')->count());
		$this->assertSame(0, $qp->find('.ignore')->count());
	}

	public function testRemove() : void
	{
		$file = DATA_FILE;
		$qp = qp($file, 'li');
		$start = $qp->count();
		$finish = $qp->remove()->count();
		$this->assertSame($start, $finish);
		$this->assertSame(0, $qp->find(':root li')->count());

		// Test for Issue #55
		$data = '<?xml version="1.0"?><root><a>test</a><b> FAIL</b></root>';
		$qp = qp($data);
		$rem = $qp->remove('b');

		$this->assertSame(' FAIL', $rem->text());
		$this->assertSame('test', $qp->text());

		// Test for Issue #63
		$qp = qp($data);
		$rem = $qp->remove('noSuchElement');
		$this->assertCount(0, $rem);
	}

	public function testHasClass() : void
	{
		$file = DATA_FILE;
		$this->assertTrue(qp($file, '#inner-one')->hasClass('innerClass'));

		$file = DATA_FILE;
		$this->assertFalse(qp($file, '#inner-one')->hasClass('noSuchClass'));
	}

	public function testAddClass() : void
	{
		$file = DATA_FILE;
		$this->assertTrue(qp($file, '#inner-one')->addClass('testClass')->hasClass('testClass'));
	}

	public function testRemoveClass() : void
	{
		$file = DATA_FILE;
		// The add class tests to make sure that this works with multiple values.
		$this->assertFalse(qp($file, '#inner-one')->removeClass('innerClass')->hasClass('innerClass'));
		$this->assertTrue(qp($file,
			'#inner-one')->addClass('testClass')->removeClass('innerClass')->hasClass('testClass'));
	}

	public function testAdd() : void
	{
		$file = DATA_FILE;
		$this->assertSame(7, qp($file, 'li')->add('inner')->count());
	}

	public function testEnd() : void
	{
		$file = DATA_FILE;
		$qp = qp($file, 'inner')->find('li');
		$this->assertInstanceOf(DOMQuery::class, $qp);
		$this->assertSame(2, $qp->end()->count());
	}

	public function testAndSelf() : void
	{
		$file = DATA_FILE;
		$qp = qp($file, 'inner')->find('li');
		$this->assertInstanceOf(DOMQuery::class, $qp);
		$this->assertSame(7, $qp->andSelf()->count());
	}

	public function testChildren() : void
	{
		$file = DATA_FILE;
		$this->assertSame(5, qp($file, 'inner')->children()->count());
		/** @var DOMQuery */
		foreach (qp($file, 'inner')->children('li') as $kid) {
			$this->assertSame('li', $kid->tag());
		}
		$this->assertSame(5, qp($file, 'inner')->children('li')->count());
		$this->assertSame(1, qp($file, ':root')->children('unary')->count());
	}

	public function testRemoveChildren() : void
	{
		$file = DATA_FILE;
		$this->assertSame(0, qp($file, '#inner-one')->removeChildren()->find('li')->count());
	}

	public function testContents() : void
	{
		$file = DATA_FILE;
		$this->assertGreaterThan(5, qp($file, 'inner')->contents()->count());
		// Two cdata nodes and one element node.
		$this->assertSame(3, qp($file, '#inner-two')->contents()->count());

		// Issue #51: Under certain recursive conditions, this returns error.
		// Warning: Whitespace is important in the markup beneath.
		$xml = '<html><body><div>Hello
        <div>how are you
          <div>fine thank you
            <div>and you ?</div>
          </div>
        </div>
      </div>
    </body></html>';
		$cr = $this->contentsRecurse(qp($xml));
		$this->assertCount(14, $cr, implode("\n", $cr));
	}

	public function testNS() : void
	{
		$xml = '<?xml version="1.0"?><root xmlns="foo:bar"><e>test</e></root>';

		$q = qp($xml, 'e');

		$this->assertSame(1, $q->count());

		$this->assertSame('foo:bar', $q->ns());
	}

	public function testSiblings() : void
	{
		$file = DATA_FILE;
		$this->assertSame(3, qp($file, '#one')->siblings()->count());
		$this->assertSame(2, qp($file, 'unary')->siblings('inner')->count());
	}

	public function testHTML() : void
	{
		$file = DATA_FILE;
		$qp = qp($file, 'unary');
		$html = '<b>test</b>';
		$qp_qp = $qp->html($html);
		$this->assertInstanceOf(DOMQuery::class, $qp_qp);
		$this->assertSame($html, $qp_qp->find('b')->html());

		$html = '<html><head><title>foo</title></head><body>bar</body></html>';
		// We expect a DocType to be prepended:
		$qp_html = qp($html)->html();
		$this->assertIsString($qp_html);
		$this->assertSame('<!DOCTYPE', substr($qp_html, 0, 9));

		// Check that HTML is not added to empty finds. Note the # is for a special
		// case.
		$qp_html = qp($html, '#nonexistant')->html('<p>Hello</p>')->html();
		$this->assertSame('', (string) $qp_html);
		$qp_html = qp($html, 'nonexistant')->html('<p>Hello</p>')->html();
		$this->assertSame('', (string) $qp_html);

		// We expect NULL if the document is empty.
		$this->assertNull(qp()->html());

		// Non-DOMNodes should not be rendered:
		$fn = $this->mapCallbackFunction();
		$this->assertNull(qp($file, 'li')->map($fn)->html());
	}

	public function testInnerHTML() : void
	{
		$html = '<html><head></head><body><div id="me">Test<p>Again</p></div></body></html>';

		$this->assertSame('Test<p>Again</p>', qp($html, '#me')->innerHTML());
	}

	public function testInnerXML() : void
	{
		$html = '<?xml version="1.0"?><root><div id="me">Test<p>Again1</p></div></root>';
		$test = 'Test<p>Again1</p>';

		$this->assertSame($test, qp($html, '#me')->innerXML());

		$html = '<?xml version="1.0"?><root><div id="me">Test<p>Again2<br/></p><![CDATA[Hello]]><?pi foo ?></div></root>';
		$test = 'Test<p>Again2<br/></p><![CDATA[Hello]]><?pi foo ?>';

		$this->assertSame($test, qp($html, '#me')->innerXML());

		$html = '<?xml version="1.0"?><root><div id="me"/></root>';
		$test = '';
		$this->assertSame($test, qp($html, '#me')->innerXML());

		$html = '<?xml version="1.0"?><root id="me">test</root>';
		$test = 'test';
		$this->assertSame($test, qp($html, '#me')->innerXML());
	}

	public function testInnerXHTML() : void
	{
		$html = '<?xml version="1.0"?><html><head></head><body><div id="me">Test<p>Again</p></div></body></html>';

		$this->assertSame('Test<p>Again</p>', qp($html, '#me')->innerXHTML());

		// Regression for issue #10: Tags should not be unary (e.g. we want <script></script>, not <script/>)
		$xml = '<html><head><title>foo</title></head><body><div id="me">Test<p>Again<br/></p></div></body></html>';
		// Look for a closing </br> tag
		$regex = '/<\/br>/';
		$xhtml = qp($xml, '#me')->innerXHTML();
		$this->assertIsString($xhtml);
		$this->assertMatchesRegularExpression($regex, $xhtml, 'BR should have a closing tag.');
	}

	public function testXML() : void
	{
		$file = DATA_FILE;
		$qp = qp($file, 'unary');
		$xml = '<b>test</b>';
		$xmlparse = $qp->xml($xml);
		$this->assertInstanceOf(DOMQuery::class, $xmlparse);
		$xmlfind = $xmlparse->find('b');
		$this->assertInstanceOf(Query::class, $xmlfind);
		$this->assertSame($xml, $xmlfind->xml());

		$xml = '<html><head><title>foo</title></head><body>bar</body></html>';
		$xmlstr = qp($xml, 'html')->xml();
		$this->assertIsString($xmlstr);
		// We expect an XML declaration to be prepended:
		$this->assertSame('<?xml', substr($xmlstr, 0, 5));

		// We don't want an XM/L declaration if xml(TRUE).
		$xml = '<?xml version="1.0"?><foo/>';
		$xmlstr = qp($xml)->xml(true);
		$this->assertIsString($xmlstr);
		$this->assertFalse(strpos($xmlstr, '<?xml'));

		// We expect NULL if the document is empty.
		$this->assertNull(qp()->xml());

		// Non-DOMNodes should not be rendered:
		$fn = $this->mapCallbackFunction();
		$this->assertNull(qp($file, 'li')->map($fn)->xml());
	}

	public function testXHTML() : void
	{
		// throw new Exception();

		$file = DATA_FILE;
		$qp = qp($file, 'unary');
		$xml = '<b>test</b>';
		$xmlparse = $qp->xml($xml);
		$this->assertInstanceOf(DOMQuery::class, $xmlparse);
		$xmlfind = $xmlparse->find('b');
		$this->assertInstanceOf(Query::class, $xmlfind);
		$this->assertSame($xml, $xmlfind->xhtml());

		$xml = '<html><head><title>foo</title></head><body>bar</body></html>';
		$xhtml = qp($xml, 'html')->xhtml();
		// We expect an XML declaration to be prepended:
		$this->assertIsString($xhtml);
		$this->assertSame('<?xml', substr($xhtml, 0, 5));

		// We don't want an XM/L declaration if xml(TRUE).
		$xml = '<?xml version="1.0"?><foo/>';
		$xhtml = qp($xml)->xhtml(true);
		$this->assertIsString($xhtml);
		$this->assertFalse(strpos($xhtml, '<?xml'));

		// We expect NULL if the document is empty.
		$this->assertNull(qp()->xhtml());

		// Non-DOMNodes should not be rendered:
		$fn = $this->mapCallbackFunction();
		$this->assertNull(qp($file, 'li')->map($fn)->xhtml());

		// Regression for issue #10: Tags should not be unary (e.g. we want <script></script>, not <script/>)
		$xml = '<html><head><title>foo</title></head>
      <body>
      bar<br/><hr width="100">
      <script></script>
      <script>
      alert("Foo");
      </script>
      <frameset id="fooframeset"></frameset>
      </body></html>';

		$xhtml = qp($xml)->xhtml();
		$this->assertIsString($xhtml);

		//throw new Exception($xhtml);

		// Look for a properly formatted BR unary tag:
		$regex = '/<br \/>/';
		$this->assertMatchesRegularExpression($regex, $xhtml, 'BR should have a closing tag.');

		// Look for a properly formatted HR tag:
		$regex = '/<hr width="100" \/>/';
		$this->assertMatchesRegularExpression($regex, $xhtml, 'BR should have a closing tag.');

		// Ensure that script tag is not collapsed:
		$regex = '/<script><\/script>/';
		$this->assertMatchesRegularExpression($regex, $xhtml, 'BR should have a closing tag.');

		// Ensure that frameset tag is not collapsed (it looks like <frame>):
		$regex = '/<frameset id="fooframeset"><\/frameset>/';
		$this->assertMatchesRegularExpression($regex, $xhtml, 'BR should have a closing tag.');

		// Ensure that script gets wrapped in CDATA:
		$find = '/* <![CDATA[ ';
		$this->assertTrue(strpos($xhtml, $find) > 0, 'CDATA section should be escaped.');

		// Regression: Make sure it parses.
		$xhtml = qp('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html><head></head><body><br /></body></html>')->xhtml();

		qp($xhtml);
	}

	public function testWriteXML() : void
	{
		$xml = '<?xml version="1.0"?><html><head><title>foo</title></head><body>bar</body></html>';

		if ( ! ob_start()) {
			exit('Could not start OB.');
		}
		qp($xml, 'tml')->writeXML();
		$out = ob_get_contents();
		ob_end_clean();

		// We expect an XML declaration at the top.
		$this->assertSame('<?xml', substr($out, 0, 5));

		$xml = '<?xml version="1.0"?><html><head><script>
    <!--
    1 < 2;
    -->
    </script>
    <![CDATA[This is CDATA]]>
    <title>foo</title></head><body>bar</body></html>';

		if ( ! ob_start()) {
			exit('Could not start OB.');
		}
		qp($xml, 'tml')->writeXML();
		$out = ob_get_contents();
		ob_end_clean();

		// We expect an XML declaration at the top.
		$this->assertSame('<?xml', substr($out, 0, 5));

		// Test writing to a file:
		$name = './' . __FUNCTION__ . '.xml';
		qp($xml)->writeXML($name);
		$this->assertFileExists($name);
		$this->assertTrue(qp($name) instanceof DOMQuery);
		unlink($name);
	}

	public function testWriteXHTML() : void
	{
		$xml = '<?xml version="1.0"?><html><head><title>foo</title></head><body>bar</body></html>';

		if ( ! ob_start()) {
			exit('Could not start OB.');
		}
		qp($xml, 'tml')->writeXHTML();
		$out = ob_get_contents();
		ob_end_clean();

		// We expect an XML declaration at the top.
		$this->assertSame('<?xml', substr($out, 0, 5));

		$xml = '<?xml version="1.0"?><html><head><script>
    <!--
    1 < 2;
    -->
    </script>
    <![CDATA[This is CDATA]]>
    <title>foo</title></head><body>bar</body></html>';

		if ( ! ob_start()) {
			exit('Could not start OB.');
		}
		qp($xml, 'html')->writeXHTML();
		$out = ob_get_contents();
		ob_end_clean();

		// We expect an XML declaration at the top.
		$this->assertSame('<?xml', substr($out, 0, 5));

		// Test writing to a file:
		$name = './' . __FUNCTION__ . '.xml';
		qp($xml)->writeXHTML($name);
		$this->assertFileExists($name);
		$this->assertTrue(qp($name) instanceof DOMQuery);
		unlink($name);

		// Regression for issue #10 (keep closing tags in XHTML)
		$xhtml = '<?xml version="1.0"?><html><head><title>foo</title><script></script><br/></head><body>bar</body></html>';
		if ( ! ob_start()) {
			exit('Could not start OB.');
		}
		qp($xhtml, 'html')->writeXHTML();
		$out = ob_get_contents();
		ob_end_clean();

		$pattern = '/<\/script>/';
		$this->assertMatchesRegularExpression($pattern, $out, 'Should be closing script tag.');

		$pattern = '/<\/br>/';
		$this->assertMatchesRegularExpression($pattern, $out, 'Should be closing br tag.');
	}

	public function testFailWriteXML() : void
	{
		$this->expectException(\QueryPath\IOException::class);
		qp()->writeXML('/dev/null');
	}

	public function testFailWriteXHTML() : void
	{
		$this->expectException(\QueryPath\IOException::class);
		qp()->writeXHTML('/dev/null');
	}

	public function testFailWriteHTML() : void
	{
		$this->expectException(\QueryPath\IOException::class);
		qp('<?xml version="1.0"?><foo/>')->writeXML('/dev/null');
	}

	public function testWriteHTML() : void
	{
		$xml = '<html><head><title>foo</title></head><body>bar</body></html>';

		if ( ! ob_start()) {
			exit('Could not start OB.');
		}
		qp($xml, 'tml')->writeHTML();
		$out = ob_get_contents();
		ob_end_clean();

		// We expect a doctype declaration at the top.
		$this->assertSame('<!DOC', substr($out, 0, 5));

		$xml = '<html><head><title>foo</title>
    <script><!--
    var foo = 1 < 5;
    --></script>
    </head><body>bar</body></html>';

		if ( ! ob_start()) {
			exit('Could not start OB.');
		}
		qp($xml, 'tml')->writeHTML();
		$out = ob_get_contents();
		ob_end_clean();

		// We expect a doctype declaration at the top.
		$this->assertSame('<!DOC', substr($out, 0, 5));

		$xml = '<html><head><title>foo</title>
    <script><![CDATA[
    var foo = 1 < 5;
    ]]></script>
    </head><body>bar</body></html>';

		if ( ! ob_start()) {
			exit('Could not start OB.');
		}
		qp($xml, 'tml')->writeHTML();
		$out = ob_get_contents();
		ob_end_clean();

		// We expect a doctype declaration at the top.
		$this->assertSame('<!DOC', substr($out, 0, 5));

		// Test writing to a file:
		$name = './' . __FUNCTION__ . '.html';
		qp($xml)->writeXML($name);
		$this->assertFileExists($name);
		$this->assertTrue(qp($name) instanceof DOMQuery);
		unlink($name);
	}

	public function testWriteHTML5() : void
	{
		$xml = '<html><head><title>foo</title></head><body>bar</body></html>';

		if ( ! ob_start()) {
			exit('Could not start OB.');
		}
		qp($xml, 'tml')->writeHTML5();
		$out = ob_get_contents();
		ob_end_clean();

		// We expect a doctype declaration at the top.
		$this->assertSame('<!DOC', substr($out, 0, 5));

		$xml = '<html><head><title>foo</title>
    <script><!--
    var foo = 1 < 5;
    --></script>
    </head><body>bar</body></html>';

		if ( ! ob_start()) {
			exit('Could not start OB.');
		}
		qp($xml, 'tml')->writeHTML5();
		$out = ob_get_contents();
		ob_end_clean();

		// We expect a doctype declaration at the top.
		$this->assertSame('<!DOC', substr($out, 0, 5));

		$xml = '<html><head><title>foo</title>
    <script><![CDATA[
    var foo = 1 < 5;
    ]]></script>
    </head><body>bar</body></html>';

		if ( ! ob_start()) {
			exit('Could not start OB.');
		}
		qp($xml, 'tml')->writeHTML5();
		$out = ob_get_contents();
		ob_end_clean();

		// We expect a doctype declaration at the top.
		$this->assertSame('<!DOC', substr($out, 0, 5));

		// Test writing to a file:
		$name = './' . __FUNCTION__ . '.html';
		qp($xml)->writeXML($name);
		$this->assertFileExists($name);
		$this->assertTrue(qp($name) instanceof DOMQuery);
		unlink($name);
	}

	public function testText() : void
	{
		$xml = '<?xml version="1.0"?><root><div>Text A</div><div>Text B</div></root>';
		$this->assertSame('Text AText B', qp($xml)->text());
		$this->assertSame('Foo', qp($xml, 'div')->eq(0)->text('Foo')->text());
		$this->assertSame('BarBar', qp($xml, 'div')->text('Bar')->text());
	}

	public function testTextAfter() : void
	{
		$xml = '<?xml version="1.0"?><root><br/>After<foo/><br/>After2<div/>After3</root>';
		$this->assertSame('AfterAfter2', qp($xml, 'br')->textAfter());
		$this->assertSame('Blarg', qp($xml, 'foo')->textAfter('Blarg')->top('foo')->textAfter());
	}

	public function testTextBefore() : void
	{
		$xml = '<?xml version="1.0"?><root>Before<br/><foo/>Before2<br/>Before3<div/></root>';
		$this->assertSame('BeforeBefore2', qp($xml, 'br')->textBefore());
		$this->assertSame('Blarg', qp($xml, 'foo')->textBefore('Blarg')->top('foo')->textBefore());
	}

	public function testTextImplode() : void
	{
		$xml = '<?xml version="1.0"?><root><div>Text A</div><div>Text B</div></root>';
		$this->assertSame('Text A, Text B', qp($xml, 'div')->textImplode());
		$this->assertSame('Text A--Text B', qp($xml, 'div')->textImplode('--'));

		$xml = '<?xml version="1.0"?><root><div>Text A </div><div>Text B</div></root>';
		$this->assertSame('Text A , Text B', qp($xml, 'div')->textImplode());

		$xml = '<?xml version="1.0"?><root><div>Text A </div>
    <div>
    </div><div>Text B</div></root>';
		$this->assertSame('Text A , Text B', qp($xml, 'div')->textImplode(', ', true));

		// Test with empties
		$xml = '<?xml version="1.0"?><root><div>Text A</div><div> </div><div>Text B</div></root>';
		$this->assertSame('Text A- -Text B', qp($xml, 'div')->textImplode('-', false));
	}

	public function testChildrenText() : void
	{
		$xml = '<?xml version="1.0"?><root><wrapper>
    NOT ME!
    <div>Text A </div>
    <div>
    </div><div>Text B</div></wrapper></root>';
		$this->assertSame('Text A , Text B', qp($xml, 'div')->childrenText(', '), 'Just inner text.');
	}

	public function testNext() : void
	{
		$file = DATA_FILE;
		$this->assertSame('inner', qp($file, 'unary')->next()->tag());
		$this->assertSame('foot', qp($file, 'inner')->next()->eq(1)->tag());

		$this->assertSame('foot', qp($file, 'unary')->next('foot')->tag());

		// Regression test for issue eabrand identified:

		$qp = qp(QueryPath::HTML_STUB, 'body')->append('<div></div><p>Hello</p><p>Goodbye</p>')
			->children('p')
			->after('<p>new paragraph</p>')
		;

		$testarray = ['new paragraph', 'Goodbye', 'new paragraph'];

		$qp = $qp->top('p:first-of-type');
		$this->assertSame('Hello', $qp->text(), 'Test First P ' . (string) $qp->top()->html());
		$i = 0;
		while (null !== $qp->next('p')->html()) {
			$qp = $qp->next('p');
			$this->assertCount(1, $qp);
			$xml = $qp->top()->xml();
			$this->assertIsString($xml);
			$this->assertSame($testarray[$i], $qp->text(), $i . " didn't match " . $xml);
			++$i;
		}
		$this->assertSame(3, $i);
	}

	public function testPrev() : void
	{
		$file = DATA_FILE;
		$this->assertSame('head', qp($file, 'unary')->prev()->tag());
		$this->assertSame('inner', qp($file, 'inner')->prev()->eq(1)->tag());
		$this->assertSame('head', qp($file, 'foot')->prev('head')->tag());
	}

	public function testNextAll() : void
	{
		$file = DATA_FILE;
		$this->assertSame(3, qp($file, '#one')->nextAll()->count());
		$this->assertSame(2, qp($file, 'unary')->nextAll('inner')->count());
	}

	public function testPrevAll() : void
	{
		$file = DATA_FILE;
		$this->assertSame(3, qp($file, '#four')->prevAll()->count());
		$this->assertSame(2, qp($file, 'foot')->prevAll('inner')->count());
	}

	public function testParent() : void
	{
		$file = DATA_FILE;
		$this->assertSame('root', qp($file, 'unary')->parent()->tag());
		$this->assertSame('root', qp($file, 'li')->parent('root')->tag());
		$this->assertSame(2, qp($file, 'li')->parent()->count());
	}

	public function testClosest() : void
	{
		$file = DATA_FILE;
		$this->assertSame('root', qp($file, 'li')->parent('root')->tag());

		$xml = '<?xml version="1.0"?>
    <root>
      <a class="foo">
        <b/>
      </a>
      <b class="foo"/>
    </root>';
		$this->assertSame(2, qp($xml, 'b')->closest('.foo')->count());
	}

	public function testParents() : void
	{
		$file = DATA_FILE;

		// Three: two inners and a root.
		$this->assertSame(3, qp($file, 'li')->parents()->count());
		$this->assertSame('root', qp($file, 'li')->parents('root')->tag());
	}

	public function testCloneAll() : void
	{
		$file = DATA_FILE;
		// Shallow test
		$qp = qp($file, 'unary');
		$one = $qp->get(0);
		$two = $qp->cloneAll()->get(0);
		$this->assertTrue($one !== $two);
		$this->assertInstanceOf(DOMElement::class, $two);
		$this->assertSame('unary', $two->tagName);

		// Deep test: make sure children are also cloned.
		$qp = qp($file, 'inner');
		$one = $qp->find('li')->get(0);
		/** @var DOMElement $two */
		$two = $qp->top('inner')->cloneAll()->findInPlace('li')->get(0);
		$this->assertInstanceOf(DOMElement::class, $two);
		$this->assertNotSame($one, $two);
	}

	public function testBranch() : void
	{
		$qp = qp(QueryPath::HTML_STUB);
		$branch = $qp->branch();
		$branch->top('title')->text('Title');
		$qp->top('title')->text('FOOOOO')->top();
		$qp->find('body')->text('This is the body');

		$this->assertSame($qp->top('title')->text(), $branch->top('title')->text(), (string) $branch->top()->html());

		$qp = qp(QueryPath::HTML_STUB);
		$branch = $qp->branch('title');
		$branch->find('title')->text('Title');
		$qp->find('body')->text('This is the body');
		$this->assertSame($qp->top()->find('title')->text(), $branch->text());
	}

	public function testXpath() : void
	{
		$file = DATA_FILE;

		$this->assertSame('head', qp($file)->xpath("//*[@id='head']")->tag());
	}

	public function test__clone() : void
	{
		$file = DATA_FILE;

		$qp = qp($file, 'inner:first-of-type');
		$qp2 = clone $qp;
		$this->assertFalse($qp === $qp2);
		$qp2->findInPlace('li')->attr('foo', 'bar');
		$this->assertSame('', $qp->find('li')->attr('foo'));
		$xml = $qp2->top()->xml();
		$this->assertIsString($xml);
		$this->assertSame('bar', $qp2->attr('foo'), $xml);
	}

	public function testStub() : void
	{
		$this->assertSame(1, qp(QueryPath::HTML_STUB)->find('title')->count());
	}

	public function testIterator() : void
	{
		$qp = qp(QueryPath::HTML_STUB, 'body')->append('<li/><li/><li/><li/>');

		$this->assertSame(4, $qp->find('li')->count());
		$i = 0;
		foreach ($qp->find('li') as $li) {
			++$i;
			$this->assertInstanceOf(DOMQuery::class, $li);
			$li->text('foo');
		}
		$this->assertSame(4, $i);
		$this->assertSame('foofoofoofoo', $qp->top()->find('li')->text());
	}

	public function testModeratelySizedDocument() : void
	{
		$this->assertSame(1, qp(MEDIUM_FILE)->count());

		$contents = file_get_contents(MEDIUM_FILE);
		$this->assertSame(1, qp($contents)->count());
	}

	/**
	 * @deprecated
	 */
	public function testSize() : void
	{
		$file = DATA_FILE;
		$qp = qp($file, 'li');
		$this->assertSame(5, $qp->count());
	}

	public function testCount() : void
	{
		$file = DATA_FILE;
		$qp = qp($file, 'li');
		$this->assertSame(5, $qp->count());

		// Test that this is exposed to PHP's Countable logic.
		$this->assertCount(5, qp($file, 'li'));
	}

	public function testLength() : void
	{
		// Test that the length attribute works exactly the same as size.
		$file = DATA_FILE;
		$qp = qp($file, 'li');
		$this->assertSame(5, $qp->length);
	}

	public function testDocument() : void
	{
		$file = DATA_FILE;
		$doc1 = new DOMDocument('1.0');
		$doc1->load($file);
		$qp = qp($doc1);

		$this->assertSame($doc1, $qp->document());

		// Ensure that adding to the DOMDocument is accessible to QP:
		$ele = $doc1->createElement('testDocument');
		$doc1->documentElement->appendChild($ele);

		$this->assertSame(1, $qp->find('testDocument')->count());
	}

	public function testDetach() : void
	{
		$file = DATA_FILE;
		$qp = qp($file, 'li');
		$start = $qp->count();
		$finish = $qp->detach()->count();
		$this->assertSame($start, $finish);
		$this->assertSame(0, $qp->find(':root li')->count());
	}

	public function testAttach() : void
	{
		$file = DATA_FILE;
		$qp = qp($file, 'li');
		$dest = qp('<?xml version="1.0"?><root><dest/></root>', 'dest');
		$qp->attach($dest);
		$this->assertSame(5, $dest->find(':root li')->count());
	}

	public function testEmptyElement() : void
	{
		$file = DATA_FILE;
		$this->assertSame(0, qp($file, '#inner-two')->removeChildren()->find('li')->count());
		$this->assertSame('<inner id="inner-two"/>', qp($file, '#inner-two')->removeChildren()->html());

		// Make sure text children get wiped out, too.
		$this->assertSame('', qp($file, 'foot')->removeChildren()->text());
	}

	public function testHas() : void
	{
		$file = DATA_FILE;

		// Test with DOMNode object
		$qp = qp($file, 'foot');
		$selector = $qp->get(0);
		$qp = $qp->top('root')->has($selector);

		// This should have one element named 'root'.
		$this->assertSame(1, $qp->count(), 'One element is a parent of foot');
		$this->assertSame('root', $qp->tag(), 'Root has foot.');

		// Test with CSS selector
		$qp = qp($file, 'root')->has('foot');

		// This should have one element named 'root'.
		$this->assertSame(1, $qp->count(), 'One element is a parent of foot');
		$this->assertSame('root', $qp->tag(), 'Root has foot.');

		// Test multiple matches.
		$qp = qp($file, '#docRoot, #inner-two')->has('#five');
		$this->assertSame(2, $qp->count(), 'Two elements are parents of #five');
		$target = $qp->get(0);
		$this->assertInstanceOf(DOMElement::class, $target);
		$this->assertSame('inner', $target->tagName, 'Inner has li.');
	}

	public function testNextUntil() : void
	{
		$file = DATA_FILE;
		$this->assertSame(3, qp($file, '#one')->nextUntil()->count());
		$this->assertSame(2, qp($file, 'li')->nextUntil('#three')->count());
	}

	public function testPrevUntil() : void
	{
		$file = DATA_FILE;
		$this->assertSame(3, qp($file, '#four')->prevUntil()->count());
		$this->assertSame(2, qp($file, 'foot')->prevUntil('unary')->count());
	}

	public function testEven() : void
	{
		$file = DATA_FILE;
		$this->assertSame(1, qp($file, 'inner')->even()->count());
		$this->assertSame(2, qp($file, 'li')->even()->count());
	}

	public function testOdd() : void
	{
		$file = DATA_FILE;
		$this->assertSame(1, qp($file, 'inner')->odd()->count());
		$this->assertSame(3, qp($file, 'li')->odd()->count());
	}

	public function testFirst() : void
	{
		$file = DATA_FILE;
		$this->assertSame(1, qp($file, 'inner')->first()->count());
		$this->assertSame(1, qp($file, 'li')->first()->count());
		$this->assertSame('Hello', qp($file, 'li')->first()->text());
	}

	public function testFirstChild() : void
	{
		$file = DATA_FILE;
		$this->assertSame(1, qp($file, '#inner-one')->firstChild()->count());
		$this->assertSame('Hello', qp($file, '#inner-one')->firstChild()->text());
	}

	public function testLast() : void
	{
		$file = DATA_FILE;
		$this->assertSame(1, qp($file, 'inner')->last()->count());
		$this->assertSame(1, qp($file, 'li')->last()->count());
		$this->assertSame('', qp($file, 'li')->last()->text());
	}

	public function testLastChild() : void
	{
		$file = DATA_FILE;
		$this->assertSame(1, qp($file, '#inner-one')->lastChild()->count());
		$this->assertSame('Last', qp($file, '#inner-one')->lastChild()->text());
	}

	public function testParentsUntil() : void
	{
		$file = DATA_FILE;

		// Three: two inners and a root.
		$this->assertSame(3, qp($file, 'li')->parentsUntil()->count());
		$this->assertSame(2, qp($file, 'li')->parentsUntil('root')->count());
	}

	public function testSort() : void
	{
		$xml = '<?xml version="1.0"?><r><s/><i>1</i><i>5</i><i>2</i><i>1</i><e/></r>';

		// Canary.
		$qp = qp($xml, 'i');
		$expect = ['1', '5', '2', '1'];

		/** @var DOMQuery */
		foreach ($qp as $item) {
			$this->assertSame(array_shift($expect), $item->text());
		}

		// Test simple ordering.
		$comp =
			/**
			 * @return -1|0|1
			 */
			static function (DOMNode|TextContent $a, DOMNode|TextContent $b) : int {
				if ($a->textContent === $b->textContent) {
					return 0;
				}

				return $a->textContent > $b->textContent ? 1 : -1;
			};
		$qp = qp($xml, 'i')->sort($comp);
		$expect = ['1', '1', '2', '5'];
		/** @var DOMQuery */
		foreach ($qp as $item) {
			$this->assertSame(array_shift($expect), $item->text());
		}

		$comp =
			/**
			 * @return -1|0|1
			 */
			static function (DOMNode|TextContent $a, DOMNode|TextContent $b) : int {
				$qpa = qp($a);
				$qpb = qp($b);

				if ($qpa->text() === $qpb->text()) {
					return 0;
				}

				return $qpa->text() > $qpb->text() ? 1 : -1;
			};
		$qp = qp($xml, 'i')->sort($comp);
		$expect = ['1', '1', '2', '5'];
		/** @var DOMQuery */
		foreach ($qp as $item) {
			$this->assertSame(array_shift($expect), $item->text());
		}

		// Test DOM re-ordering
		$comp =
			/**
			 * @return -1|0|1
			 */
			static function (DOMNode|TextContent $a, DOMNode|TextContent $b) : int {
				if ($a->textContent === $b->textContent) {
					return 0;
				}

				return $a->textContent > $b->textContent ? 1 : -1;
			};
		$qp = qp($xml, 'i')->sort($comp, true);
		$expect = ['1', '1', '2', '5'];
		/** @var DOMQuery */
		foreach ($qp as $item) {
			$this->assertSame(array_shift($expect), $item->text());
		}
		$res = $qp->top()->xml();
		$expect_xml = '<?xml version="1.0"?><r><s/><i>1</i><i>1</i><i>2</i><i>5</i><e/></r>';
		$this->assertIsString($res);
		$this->assertXmlStringEqualsXmlString($expect_xml, $res);
	}

	/**
	 * Regression test for issue #14.
	 */
	public function testRegressionFindOptimizations() : void
	{
		$xml = '<?xml version="1.0"?><root>
      <item id="outside">
        <item>
          <item id="inside">Test</item>
        </item>
      </item>
    </root>';

		// From inside, should not be able to find outside.
		$this->assertSame(0, qp($xml, '#inside')->find('#outside')->count());

		$xml = '<?xml version="1.0"?><root>
      <item class="outside">
        <item>
          <item class="inside">Test</item>
        </item>
      </item>
    </root>';
		// From inside, should not be able to find outside.
		$this->assertSame(0, qp($xml, '.inside')->find('.outside')->count());
	}

	public function testDataURL() : void
	{
		$text = 'Hi!'; // Base-64 encoded value would be SGkh
		$xml = '<?xml version="1.0"?><root><item/></root>';

		$qp = qp($xml, 'item')->dataURL('secret', $text, 'text/plain');

		$this->assertSame(1, $qp->top('item[secret]')->count(), 'One attr should be added.');

		$this->assertSame('data:text/plain;base64,SGkh', $qp->attr('secret'), 'Attr value should be data URL.');

		$result = $qp->dataURL('secret');
		$this->assertIsArray($result);
		$this->assertCount(2, $result, 'Should return two-array.');
		$this->assertSame($text, $result['data'], 'Should return original data, decoded.');
		$this->assertSame('text/plain', $result['mime'], 'Should return the original MIME');
	}

	public function testEncodeDataURL() : void
	{
		$data = QueryPath::encodeDataURL('Hi!', 'text/plain');
		$this->assertSame('data:text/plain;base64,SGkh', $data);
	}

	/**
	 * Helper function for testContents().
	 * Based on problem reported in issue 51.
	 *
	 * @param list<1> $pack
	 *
	 * @return list<1>
	 */
	private function contentsRecurse(DOMQuery $source, array &$pack = []) : array
	{
		$children = $source->contents();
		$pack[] = 1;

		/** @var DOMQuery */
		foreach ($children as $child) {
			$pack += $this->contentsRecurse($child, $pack);
		}

		return $pack;
	}
}
