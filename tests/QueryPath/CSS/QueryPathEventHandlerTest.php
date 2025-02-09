<?php

declare(strict_types=1);

namespace QueryPathTests\CSS;

use DomDocument;
use DOMElement;
use DOMNode;
use const LIBXML_NOBLANKS;
use QueryPath\CSS\QueryPathEventHandler;
use QueryPath\TextContent;
use QueryPathTests\TestCase;
use SplObjectStorage;
use UnexpectedValueException;

/**
 * Tests for QueryPathEventHandler class.
 *
 * @ingroup querypath_tests
 * @group   deprecated
 */
class QueryPathEventHandlerTest extends TestCase
{
	public const xml = '<?xml version="1.0" ?>
  <html>
  <head>
    <title>This is the title</title>
  </head>
  <body>
    <div id="one">
      <div id="two" class="class-one">
        <div id="three">
        Inner text.
        </div>
      </div>
    </div>
    <span class="class-two">Nada</span>
    <p><p><p><p><p><p><p class="Odd"><p>8</p></p></p></p></p></p></p></p>
    <ul>
      <li class="Odd" id="li-one">Odd</li>
      <li class="even" id="li-two">Even</li>
      <li class="Odd" id="li-three">Odd</li>
      <li class="even" id="li-four">Even</li>
      <li class="Odd" id="li-five">Odd</li>
      <li class="even" id="li-six">Even</li>
      <li class="Odd" id="li-seven">Odd</li>
      <li class="even" id="li-eight">Even</li>
      <li class="Odd" id="li-nine">Odd</li>
      <li class="even" id="li-ten">Even</li>
    </ul>
  </body>
  </html>
  ';

	public function testGetMatches() : void
	{
		// Test root element:
		$xml = '<?xml version="1.0" ?><test><inside/>Text<inside/></test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test handing it a DOM Document
		$handler = new QueryPathEventHandler($doc);
		$matches = $handler->getMatches();
		$this->assertTrue(1 === $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertInstanceOf(DOMElement::class, $match);
		$this->assertSame('test', $match->tagName);

		// Test handling single element
		$root = $doc->documentElement;
		$handler = new QueryPathEventHandler($root);
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertInstanceOf(DOMElement::class, $match);
		$this->assertSame('test', $match->tagName);

		// Test handling a node list
		$eles = $doc->getElementsByTagName('inside');
		$handler = new QueryPathEventHandler($eles);
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertInstanceOf(DOMElement::class, $match);
		$this->assertSame('inside', $match->tagName);

		// Test handling an array of elements
		$eles = $doc->getElementsByTagName('inside');
		$array = [];
		foreach ($eles as $ele) {
			$array[] = $ele;
		}
		$handler = new QueryPathEventHandler($array);
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertInstanceOf(DOMElement::class, $match);
		$this->assertSame('inside', $match->tagName);
	}

	public function testEmptySelector() : void
	{
		$xml = '<?xml version="1.0" ?><t:test xmlns:t="urn:foo/bar"><t:inside id="first"/>Text<t:inside/><inside/></t:test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$this->expectException(\QueryPath\CSS\ParseException::class);

		// Basic test
		$handler = new QueryPathEventHandler($doc);
		$handler->find('');
		$matches = $handler->getMatches();
		$this->assertSame(0, $matches->count());
	}

	public function testElementNS() : void
	{
		$xml = '<?xml version="1.0" ?><t:test xmlns:t="urn:foo/bar"><t:inside id="first"/>Text<t:inside/><inside/></t:test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Basic test
		$handler = new QueryPathEventHandler($doc);
		$handler->find('t|inside');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertInstanceOf(DOMElement::class, $match);
		$this->assertSame('t:inside', $match->tagName);

		// Basic test
		$handler = new QueryPathEventHandler($doc);
		$handler->find('t|test');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertInstanceOf(DOMElement::class, $match);
		$this->assertSame('t:test', $match->tagName);
	}

	public function testFailedElementNS() : void
	{
		$xml = '<?xml version="1.0" ?><t:test xmlns:t="urn:foo/bar"><t:inside id="first"/>Text<t:inside/><inside/></t:test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$this->expectException(\QueryPath\CSS\ParseException::class);
		$handler->find('myns\:mytest');
	}

	public function testElement() : void
	{
		$xml = '<?xml version="1.0" ?><test><inside id="first"/>Text<inside/></test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Basic test
		$handler = new QueryPathEventHandler($doc);
		$handler->find('inside');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertInstanceOf(DOMElement::class, $match);
		$this->assertSame('inside', $match->tagName);

		$doc = new DomDocument();
		$doc->loadXML(self::xml);

		// Test getting nested
		$handler = new QueryPathEventHandler($doc);
		$handler->find('div');
		$matches = $handler->getMatches();
		$this->assertSame(3, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertInstanceOf(DOMElement::class, $match);
		$this->assertSame('div', $match->tagName);
		$this->assertSame('one', $match->getAttribute('id'));

		// Test getting a list
		$handler = new QueryPathEventHandler($doc);
		$handler->find('li');
		$matches = $handler->getMatches();
		$this->assertSame(10, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertInstanceOf(DOMElement::class, $match);
		//$this->assertEquals('div', $match->tagName);
		$this->assertSame('li-one', $match->getAttribute('id'));

		// Test getting the root element
		$handler = new QueryPathEventHandler($doc);
		$handler->find('html');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertInstanceOf(DOMElement::class, $match);
		$this->assertSame('html', $match->tagName);
	}

	public function testElementId() : void
	{
		// Test root element:
		$xml = '<?xml version="1.0" ?><test><inside id="first"/>Text<inside/></test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('#first');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertInstanceOf(DOMElement::class, $match);
		$this->assertSame('inside', $match->tagName);

		// Test a search with restricted scope:
		$handler = new QueryPathEventHandler($doc);
		$handler->find('inside#first');
		$matches = $handler->getMatches();

		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertInstanceOf(DOMElement::class, $match);
		$this->assertSame('inside', $match->tagName);
	}

	public function testAnyElementInNS() : void
	{
		$xml = '<?xml version="1.0" ?><ns1:test xmlns:ns1="urn:foo/bar"><ns1:inside/>Text<ns1:inside/></ns1:test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test handing it a DOM Document
		$handler = new QueryPathEventHandler($doc);
		$handler->find('ns1|*');
		$matches = $handler->getMatches();

		$this->assertSame(3, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertInstanceOf(DOMElement::class, $match);
		$this->assertSame('ns1:test', $match->tagName);

		// Test Issue #30:
		$xml = '<?xml version="1.0" ?>
    <ns1:test xmlns:ns1="urn:foo/bar">
      <ns1:inside>
        <ns1:insideInside>Test</ns1:insideInside>
      </ns1:inside>
    </ns1:test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('ns1|test>*');
		$matches = $handler->getMatches();

		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertInstanceOf(DOMElement::class, $match);
		$this->assertSame('ns1:inside', $match->tagName);
	}

	public function testAnyElement() : void
	{
		$xml = '<?xml version="1.0" ?><test><inside/>Text<inside/></test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test handing it a DOM Document
		$handler = new QueryPathEventHandler($doc);
		$handler->find('*');
		$matches = $handler->getMatches();

		$this->assertSame(3, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertInstanceOf(DOMElement::class, $match);
		$this->assertSame('test', $match->tagName);

		$doc = new DomDocument();
		$doc->loadXML(self::xml);

		// Test handing it a DOM Document
		$handler = new QueryPathEventHandler($doc);
		$handler->find('#two *');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('three', $match->getAttribute('id'));

		// Regression for issue #30
		$handler = new QueryPathEventHandler($doc);
		$handler->find('#one>*');
		$matches = $handler->getMatches();

		$this->assertSame(1, $matches->count(), 'Should match just top div.');
		$match = $this->firstMatch($matches);
		$this->assertSame('two', $match->getAttribute('id'), 'Should match ID #two');
	}

	public function testElementClass() : void
	{
		$xml = '<?xml version="1.0" ?><test><inside class="foo" id="one"/>Text<inside/></test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test basic class
		$handler = new QueryPathEventHandler($doc);
		$handler->find('.foo');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('one', $match->getAttribute('id'));

		// Test class in element
		$doc = new DomDocument();
		$doc->loadXML(self::xml);
		$handler = new QueryPathEventHandler($doc);
		$handler->find('li.Odd');
		$matches = $handler->getMatches();
		$this->assertSame(5, $matches->count());
		$match = $this->nthMatch($matches, 4);
		$this->assertSame('li-nine', $match->getAttribute('id'));

		// Test ID/class combo
		$handler = new QueryPathEventHandler($doc);
		$handler->find('.Odd#li-nine');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('li-nine', $match->getAttribute('id'));
	}

	public function testDirectDescendant() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <inside class="foo" id="one"/>
      Text
      <inside id="two">
        <inside id="inner-one"/>
      </inside>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test direct descendent
		$handler = new QueryPathEventHandler($doc);
		$handler->find('test > inside');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$match = $this->nthMatch($matches, 1);
		$this->assertSame('two', $match->getAttribute('id'));
	}

	public function testAttribute() : void
	{
		$xml = '<?xml version="1.0" ?><test><inside id="one" name="antidisestablishmentarianism"/>Text<inside/></test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test match on attr name
		$handler = new QueryPathEventHandler($doc);
		$handler->find('inside[name]');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('one', $match->getAttribute('id'));

		// Test broken form
		$handler = new QueryPathEventHandler($doc);
		$handler->find('inside[@name]');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('one', $match->getAttribute('id'));

		// Test match on attr name and equals value
		$handler = new QueryPathEventHandler($doc);
		$handler->find('inside[name="antidisestablishmentarianism"]');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('one', $match->getAttribute('id'));

		// Test match on containsInString
		$handler = new QueryPathEventHandler($doc);
		$handler->find('inside[name*="disestablish"]');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('one', $match->getAttribute('id'));

		// Test match on beginsWith
		$handler = new QueryPathEventHandler($doc);
		$handler->find('inside[name^="anti"]');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('one', $match->getAttribute('id'));

		// Test match on endsWith
		$handler = new QueryPathEventHandler($doc);
		$handler->find('inside[name$="ism"]');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('one', $match->getAttribute('id'));

		// Test containsWithSpace
		$xml = '<?xml version="1.0" ?><test><inside id="one" name="anti dis establishment arian ism"/>Text<inside/></test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('inside[name~="dis"]');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('one', $match->getAttribute('id'));

		// Test containsWithHyphen
		$xml = '<?xml version="1.0" ?><test><inside id="one" name="en-us"/>Text<inside/></test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('inside[name|="us"]');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('one', $match->getAttribute('id'));
	}

	public function testPseudoClassLang() : void
	{
		$xml = '<?xml version="1.0" ?><test><inside lang="en-us" id="one"/>Text<inside/></test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find(':lang(en-us)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('one', $match->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find('inside:lang(en)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('one', $match->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find('inside:lang(us)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('one', $match->getAttribute('id'));

		$xml = '<?xml version="1.0" ?><test><inside lang="en-us" id="one"/>Text<inside lang="us" id="two"/></test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find(':lang(us)');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$match = $this->nthMatch($matches, 1);
		$this->assertSame('two', $match->getAttribute('id'));

		$xml = '<?xml version="1.0" ?>
    <test xmlns="http://aleph-null.tv/xml" xmlns:xml="http://www.w3.org/XML/1998/namespace">
     <inside lang="en-us" id="one"/>Text
     <inside xml:lang="en-us" id="two"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find(':lang(us)');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$match = $this->nthMatch($matches, 1);
		$this->assertSame('two', $match->getAttribute('id'));
	}

	public function testPseudoClassEnabledDisabledChecked() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
     <inside enabled="enabled" id="one"/>Text
     <inside disabled="disabled" id="two"/>
     <inside checked="FOOOOO" id="three"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find(':enabled');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('one', $match->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find(':disabled');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('two', $match->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find(':checked()');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$match = $this->firstMatch($matches);
		$this->assertSame('three', $match->getAttribute('id'));
	}

	public function testPseudoClassLink() : void
	{
		$xml = '<?xml version="1.0"?><a><b href="foo"/><c href="foo"/></a>';
		$qp = qp($xml, ':link');
		$this->assertSame(2, $qp->count());
	}

	public function testPseudoClassXReset() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
     <inside enabled="enabled" id="one"/>Text
     <inside disabled="disabled" id="two"/>
     <inside checked="FOOOOO" id="three"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('inside');
		$matches = $handler->getMatches();
		$this->assertSame(3, $matches->count());
		$handler->find(':x-reset');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('test', $this->firstMatch($matches)->tagName);
	}

	public function testPseudoClassRoot() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
     <inside enabled="enabled" id="one"/>Text
     <inside disabled="disabled" id="two"/>
     <inside checked="FOOOOO" id="three"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);
		$start = $doc->getElementsByTagName('inside');

		// Start "deep in the doc" and traverse backward.
		$handler = new QueryPathEventHandler($start);
		$handler->find(':root');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('test', $this->firstMatch($matches)->tagName);
	}

	public function testPseudoClassNthChild() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <i class="odd" id="one"/>
      <i class="even" id="two"/>
      <i class="odd" id="three"/>
      <i class="even" id="four"/>
      <i class="odd" id="five"/>
      <e class="even" id="six"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test full list
		$handler = new QueryPathEventHandler($doc);
		$handler->find(':root :even');
		$matches = $handler->getMatches();
		$this->assertSame(3, $matches->count());
		$this->assertSame('four', $this->nthMatch($matches, 1)->getAttribute('id'));

		// Test restricted to specific element
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:even');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$this->assertSame('four', $this->nthMatch($matches, 1)->getAttribute('id'));

		// Test restricted to specific element, odd this time
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:odd');
		$matches = $handler->getMatches();
		$this->assertSame(3, $matches->count());
		$this->assertSame('three', $this->nthMatch($matches, 1)->getAttribute('id'));

		// Test nth-child(odd)
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:nth-child(odd)');
		$matches = $handler->getMatches();
		$this->assertSame(3, $matches->count());
		$this->assertSame('three', $this->nthMatch($matches, 1)->getAttribute('id'));

		// Test nth-child(2n+1)
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:nth-child(2n+1)');
		$matches = $handler->getMatches();
		$this->assertSame(3, $matches->count());
		$this->assertSame('three', $this->nthMatch($matches, 1)->getAttribute('id'));

		// Test nth-child(2n) (even)
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:nth-child(2n)');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$this->assertSame('four', $this->nthMatch($matches, 1)->getAttribute('id'));

		// Test nth-child(2n-1) (odd, equiv to 2n + 1)
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:nth-child(2n-1)');
		$matches = $handler->getMatches();
		$this->assertSame(3, $matches->count());
		$this->assertSame('three', $this->nthMatch($matches, 1)->getAttribute('id'));

		// Test nth-child(4n) (every fourth row)
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:nth-child(4n)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('four', $this->nthMatch($matches, 0)->getAttribute('id'));

		// Test nth-child(4n+1) (first of every four rows)
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:nth-child(4n+1)');
		$matches = $handler->getMatches();
		// Should match rows one and five
		$this->assertSame(2, $matches->count());
		$this->assertSame('five', $this->nthMatch($matches, 1)->getAttribute('id'));

		// Test nth-child(1) (First row)
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:nth-child(1)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('one', $this->firstMatch($matches)->getAttribute('id'));

		// Test nth-child(0n-0) (Empty list)
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:nth-child(0n-0)');
		$matches = $handler->getMatches();
		$this->assertSame(0, $matches->count());

		$xml = '<?xml version="1.0" ?>
    <test>
      <i class="odd" id="one"/>
      <i class="even" id="two"/>
      <i class="odd" id="three">
        <i class="odd" id="inner-one"/>
        <i class="even" id="inner-two"/>
      </i>
      <i class="even" id="four"/>
      <i class="odd" id="five"/>
      <e class="even" id="six"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test nested items.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:nth-child(odd)');
		$matches = $handler->getMatches();
		$this->assertSame(4, $matches->count());
		$matchIDs = [];
		foreach ($matches as $m) {
			static::assertInstanceOf(DOMElement::class, $m);
			$matchIDs[] = $m->getAttribute('id');
		}
		$this->assertSame(['one', 'three', 'inner-one', 'five'], $matchIDs);
	}

	public function testPseudoClassOnlyChild() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <i class="odd" id="one"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test single last child.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:only-child');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('one', $this->firstMatch($matches)->getAttribute('id'));

		$xml = '<?xml version="1.0" ?>
    <test>
      <i class="odd" id="one"/>
      <i class="odd" id="two"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test single last child.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:only-child');
		$matches = $handler->getMatches();
		$this->assertSame(0, $matches->count());
	}

	public function testPseudoClassOnlyOfType() : void
	{
		// TODO: Added this late (it was missing in original test),
		// and I'm not sure if the assumed behavior is correct.

		$xml = '<?xml version="1.0" ?>
    <test>
      <i class="odd" id="one"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test single last child.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:only-of-type');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('one', $this->firstMatch($matches)->getAttribute('id'));

		$xml = '<?xml version="1.0" ?>
    <test>
      <i class="odd" id="one"/>
      <i class="odd" id="two"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test single last child.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:only-of-type');
		$matches = $handler->getMatches();
		$this->assertSame(0, $matches->count());
	}

	public function testPseudoClassFirstChild() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <i class="odd" id="one"/>
      <i class="even" id="two"/>
      <i class="odd" id="three"/>
      <i class="even" id="four">
        <i class="odd" id="inner-one"/>
        <i class="even" id="inner-two"/>
        <i class="odd" id="inner-three"/>
        <i class="even" id="inner-four"/>
      </i>
      <i class="odd" id="five"/>
      <e class="even" id="six"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test single last child.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('#four > i:first-child');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('inner-one', $this->firstMatch($matches)->getAttribute('id'));

		// Test for two last children
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:first-child');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$this->assertSame('inner-one', $this->nthMatch($matches, 1)->getAttribute('id'));
	}

	public function testPseudoClassLastChild() : void
	{
		//print '----' . PHP_EOL;
		$xml = '<?xml version="1.0" ?>
    <test>
      <i class="odd" id="one"/>
      <i class="even" id="two"/>
      <i class="odd" id="three"/>
      <i class="even" id="four">
        <i class="odd" id="inner-one"/>
        <i class="even" id="inner-two"/>
        <i class="odd" id="inner-three"/>
        <i class="even" id="inner-four"/>
      </i>
      <i class="odd" id="five"/>
      <e class="even" id="six"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test single last child.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('#four > i:last-child');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('inner-four', $this->nthMatch($matches, 0)->getAttribute('id'));

		// Test for two last children
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:last-child');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$this->assertSame('inner-four', $this->nthMatch($matches, 0)->getAttribute('id'));
		$this->assertSame('five', $this->nthMatch($matches, 1)->getAttribute('id'));
	}

	public function testPseudoClassNthLastChild() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <i class="odd" id="one"/>
      <i class="even" id="two"/>
      <i class="odd" id="three"/>
      <i class="even" id="four">
        <i class="odd" id="inner-one"/>
        <i class="even" id="inner-two"/>
        <i class="odd" id="inner-three"/>
        <i class="even" id="inner-four"/>
      </i>
      <i class="odd" id="five"/>
      <e class="even" id="six"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test alternate rows from the end.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('#four > i:nth-last-child(odd)');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$this->assertSame('inner-two', $this->nthMatch($matches, 0)->getAttribute('id'));
		$this->assertSame('inner-four', $this->nthMatch($matches, 1)->getAttribute('id'));

		// According to spec, this should be last two elements.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('#four > i:nth-last-child(-1n+2)');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$this->assertSame('inner-three', $this->nthMatch($matches, 0)->getAttribute('id'));
		$this->assertSame('inner-four', $this->nthMatch($matches, 1)->getAttribute('id'));
	}

	public function testPseudoClassFirstOfType() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <n class="odd" id="one"/>
      <i class="even" id="two"/>
      <i class="odd" id="three"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test alternate rows from the end.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:first-of-type(odd)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('two', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testPseudoClassNthFirstOfType() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <n class="odd" id="one"/>
      <i class="even" id="two"/>
      <i class="odd" id="three"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test alternate rows from the end.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:first-of-type(1)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('two', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testPseudoClassLastOfType() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <n class="odd" id="one"/>
      <i class="even" id="two"/>
      <i class="odd" id="three"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test alternate rows from the end.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:last-of-type(odd)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('three', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testPseudoNthClassLastOfType() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <n class="odd" id="one"/>
      <i class="even" id="two"/>
      <i class="odd" id="three"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test alternate rows from the end.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:nth-last-of-type(1)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('three', $this->firstMatch($matches)->getAttribute('id'));

		// Issue #56: an+b not working.
		$xml = '<?xml version="1.0"?>
    <root>
    <div>I am the first div.</div>
    <div>I am the second div.</div>
    <div>I am the third div.</div>
    <div>I am the fourth div.</div>
    <div id="five">I am the fifth div.</div>
    <div id="six">I am the sixth div.</div>
    <div id="seven">I am the seventh div.</div>
    </root>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('div:nth-last-of-type(-n+3)');
		$matches = $handler->getMatches();

		$this->assertSame(3, $matches->count());
		$this->assertSame('five', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testPseudoClassEmpty() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <n class="odd" id="one"/>
      <i class="even" id="two"></i>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('n:empty');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('one', $this->firstMatch($matches)->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:empty');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('two', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testPseudoClassFirst() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <i class="odd" id="one"/>
      <i class="even" id="two"/>
      <i class="odd" id="three"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test alternate rows from the end.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:first');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('one', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testPseudoClassLast() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <i class="odd" id="one"/>
      <i class="even" id="two"/>
      <i class="odd" id="three"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test alternate rows from the end.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:last');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('three', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testPseudoClassGT() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <i class="odd" id="one"/>
      <i class="even" id="two"/>
      <i class="odd" id="three"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test alternate rows from the end.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:gt(1)');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$this->assertSame('two', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testPseudoClassLT() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <i class="odd" id="one"/>
      <i class="even" id="two"/>
      <i class="odd" id="three"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		// Test alternate rows from the end.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:lt(3)');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$this->assertSame('one', $this->nthMatch($matches, 0)->getAttribute('id'));
		$this->assertSame('two', $this->nthMatch($matches, 1)->getAttribute('id'));
	}

	public function testPseudoClassNTH() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <i class="odd" id="one"/>
      <i class="even" id="two"/>
      <i class="odd" id="three"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:nth(2)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('two', $this->firstMatch($matches)->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:eq(2)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('two', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testPseudoClassNthOfType() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <i class="odd" id="one"/>
      <i class="even" id="two"/>
      <i class="odd" id="three"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('i:nth-of-type(2)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('two', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testPseudoClassFormElements() : void
	{
		$form = ['text', 'radio', 'checkbox', 'button', 'password'];
		$xml = '<?xml version="1.0" ?>
    <test>
      <input type="%s" class="odd" id="one"/>
    </test>';

		foreach ($form as $item) {
			$doc = new DomDocument();
			$doc->loadXML(sprintf($xml, $item));

			$handler = new QueryPathEventHandler($doc);
			$handler->find(':' . $item);
			$matches = $handler->getMatches();
			$this->assertSame(1, $matches->count());
			$this->assertSame('one', $this->firstMatch($matches)->getAttribute('id'));
		}
	}

	public function testPseudoClassHeader() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <h1 class="odd" id="one"/>
      <h2 class="even" id="two"/>
      <h6 class="odd" id="three"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('test :header');
		$matches = $handler->getMatches();
		$this->assertSame(3, $matches->count());
		$this->assertSame('three', $this->nthMatch($matches, 2)->getAttribute('id'));
	}

	public function testPseudoClassContains() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <p id="one">This is text.</p>
      <p id="two"><i>More text</i></p>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('p:contains(This is text.)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('one', $this->firstMatch($matches)->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find('* :contains(More text)');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count(), 'Matches two instance of same text?');
		$this->assertSame('two', $this->firstMatch($matches)->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find('p:contains("This is text.")');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count(), 'Quoted text matches unquoted pcdata');
		$this->assertSame('one', $this->firstMatch($matches)->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find('p:contains(\\\'This is text.\\\')');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count(), 'One match for quoted string.');
		$this->assertSame('one', $this->firstMatch($matches)->getAttribute('id'));

		// Test for issue #32
		$handler = new QueryPathEventHandler($doc);
		$handler->find('p:contains(text)');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count(), 'Two matches for fragment of string.');
		$this->assertSame('one', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testPseudoClassContainsExactly() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <p id="one">This is text.</p>
      <p id="two"><i>More text</i></p>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('p:contains(This is text.)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('one', $this->firstMatch($matches)->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find('* :contains(More text)');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count(), 'Matches two instance of same text.');
		$this->assertSame('two', $this->firstMatch($matches)->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find('p:contains("This is text.")');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count(), 'Quoted text matches unquoted pcdata');
		$this->assertSame('one', $this->firstMatch($matches)->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find('p:contains(\\\'This is text.\\\')');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count(), 'One match for quoted string.');
		$this->assertSame('one', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testPseudoClassHas() : void
	{
		$xml = '<?xml version="1.0" ?>
    <test>
      <outer id="one">
        <inner/>
      </outer>
      <outer id="two"/>
    </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('outer:has(inner)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('one', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testPseudoClassNot() : void
	{
		$xml = '<?xml version="1.0" ?>
      <test>
        <outer id="one">
          <inner/>
        </outer>
        <outer id="two" class="notMe"/>
      </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('outer:not(#one)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('two', $this->firstMatch($matches)->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find('outer:not(inner)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('two', $this->firstMatch($matches)->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find('outer:not(.notMe)');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('one', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testPseudoElement() : void
	{
		$xml = '<?xml version="1.0" ?>
      <test>
        <outer id="one">Texts

        More text</outer>
        <outer id="two" class="notMe"/>
      </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('outer::first-letter');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('T', $this->firstMatch($matches, true)->textContent);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('outer::first-line');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('Texts', $this->firstMatch($matches, true)->textContent);
	}

	public function testAdjacent() : void
	{
		$xml = '<?xml version="1.0" ?>
      <test>
        <li id="one"/><li id="two"/><li id="three">
          <li id="inner-one">
            <li id="inner-inner-one"/>
            <li id="inner-inner-one"/>
          </li>
          <li id="inner-two"/>
        </li>
        <li id="four"/>
        <li id="five"/>
      </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('#one + li');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('two', $this->firstMatch($matches)->getAttribute('id'));

		// Tell it to ignore whitespace nodes.
		$doc->loadXML($xml, LIBXML_NOBLANKS);

		// Test with whitespace sensitivity weakened.
		$handler = new QueryPathEventHandler($doc);
		$handler->find('#four + li');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('five', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testAnotherSelector() : void
	{
		$xml = '<?xml version="1.0" ?>
      <test>
        <li id="one"/><li id="two"/><li id="three">
          <li id="inner-one">
            <li id="inner-inner-one"/>
            <li id="inner-inner-one"/>
          </li>
          <li id="inner-two"/>
        </li>
        <li id="four"/>
        <li id="five"/>
      </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('#one, #two');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$this->assertSame('two', $this->nthMatch($matches, 1)->getAttribute('id'));
	}

	public function testSibling() : void
	{
		$xml = '<?xml version="1.0" ?>
      <test>
        <li id="one"/><li id="two"/><li id="three">
          <li id="inner-one">
            <li id="inner-inner-one"/>
            <il id="inner-inner-two"/>
            <li id="dont-match-me"/>
          </li>
          <li id="inner-two"/>
        </li>
        <li id="four"/>
        <li id="five"/>
      </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('#one ~ li');
		$matches = $handler->getMatches();
		$this->assertSame(4, $matches->count());
		$this->assertSame('three', $this->nthMatch($matches, 1)->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find('#two ~ li');
		$matches = $handler->getMatches();
		$this->assertSame(3, $matches->count());

		$handler = new QueryPathEventHandler($doc);
		$handler->find('#inner-one > li ~ il');
		$matches = $handler->getMatches();
		$this->assertSame(1, $matches->count());
		$this->assertSame('inner-inner-two', $this->firstMatch($matches)->getAttribute('id'));
	}

	public function testAnyDescendant() : void
	{
		$xml = '<?xml version="1.0" ?>
      <test>
        <li id="one"/><li id="two"/><li id="three">
          <li id="inner-one" class="foo">
            <li id="inner-inner-one" class="foo"/>
            <il id="inner-inner-two"/>
            <li id="dont-match-me"/>
          </li>
          <li id="inner-two"/>
        </li>
        <li id="four"/>
        <li id="five"/>
      </test>';
		$doc = new DomDocument();
		$doc->loadXML($xml);

		$handler = new QueryPathEventHandler($doc);
		$handler->find('*');
		$matches = $handler->getMatches();
		$this->assertSame(11, $matches->count());

		$handler = new QueryPathEventHandler($doc);
		$handler->find('*.foo');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$this->assertSame('inner-inner-one', $this->nthMatch($matches, 1)->getAttribute('id'));

		$handler = new QueryPathEventHandler($doc);
		$handler->find('test > li *.foo');
		$matches = $handler->getMatches();
		$this->assertSame(2, $matches->count());
		$this->assertSame('inner-inner-one', $this->nthMatch($matches, 1)->getAttribute('id'));
	}

	/**
	 * @template T as bool
	 *
	 * @param SplObjectStorage<DOMNode|TextContent, mixed> $matches
	 *
	 * @return (T is false ? DOMElement : (DOMElement|TextContent))
	 */
	private function firstMatch(SplObjectStorage $matches, bool $usingTextContentOnly = false) : DOMElement|TextContent
	{
		$matches->rewind();

		$current = $matches->current();

		if (
			( ! $usingTextContentOnly && ! ($current instanceof DOMElement))
			|| (
				$usingTextContentOnly
				&& ! (
					($current instanceof DOMElement)
					|| ($current instanceof TextContent)
				)
			)
		) {
			throw new UnexpectedValueException(
				'nth match not found!'
			);
		}

		return $current;
	}

	/**
	 * @template T as bool
	 *
	 * @param SplObjectStorage<DOMNode|TextContent, mixed> $matches
	 *
	 * @return (T is false ? DOMElement : (DOMElement|TextContent))
	 */
	private function nthMatch(SplObjectStorage $matches, int $n = 0, bool $usingTextContentOnly = false) : DOMElement|TextContent
	{
		/** @var DOMNode|TextContent|null */
		$match = null;

		foreach ($matches as $m) {
			if ($matches->key() === $n) {
				$match = $m;
				break;
			}
		}

		if (
			( ! $usingTextContentOnly && ! ($match instanceof DOMElement))
			|| (
				$usingTextContentOnly
				&& ! (
					($match instanceof DOMElement)
					|| ($match instanceof TextContent)
				)
			)
		) {
			throw new UnexpectedValueException(
				'nth match not found!'
			);
		}

		return $match;
	}
}
