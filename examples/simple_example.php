<?php
require_once __DIR__.'/../vendor/autoload.php';
use function ReplBreakpoint\repl;

$a = 2;
function sq($x) { return $x * $x; }

repl(get_defined_vars());
