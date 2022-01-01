<?php

declare(strict_types=1);

namespace QueryPathTests\Extension;

use QueryPath\DOMQuery;
use QueryPath\Extension\QPXML;
use QueryPath\QueryPath;
use QueryPathTests\TestCase;

/**
 * @ingroup querypath_tests
 * @group   extension
 */
class QPXMLTest extends TestCase
{
	protected string $file = './tests/advanced.xml';

	public static function setUpBeforeClass() : void
	{
		QueryPath::enable(QPXML::class);
	}

	public function testCDATA() : void
	{
		$this->assertSame('This is a CDATA section.', qp($this->file, 'first')->cdata());

		$msg = 'Another CDATA Section';
		$qp1 = qp($this->file, 'second')->cdata($msg);
		$this->assertInstanceOf(DOMQuery::class, $qp1);
		$qp2 = $qp1->top();
		$this->assertInstanceOf(DOMQuery::class, $qp2);
		$qp3 = $qp2->find('second');
		$this->assertInstanceOf(DOMQuery::class, $qp3);
		$this->assertSame($msg, $qp3->cdata());
	}

	public function testComment() : void
	{
		$qp = qp($this->file, 'root')->comment();
		$this->assertIsString($qp);
		$this->assertSame('This is a comment.', trim($qp));
		$msg = 'Message';
		$qp = qp($this->file, 'second');
		$this->assertInstanceOf(DOMQuery::class, $qp);
		$qp2 = $qp->comment($msg);
		$this->assertInstanceOf(DOMQuery::class, $qp2);
		$qp3 = $qp2->top();
		$this->assertInstanceOf(DOMQuery::class, $qp3);
		$this->assertSame($msg, $qp3->find('second')->comment());
	}

	public function testProcessingInstruction() : void
	{
		$pi = qp($this->file, 'third')->pi();
		$this->assertIsString($pi);
		$this->assertSame('This is a processing instruction.', trim($pi));
		$msg = 'Message';
		$qp_pi = qp($this->file, 'second')->pi('qp', $msg);
		$this->assertInstanceOf(DOMQuery::class, $qp_pi);
		$this->assertSame($msg, $qp_pi->top()->find('second')->pi());
	}
}
