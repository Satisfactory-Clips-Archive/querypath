<?php

declare(strict_types=1);

namespace QueryPathTests\Extension;

use DOMElement;
use QueryPath\Extension\Format;
use QueryPath\QueryPath;
use QueryPath\TextContent;
use QueryPathTests\TestCase;

class FormatTest extends TestCase
{
	/**
	 * @test
	 *
	 * @throws \QueryPath\CSS\ParseException
	 */
	public function it_formats_tag_text_node() : void
	{
		QueryPath::enable(Format::class);
		$qp = qp('<?xml version="1.0"?><root><div>_apple_</div><div>_orange_</div></root>');
		$qp->find('div')->format('strtoupper')->format('trim', '_')->format(static function ($text) {
			return '*' . $text . '*';
		});

		$result = $qp->get(0);
		$this->assertTrue(
			($result instanceof DOMElement)
			|| ($result instanceof TextContent)
		);
		$this->assertSame('*APPLE**ORANGE*', $result->textContent);
	}

	/**
	 * @test
	 *
	 * @throws \QueryPath\CSS\ParseException
	 */
	public function it_formats_attribute() : void
	{
		QueryPath::enable(Format::class);
		$qp = qp('<?xml version="1.0"?><root>' .
			'<item label="_apple_" total="12,345,678" />' .
			'<item label="_orange_" total="987,654,321" />' .
			'</root>');

		$qp->find('item')
			->formatAttr('label', 'trim', '_')
			->formatAttr('total', 'str_replace[2]', ',', '')
		;

		$this->assertSame('apple', $qp->find('item')->attr('label'));
		$this->assertSame('12345678', $qp->find('item')->attr('total'));
	}
}
