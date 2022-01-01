<?php

declare(strict_types=1);

namespace QueryPathTests\Extension;

use QueryPath\DOMQuery;
use QueryPath\Extension\QPXSL;
use QueryPath\QueryPath;
use QueryPathTests\TestCase;

class QPXSLTest extends TestCase
{
	public static function setUpBeforeClass() : void
	{
		QueryPath::enable(QPXSL::class);
	}

	public function testXSLT() : void
	{
		// XML and XSLT taken from http://us.php.net/manual/en/xsl.examples-collection.php
		// and then modified to be *actually welformed* XML.
		$orig = '<?xml version="1.0"?><collection>
     <cd>
      <title>Fight for your mind</title>
      <artist>Ben Harper</artist>
      <year>1995</year>
     </cd>
     <cd>
      <title>Electric Ladyland</title>
      <artist>Jimi Hendrix</artist>
      <year>1997</year>
     </cd>
    </collection>';

		$template = '<?xml version="1.0"?><xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
     <xsl:param name="owner" select="\'Nicolas Eliaszewicz\'"/>
     <xsl:output method="html" encoding="iso-8859-1" indent="no"/>
     <xsl:template match="collection">
      <div>
      Hey! Welcome to <xsl:value-of select="$owner"/>\'s sweet CD collection!
      <xsl:apply-templates/>
      </div>
     </xsl:template>
     <xsl:template match="cd">
      <h1><xsl:value-of select="title"/></h1>
      <h2>by <xsl:value-of select="artist"/> - <xsl:value-of select="year"/></h2>
      <hr />
     </xsl:template>
    </xsl:stylesheet>
    ';

		/** @var DOMQuery&QPXSL */
		$with_xslt = qp($orig);
		$qp = $with_xslt->xslt($template);
		$this->assertInstanceOf(DOMQuery::class, $qp);
		$this->assertSame(2, $qp->top('h1')->count(), 'Make sure that data was formatted');
	}
}
