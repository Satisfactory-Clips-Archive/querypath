<?php

declare(strict_types=1);

namespace QueryPathTests;

/**
 * @ingroup querypath_tests
 */
class EntitiesTest extends TestCase
{
	public function testReplaceEntity() : void
	{
		$entity = 'amp';
		$this->assertSame(38, \QueryPath\Entities::replaceEntity($entity));

		$entity = 'lceil';
		$this->assertSame(8968, \QueryPath\Entities::replaceEntity($entity));
	}

	public function testReplaceAllEntities() : void
	{
		$test = '<?xml version="1.0"?><root>&amp;&copy;&#38;& nothing.</root>';
		$expect = '<?xml version="1.0"?><root>&#38;&#169;&#38;&#38; nothing.</root>';
		$this->assertSame($expect, \QueryPath\Entities::replaceAllEntities($test));

		$test = '&&& ';
		$expect = '&#38;&#38;&#38; ';
		$this->assertSame($expect, \QueryPath\Entities::replaceAllEntities($test));

		$test = "&eacute;\n";
		$expect = "&#233;\n";
		$this->assertSame($expect, \QueryPath\Entities::replaceAllEntities($test));
	}

	public function testReplaceHexEntities() : void
	{
		$test = '&#xA9;';
		$expect = '&#xA9;';
		$this->assertSame($expect, \QueryPath\Entities::replaceAllEntities($test));
	}

	public function testQPEntityReplacement() : void
	{
		$test = '<?xml version="1.0"?><root>&amp;&copy;&#38;& nothing.</root>';
		/*$expect = '<?xml version="1.0"?><root>&#38;&#169;&#38;&#38; nothing.</root>';*/
		// We get this because the DOM serializer re-converts entities.
		$expect = '<?xml version="1.0"?>
<root>&amp;&#xA9;&amp;&amp; nothing.</root>';

		$qp = qp($test, null, ['replace_entities' => true]);
		// Interestingly, the XML serializer converts decimal to hex and ampersands
		// to &amp;.
		$xml = $qp->xml();
		$this->assertIsString($xml);
		$this->assertSame($expect, trim($xml));
	}
}
