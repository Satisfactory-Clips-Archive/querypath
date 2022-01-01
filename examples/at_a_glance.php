<?php

declare(strict_types=1);
require '../src/qp.php';
$xml = <<<'EOF'
<?xml version="1.0"?>
<table>
  <tr id="row1">
    <td>one</td><td>two</td><td>three</td>
  </tr>
  <tr id="row2">
    <td>four</td><td>five</td><td>six</td>
  </tr>
  </table>
EOF;

echo "\nExample 1: \n";
// Get all of the <td> elements in the document and add the
// attribute `foo='bar'`:
qp($xml, 'td')->attr('foo', 'bar')->writeXML();

echo "\nExample 2: \n";

// Or print the contents of the third TD in the second row:
echo qp($xml, '#row2>td:nth(3)')->text();

echo "\nExample 3: \n";
// Or append another row to the XML and then write the
// result to standard output:
qp($xml, 'tr:last')->after('<tr><td/><td/><td/></tr>')->writeXML();
