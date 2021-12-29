<?php

declare(strict_types=1);

namespace QueryPath\Extension;

use function array_slice;
use function call_user_func_array;
use Closure;
use function count;
use DOMNode;
use function func_get_args;
use function is_array;
use function is_callable;
use function is_string;
use QueryPath\DOMQuery;
use QueryPath\Exception;
use QueryPath\Extension;
use QueryPath\Query;
use QueryPath\TextContent;
use UnexpectedValueException;

/**
 * A QueryPath extension that adds extra methods for formatting node values.
 *
 * This extension provides two methods:
 *
 * - format()
 * - formatAttr()
 *
 * Usage:
 * <code>
 * <?php
 * QueryPath::enable('Noi\QueryPath\FormatExtension');
 * $qp = qp('<?xml version="1.0"?><root><item score="12000">TEST A</item><item score="9876.54">TEST B</item></root>');
 *
 * $qp->find('item')->format(function ($text) {
 *     return ucwords(strtolower($text));
 * });
 * $qp->find('item')->formatAttr('score', 'number_format', 2);
 *
 * $qp->writeXML();
 * </code>
 *
 * OUTPUT:
 * <code>
 * <?xml version="1.0"?>
 * <root>
 *   <item score="12,000.00">Test A</item>
 *   <item score="9,876.54">Test B</item>
 * </root>
 * </code>
 *
 * @see FormatExtension::format()
 * @see FormatExtension::formatAttr()
 *
 * @author Akihiro Yamanoi <akihiro.yamanoi@gmail.com>
 */
class Format implements Extension
{
	public function __construct(
		protected DOMQuery $qp
	)
	{
	}

	/**
	 * Formats the text content of each selected element in the current DOMQuery object.
	 *
	 * Usage:
	 * <code>
	 * <?php
	 * QueryPath::enable('Noi\QueryPath\FormatExtension');
	 * $qp = qp('<?xml version="1.0"?><root><div>Apple</div><div>Orange</div></root>');
	 *
	 * $qp->find('div')->format('strtoupper');
	 * $qp->find('div')->format(function ($text) {
	 *     return '*' . $text . '*';
	 * });
	 *
	 * $qp->writeXML();
	 * </code>
	 *
	 * OUTPUT:
	 * <code>
	 * <?xml version="1.0"?>
	 * <root>
	 *   <div>*APPLE*</div>
	 *   <div>*ORANGE*</div>
	 * </root>
	 * </code>
	 *
	 * @param callable $callback the callable to be called on every element
	 * @param string ...$args        [optional] Zero or more parameters to be passed to the callback
	 *
	 * @throws Exception
	 *
	 * @return DOMQuery the DOMQuery object with the same element(s) selected
	 */
	public function format($callback, string ...$args) : DOMQuery
	{
		$getter = static function (DOMQuery $qp) : string {
			return $qp->text();
		};

		$setter = static function (DOMQuery $qp, string $value = null) : void {
			$qp->text($value);
		};

		return $this->forAll($callback, $args, $getter, $setter);
	}

	/**
	 * Formats the given attribute of each selected element in the current DOMQuery object.
	 *
	 * Usage:
	 * <code>
	 * QueryPath::enable('Noi\QueryPath\FormatExtension');
	 * $qp = qp('<?xml version="1.0"?><root><item label="_apple_" total="12,345,678" /><item label="_orange_" total="987,654,321" /></root>');
	 *
	 * $qp->find('item')
	 *     ->formatAttr('label', 'trim', '_')
	 *     ->formatAttr('total', 'str_replace[2]', ',', '');
	 *
	 * $qp->find('item')->formatAttr('label', function ($value) {
	 *     return ucfirst(strtolower($value));
	 * });
	 *
	 * $qp->writeXML();
	 * </code>
	 *
	 * OUTPUT:
	 * <code>
	 * <?xml version="1.0"?>
	 * <root>
	 *   <item label="Apple" total="12345678"/>
	 *   <item label="Orange" total="987654321"/>
	 * </root>
	 * </code>
	 *
	 * @param string $attrName   the attribute name
	 * @param callable $callback the callable to be called on every element
	 * @param string ...$args        [optional] Zero or more parameters to be passed to the callback
	 *
	 * @throws Exception
	 *
	 * @return DOMQuery the DOMQuery object with the same element(s) selected
	 */
	public function formatAttr(string $attrName, $callback, string ...$args) : DOMQuery
	{
		$getter = static function (DOMQuery $qp) use ($attrName) : string|int|null {
			return $qp->attr($attrName);
		};

		$setter = static function (DOMQuery $qp, ?string $value) use ($attrName) : DOMQuery|int|string|null {
			return $qp->attr($attrName, $value);
		};

		return $this->forAll($callback, $args, $getter, $setter);
	}

	/**
	 * @param string|array{0:string|object, 1:string, 2?:int}|Closure|callable $callback
	 * @param string[] $args
	 * @param Closure(DOMQuery):(string|int|null) $getter
	 * @param Closure(DOMQuery, string|null):(DOMQuery|int|string|null) $setter
	 *
	 * @throws Exception
	 */
	protected function forAll($callback, array $args, $getter, $setter) : DOMQuery
	{
		[$callback, $pos] = $this->prepareCallback($callback);

		$padded = $this->prepareArgs($args, $pos);
		/** @var DOMQuery */
		foreach ($this->qp as $qp) {
			$padded[$pos] = $getter($qp);
			$result = call_user_func_array($callback, $padded);
			assert(
				(is_string($result) || is_null($result)),
				new UnexpectedValueException(sprintf(
					'result of callback should\'ve been string or null, %s given!',
					gettype($result)
				))
			);
			$setter($qp, $result);
		}

		return $this->qp;
	}

	/**
	 * @param string|array{0:string|object, 1:string, 2?:int}|Closure|callable $callback
	 *
	 * @return array{0:callable, 1:int}
	 */
	protected function prepareCallback($callback) : array
	{
		if (is_string($callback)) {
			[$callback, $trail] = $this->splitFunctionName($callback) ?: [null, null];
			$pos = (int) $trail;
		} elseif (is_array($callback) && isset($callback[2])) {
			/** @var int */
			$pos = $callback[2];
			$callback = [$callback[0], $callback[1]];
		} else {
			$pos = 0;
		}
		if ( ! is_callable($callback)) {
			throw new Exception('Callback is not callable.');
		}

		/** @var array{0:callable, 1:int} */
		return [$callback, $pos];
	}

	/**
	 * @return array[]|false|string[]
	 */
	protected function splitFunctionName(string $string) : array|false
	{
		// 'func_name:2', 'func_name@3', 'func_name[1]', ...
		return preg_split('/[^a-zA-Z0-9_\x7f-\xff][^\d]*|$/', $string, 2);
	}

	/**
	 * @param string|string[] $args
	 *
	 * @return (string|null)[]
	 */
	protected function prepareArgs(string|array $args, int $pos) : array
	{
		$padded = array_pad((array) $args, (0 < $pos) ? $pos - 1 : 0, null);
		array_splice($padded, $pos, 0, [null]); // insert null as a place holder

		return $padded;
	}
}
