<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace QueryPath;

use DOMDocument;
use DOMNode;
use const XML_TEXT_NODE;

class TextContent
{
	/** @readonly */
	public int $nodeType = XML_TEXT_NODE;

	/** @readonly */
	public DOMNode|null $parentNode = null;

	/** @readonly */
	public array $childNodes = [];

	/** @readonly */
	public DOMDocument|null $ownerDocument = null;

	public function __construct(
		public readonly string $textContent
	) {
	}

	public function cloneNode() : self
	{
		return new self($this->textContent);
	}
}
