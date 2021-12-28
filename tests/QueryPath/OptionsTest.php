<?php

declare(strict_types=1);

namespace QueryPathTests;

use QueryPath\Options;

/**
 * @ingroup querypath_tests
 */
class OptionsTest extends TestCase
{
	public function testOptions() : void
	{
		$expect = ['test1' => 'val1', 'test2' => 'val2'];
		$options = ['test1' => 'val1', 'test2' => 'val2'];

		Options::set($options);

		$results = Options::get();
		$this->assertSame($expect, $results);

		$this->assertSame('val1', $results['test1']);
	}

	public function testQPOverrideOrder() : void
	{
		$expect = ['test1' => 'val3', 'test2' => 'val2'];
		$options = ['test1' => 'val1', 'test2' => 'val2'];

		Options::set($options);
		$qpOpts = qp(null, null, ['test1' => 'val3', 'replace_entities' => true])->getOptions();

		$this->assertSame($expect['test1'], $qpOpts['test1']);
		$this->assertTrue($qpOpts['replace_entities']);
		$this->assertNull($qpOpts['parser_flags']);
		$this->assertSame($expect['test2'], $qpOpts['test2']);
	}

	public function testQPHas() : void
	{
		$options = ['test1' => 'val1', 'test2' => 'val2'];

		Options::set($options);
		$this->assertTrue(Options::has('test1'));
		$this->assertFalse(Options::has('test3'));
	}

	public function testQPMerge() : void
	{
		$options = ['test1' => 'val1', 'test2' => 'val2'];
		$options2 = ['test1' => 'val3', 'test4' => 'val4'];

		Options::set($options);
		Options::merge($options2);

		$this->assertTrue(Options::has('test1'));
		$this->assertTrue(Options::has('test2'));
		$this->assertTrue(Options::has('test4'));

		/** @var array{test1:string, test2:'val2', test4:'val4'} */
		$results = Options::get();

		$this->assertSame('val3', $results['test1']);
	}
}
