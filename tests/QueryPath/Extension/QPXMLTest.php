<?php

declare(strict_types=1);

namespace QueryPathTests\Extension;

use QueryPath\Extension\QPXML;
use QueryPath\QueryPath;
use QueryPathTests\TestCase;

/**
 * @ingroup querypath_tests
 * @group   extension
 */
class QPXMLTest extends TestCase
{
	protected $file = './tests/advanced.xml';

	public static function setUpBeforeClass() : void
	{
		QueryPath::enable(QPXML::class);
	}

	public function testCDATA() : void
	{
		$this->assertSame('This is a CDATA section.', qp($this->file, 'first')->cdata());

		$msg = 'Another CDATA Section';
		$this->assertSame($msg, qp($this->file, 'second')->cdata($msg)->top()->find('second')->cdata());
	}

	public function testComment() : void
	{
		$this->assertSame('This is a comment.', trim(qp($this->file, 'root')->comment()));
		$msg = 'Message';
		$this->assertSame($msg, qp($this->file, 'second')->comment($msg)->top()->find('second')->comment());
	}

	public function testProcessingInstruction() : void
	{
		$this->assertSame('This is a processing instruction.', trim(qp($this->file, 'third')->pi()));
		$msg = 'Message';
		$this->assertSame($msg, qp($this->file, 'second')->pi('qp', $msg)->top()->find('second')->pi());
	}
}
