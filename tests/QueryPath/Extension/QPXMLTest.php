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
		/** @var DOMQuery&QPXML */
		$with_extension = qp($this->file, 'second');
		$qp1 = $with_extension->cdata($msg);
		$this->assertInstanceOf(DOMQuery::class, $qp1);
		$qp2 = $qp1->top();
		$this->assertInstanceOf(DOMQuery::class, $qp2);
		/** @var DOMQuery&QPXML */
		$qp3 = $qp2->find('second');
		$this->assertInstanceOf(DOMQuery::class, $qp3);
		$this->assertSame($msg, $qp3->cdata());
	}

	public function testComment() : void
	{
		/** @var DOMQuery&QPXML */
		$with_extension = qp($this->file, 'root');
		$qp = $with_extension->comment();
		$this->assertIsString($qp);
		$this->assertSame('This is a comment.', trim($qp));
		$msg = 'Message';
		/** @var DOMQuery&QPXML */
		$qp = qp($this->file, 'second');
		$this->assertInstanceOf(DOMQuery::class, $qp);
		$qp2 = $qp->comment($msg);
		$this->assertInstanceOf(DOMQuery::class, $qp2);
		$qp3 = $qp2->top();
		$this->assertInstanceOf(DOMQuery::class, $qp3);
		/** @var DOMQuery&QPXML */
		$with_extension = $qp3->find('second');
		$this->assertSame($msg, $with_extension->comment());
	}

	public function testProcessingInstruction() : void
	{
		/** @var DOMQuery&QPXML */
		$qp = qp($this->file, 'third');
		$pi = $qp->pi();
		$this->assertIsString($pi);
		$this->assertSame('This is a processing instruction.', trim($pi));
		$msg = 'Message';
		/** @var DOMQuery&QPXML */
		$qp = qp($this->file, 'second');
		$qp_pi = $qp->pi('qp', $msg);
		$this->assertInstanceOf(DOMQuery::class, $qp_pi);
		/** @var DOMQuery&QPXML */
		$qp = $qp_pi->top()->find('second');
		$this->assertSame($msg, $qp->pi());
	}
}
