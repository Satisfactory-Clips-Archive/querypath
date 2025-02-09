<?php

declare(strict_types=1);
/**
 * @file
 * The extension registry.
 */

namespace QueryPath;

use function in_array;
use ReflectionClass;

/**
 * A registry for QueryPath extensions.
 *
 * QueryPath extensions should call the QueryPath::ExtensionRegistry::extend()
 * function to register their extension classes. The QueryPath library then
 * uses this information to determine what QueryPath extensions should be loaded and
 * executed.
 *
 * Extensions are attached to a Query object.
 *
 * To enable an extension (the easy way), use QueryPath::enable().
 *
 * This class provides lower-level interaction with the extension
 * mechanism.
 *
 * @ingroup querypath_extensions
 */
class ExtensionRegistry
{
	/**
	 * Internal flag indicating whether or not the registry should
	 * be used for automatic extension loading. If this is false, then
	 * implementations should not automatically load extensions.
	 */
	public static bool $useRegistry = true;
	/**
	 * The extension registry. This should consist of an array of class
	 * names.
	 *
	 * @var list<class-string<Extension>>
	 */
	protected static array $extensionRegistry = [];

	/** @var array<string, class-string<Extension>> */
	protected static array $extensionMethodRegistry = [];

	/**
	 * Extend a Query with the given extension class.
	 *
	 * @param class-string<Extension> $classname
	 */
	public static function extend(string $classname) : void
	{
		self::$extensionRegistry[] = $classname;
		$class = new ReflectionClass($classname);
		$methods = $class->getMethods();
		foreach ($methods as $method) {
			self::$extensionMethodRegistry[$method->getName()] = $classname;
		}
	}

	/**
	 * Check to see if a method is known.
	 * This checks to see if the given method name belongs to one of the
	 * registered extensions. If it does, then this will return TRUE.
	 *
	 * @param string $name
	 *  The name of the method to search for
	 *
	 * @return bool
	 *  TRUE if the method exists, false otherwise
	 */
	public static function hasMethod($name)
	{
		return isset(self::$extensionMethodRegistry[$name]);
	}

	/**
	 * Check to see if the given extension class is registered.
	 * Given a class name for a QueryPath::Extension class, this
	 * will check to see if that class is registered. If so, it will return
	 * TRUE.
	 *
	 * @param string $name
	 *  The name of the class
	 *
	 * @return bool
	 *  TRUE if the class is registered, FALSE otherwise
	 */
	public static function hasExtension($name)
	{
		return in_array($name, self::$extensionRegistry, true);
	}

	/**
	 * Get the class that a given method belongs to.
	 * Given a method name, this will check all registered extension classes
	 * to see if any of them has the named method. If so, this will return
	 * the classname.
	 *
	 * Note that if two extensions are registered that contain the same
	 * method name, the last one registred will be the only one recognized.
	 *
	 * @param string $name
	 *  The name of the method
	 *
	 * @return class-string<Extension>
	 *  The name of the class
	 */
	public static function getMethodClass(string $name) : string
	{
		return self::$extensionMethodRegistry[$name];
	}

	/**
	 * Get extensions for the given Query object.
	 *
	 * Given a Query object, this will return
	 * an associative array of extension names to (new) instances.
	 * Generally, this is intended to be used internally.
	 *
	 * @param Query $qp
	 *  The Query into which the extensions should be registered
	 *
	 * @return array<string, Extension>
	 *  An associative array of classnames to instances
	 */
	public static function getExtensions(Query $qp) : array
	{
		/** @var array<string, Extension> */
		$extInstances = [];
		foreach (self::$extensionRegistry as $ext) {
			$extInstances[$ext] = new $ext($qp);
		}

		return $extInstances;
	}

	/**
	 * Enable or disable automatic extension loading.
	 *
	 * If extension autoloading is disabled, then QueryPath will not
	 * automatically load all registred extensions when a new Query
	 * object is created using qp().
	 *
	 * @param bool $boolean
	 */
	public static function autoloadExtensions($boolean = true) : void
	{
		self::$useRegistry = $boolean;
	}
}
