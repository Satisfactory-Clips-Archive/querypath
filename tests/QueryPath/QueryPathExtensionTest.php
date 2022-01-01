<?php

declare(strict_types=1);

namespace QueryPathTests;

use Exception;
use QueryPath\DOMQuery;
use QueryPath\Extension;
use QueryPath\ExtensionRegistry;

/**
 * Run all of the usual tests, plus some extras, with some extensions loaded.
 *
 * @ingroup querypath_tests
 * @group   extension
 */
class QueryPathExtensionTest extends TestCase
{
	public static function setUpBeforeClass() : void
	{
		ExtensionRegistry::extend(StubExtensionOne::class);
		ExtensionRegistry::extend(StubExtensionTwo::class);
	}

	public function tearDown() : void
	{
		ExtensionRegistry::$useRegistry = true;
	}

	public function testExtensions() : void
	{
		$this->assertNotNull(qp());
	}

	public function testHasExtension() : void
	{
		$this->assertTrue(ExtensionRegistry::hasExtension(StubExtensionOne::class));
	}

	public function testStubToe() : void
	{
		/** @var DOMQuery&StubExtensionOne */
		$qp = qp(self::DATA_FILE_XML, 'unary');
		$this->assertSame(1, $qp->stubToe()->top(':root > toe')->count());
	}

	public function testStuble() : void
	{
		/** @var DOMQuery&StubExtensionTwo|DOMQuery&StubExtensionThree */
		$qp = qp(self::DATA_FILE_XML);
		$this->assertSame('arg1arg2', $qp->stuble('arg1', 'arg2'));
	}

	public function testNoRegistry() : void
	{
		ExtensionRegistry::$useRegistry = false;
		$this->expectException(\QueryPath\Exception::class);
		try {
			qp(self::DATA_FILE_XML)->stuble('arg1', 'arg2');
		} catch (\QueryPath\Exception $e) {
			ExtensionRegistry::$useRegistry = true;
			throw $e;
		}
	}

	public function testExtend() : void
	{
		$this->assertFalse(ExtensionRegistry::hasExtension(StubExtensionThree::class));
		ExtensionRegistry::extend(StubExtensionThree::class);
		$this->assertTrue(ExtensionRegistry::hasExtension(StubExtensionThree::class));
	}

	public function testAutoloadExtensions() : void
	{
		// FIXME: This isn't really much of a test.
		ExtensionRegistry::autoloadExtensions(false);
		$this->expectException(\QueryPath\Exception::class);
		try {
			qp()->stubToe();
		} catch (Exception $e) {
			ExtensionRegistry::autoloadExtensions(true);
			throw $e;
		}
	}

	public function testCallFailure() : void
	{
		$this->expectException(\QueryPath\Exception::class);
		qp()->foo();
	}
}
