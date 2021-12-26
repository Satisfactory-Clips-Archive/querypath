<?php

declare(strict_types=1);

namespace QueryPathTests\CSS;

use QueryPath\CSS\DOMTraverser;
use QueryPathTests\TestCase;

define('TRAVERSER_XML', __DIR__ . '/../../DOMTraverserTest.xml');

/**
 * @ingroup querypath_tests
 * @group   CSS
 */
class DOMTraverserTest extends TestCase
{
	protected $xml_file = TRAVERSER_XML;

	public function debug($msg)
	{
		fwrite(STDOUT, PHP_EOL . $msg);
	}

	public function testConstructor()
	{
		$dom = new \DOMDocument('1.0');
		$dom->load($this->xml_file);

		$splos = new \SPLObjectStorage();
		$splos->attach($dom);

		$traverser = new DOMTraverser($splos);

		$this->assertInstanceOf('\QueryPath\CSS\Traverser', $traverser);
		$this->assertInstanceOf('\QueryPath\CSS\DOMTraverser', $traverser);
	}

	protected function traverser()
	{
		$dom = new \DOMDocument('1.0');
		$dom->load($this->xml_file);

		$splos = new \SPLObjectStorage();
		$splos->attach($dom->documentElement);

		$traverser = new DOMTraverser($splos);

		return $traverser;
	}

	protected function find($selector)
	{
		return $this->traverser()->find($selector)->matches();
	}

	public function testFind()
	{
		$res = $this->traverser()->find('root');

		// Ensure that return contract is not violated.
		$this->assertInstanceOf('\QueryPath\CSS\Traverser', $res);
	}

	public function testMatches()
	{
		$res = $this->traverser()->matches();
		$this->assertCount(1, $res);
	}

	public function testMatchElement()
	{
		// Canary: If element does not exist, must return FALSE.
		$matches = $this->find('NO_SUCH_ELEMENT');
		$this->assertCount(0, $matches);

		// Test without namespace
		$matches = $this->find('root');
		$this->assertCount(1, $matches);

		$matches = $this->find('crowded');
		$this->assertCount(1, $matches);

		$matches = $this->find('outside');
		$this->assertCount(3, $matches);

		// Check nested elements.
		$matches = $this->find('a');
		$this->assertCount(3, $matches);

		// Test wildcard.
		$traverser = $this->traverser();
		$matches = $traverser->find('*')->matches();
		$actual = $traverser->getDocument()->getElementsByTagName('*');
		$this->assertSame($actual->length, count($matches));

		// Test with namespace
		//$this->markTestIncomplete();
		$matches = $this->find('ns_test');
		$this->assertCount(3, $matches);

		$matches = $this->find('test|ns_test');
		$this->assertCount(1, $matches);

		$matches = $this->find('test|ns_test>ns_attr');
		$this->assertCount(1, $matches);

		$matches = $this->find('*|ns_test');
		$this->assertCount(3, $matches);

		$matches = $this->find('test|*');
		$this->assertCount(1, $matches);

		// Test where namespace is declared on the element.
		$matches = $this->find('newns|my_element');
		$this->assertCount(1, $matches);

		$matches = $this->find('test|ns_test>newns|my_element');
		$this->assertCount(1, $matches);

		$matches = $this->find('test|*>newns|my_element');
		$this->assertCount(1, $matches);

		$matches = $this->find('*|ns_test>newns|my_element');
		$this->assertCount(1, $matches);

		$matches = $this->find('*|*>newns|my_element');
		$this->assertCount(1, $matches);

		$matches = $this->find('*>newns|my_element');
		$this->assertCount(1, $matches);
	}

	public function testMatchAttributes()
	{
		$matches = $this->find('crowded[attr1]');
		$this->assertCount(1, $matches);

		$matches = $this->find('crowded[attr1=one]');
		$this->assertCount(1, $matches);

		$matches = $this->find('crowded[attr2^=tw]');
		$this->assertCount(1, $matches);

		$matches = $this->find('classtest[class~=two]');
		$this->assertCount(1, $matches);
		$matches = $this->find('classtest[class~=one]');
		$this->assertCount(1, $matches);
		$matches = $this->find('classtest[class~=seven]');
		$this->assertCount(1, $matches);

		$matches = $this->find('crowded[attr0]');
		$this->assertCount(0, $matches);

		$matches = $this->find('[level=1]');
		$this->assertCount(3, $matches);

		$matches = $this->find('[attr1]');
		$this->assertCount(1, $matches);

		$matches = $this->find('[test|myattr]');
		$this->assertCount(1, $matches);

		$matches = $this->find('[test|myattr=foo]');
		$this->assertCount(1, $matches);

		$matches = $this->find('[*|myattr=foo]');
		$this->assertCount(1, $matches);

		$matches = $this->find('[|myattr=foo]');
		$this->assertCount(0, $matches);

		$matches = $this->find('[|level=1]');
		$this->assertCount(3, $matches);

		$matches = $this->find('[*|level=1]');
		$this->assertCount(4, $matches);

		// Test namespace on attr where namespace
		// is declared on that element
		$matches = $this->find('[nuther|ping]');
		$this->assertCount(1, $matches);

		// Test multiple namespaces on an element.
		$matches = $this->find('[*|ping=3]');
		$this->assertCount(1, $matches);

		// Test multiple namespaces on an element.
		$matches = $this->find('[*|ping]');
		$this->assertCount(1, $matches);
	}

	public function testMatchId()
	{
		$matches = $this->find('idtest#idtest-one');
		$this->assertCount(1, $matches);

		$matches = $this->find('#idtest-one');
		$this->assertCount(1, $matches);

		$matches = $this->find('outter#fake');
		$this->assertCount(0, $matches);

		$matches = $this->find('#fake');
		$this->assertCount(0, $matches);
	}

	public function testMatchClasses()
	{
		// Basic test.
		$matches = $this->find('a.a1');
		$this->assertCount(1, $matches);

		// Count multiple.
		$matches = $this->find('.first');
		$this->assertCount(2, $matches);

		// Grab one in the middle of a list.
		$matches = $this->find('.four');
		$this->assertCount(1, $matches);

		// One element with two classes.
		$matches = $this->find('.three.four');
		$this->assertCount(1, $matches);
	}

	public function testMatchPseudoClasses()
	{
		$matches = $this->find('ul>li:first');
		$this->assertCount(1, $matches);

		$matches = $this->find('ul>li:not(.first)');
		$this->assertCount(5, $matches);
	}

	public function testMatchPseudoElements()
	{
		$matches = $this->find('p::first-line');
		$this->assertCount(1, $matches);

		$matches = $this->find('p::first-letter');
		$this->assertCount(1, $matches);

		$matches = $this->find('p::before');
		$this->assertCount(1, $matches);

		$matches = $this->find('p::after');
		$this->assertCount(1, $matches);

		$matches = $this->find('bottom::after');
		$this->assertCount(0, $matches);
	}

	public function testCombineAdjacent()
	{
		// Simple test
		$matches = $this->find('idtest + p');
		$this->assertCount(1, $matches);
		foreach ($matches as $m) {
			$this->assertSame('p', $m->tagName);
		}

		// Test ignoring PCDATA
		$matches = $this->find('p + one');
		$this->assertCount(1, $matches);
		foreach ($matches as $m) {
			$this->assertSame('one', $m->tagName);
		}

		// Test that non-adjacent elements don't match.
		$matches = $this->find('idtest + one');
		foreach ($matches as $m) {
			$this->assertSame('one', $m->tagName);
		}
		$this->assertCount(0, $matches, 'Non-adjacents should not match.');

		// Test that elements BEFORE don't match
		$matches = $this->find('one + p');
		foreach ($matches as $m) {
			$this->assertSame('one', $m->tagName);
		}
		$this->assertCount(0, $matches, 'Match only if b is after a');
	}

	public function testCombineSibling()
	{
		// Canary:
		$matches = $this->find('one ~ two');
		$this->assertCount(0, $matches);

		// Canary 2:
		$matches = $this->find('NO_SUCH_ELEMENT ~ two');
		$this->assertCount(0, $matches);

		// Simple test
		$matches = $this->find('idtest ~ p');
		$this->assertCount(1, $matches);
		foreach ($matches as $m) {
			$this->assertSame('p', $m->tagName);
		}

		// Simple test
		$matches = $this->find('outside ~ p');
		$this->assertCount(1, $matches);
		foreach ($matches as $m) {
			$this->assertSame('p', $m->tagName);
		}

		// Matches only go left, not right.
		$matches = $this->find('p ~ outside');
		$this->assertCount(0, $matches);
	}

	public function testCombineDirectDescendant()
	{
		// Canary:
		$matches = $this->find('one > four');
		$this->assertCount(0, $matches);

		$matches = $this->find('two>three');
		$this->assertCount(1, $matches);
		foreach ($matches as $m) {
			$this->assertSame('three', $m->tagName);
		}

		$matches = $this->find('one > two > three');
		$this->assertCount(1, $matches);
		foreach ($matches as $m) {
			$this->assertSame('three', $m->tagName);
		}

		$matches = $this->find('a>a>a');
		$this->assertCount(1, $matches);

		$matches = $this->find('a>a');
		$this->assertCount(2, $matches);
	}

	public function testCombineAnyDescendant()
	{
		// Canary
		$matches = $this->find('four one');
		$this->assertCount(0, $matches);

		$matches = $this->find('one two');
		$this->assertCount(1, $matches);
		foreach ($matches as $m) {
			$this->assertSame('two', $m->tagName);
		}

		$matches = $this->find('one four');
		$this->assertCount(1, $matches);

		$matches = $this->find('a a');
		$this->assertCount(2, $matches);

		$matches = $this->find('root two four');
		$this->assertCount(1, $matches);
	}

	public function testMultipleSelectors()
	{
		// fprintf(STDOUT, "=========TEST=========\n\n");
		$matches = $this->find('one, two');
		$this->assertCount(2, $matches);
	}
}

