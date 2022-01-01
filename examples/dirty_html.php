<?php

declare(strict_types=1);
/**
 * Urban Dictionary Random Word Generator.
 *
 * @author Emily Brand
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 *
 * @see http://www.urbandictionary.com/
 */
require_once '../src/QueryPath/QueryPath.php';

echo '<h3>Urban Dictionary Random Word Generator</h3>';

$page = mt_rand(0, 288);
$qp = htmlqp('http://www.urbandictionary.com/?page=' . $page, '#home');

$rand = mt_rand(0, 7);
echo $qp->find('.word')->eq($rand)->text() . '<br />';
echo $qp->top()->find('.definition')->eq($rand)->text();
