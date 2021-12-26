<?php
/**
* PHP-CS-Fixer Config.
*
* @author SignpostMarv
*/
declare(strict_types=1);

namespace SignpostMarv\CS;

class QueryPathConfig extends ConfigUsedWithStaticAnalysis
{
	protected static function RuntimeResolveRules() : array
	{
		$rules = parent::RuntimeResolveRules();

		$rules['php_unit_method_casing'] = false;
		$rules['php_unit_test_case_static_method_calls'] = false;
		$rules['mb_str_functions'] = false;

		return $rules;
	}
}

return QueryPathConfig::createWithPaths(...[
	__FILE__,
	__DIR__ . '/src/',
	__DIR__ . '/tests/',
]);
