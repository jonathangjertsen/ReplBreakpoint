<?php
require_once __DIR__.'/../vendor/autoload.php';

use function ReplBreakpoint\repl;
use function ReplBreakpoint\replPrintable;

function get_injected_vars($vars)
{
    return array_filter($vars, function($key) {
        return !in_array($key, ['var_list', '_GET', '_POST', '_COOKIE', '_SERVER', '_ENV', '_REQUEST', '_FILES']);
    }, ARRAY_FILTER_USE_KEY);
}

$a = 2;
$b = "hello";
$c = [1,2,3,[1,2,3,4]];
function sq($x) { return $x * $x; }

$var_list = "";
foreach (get_injected_vars(get_defined_vars()) as $key => $val) {
    $var_list .= "\n    - \$$key is " . replPrintable($val);
}

echo <<<HEREDOC
==========================================================
|                ReplBreakpoint example                  |
==========================================================
By calling repl(get_defined_vars()), we have made the REPL
able to see the following variables and functions from
the parent scope (in addition to the superglobals):
$var_list
    - the function sq(), which returns the square of a number

To print the value of something, write it out without a
semicolon. If you include the semicolon, the result will
not be printed. \$_ANS always contains the last result.

Multiline code is accepted: as long as the parens are
unbalanced, the input will be buffered. You can also force
buffering by ending the line with / or \\.


HEREDOC;

unset($var_list);

repl(get_defined_vars());
