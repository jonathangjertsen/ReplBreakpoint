<?php

namespace ReplBreakpoint;

function repl($vars = NULL, $options = [])
{
    // Get variables
    if ($vars) {
        extract($vars);
    }

    // Set error handler
    if (isset($options['error_handler'])) {
        set_error_handler($options['error_handler']);
    } else {
        _setReplErrorHandler();
    }

    if (isset($options['shutdown_function'])) {
        set_error_handler($options['shutdown_function']);
    } else {
        _setReplShutdownFunction();
    }

    // Build an intro statement and print it
    if (!isset($options["quiet"]) || !$options["quiet"]) {
        $intro_statement = "REPL launched";
        $func_indicator = isset($options["function"]) && $options["function"] ? " in function {$options["function"]}" : "";
        $file_indicator = isset($options["file"]) ? " in file {$options["file"]}" : "";
        $line_indicator = isset($options["line"]) ? " on line {$options["line"]}" : "";
        $exit_guide = "To exit, write \"exit\" or press Ctrl+C.";
        echo($intro_statement . $func_indicator . $file_indicator . $line_indicator . "\n" . $exit_guide);
    }

    // Initialize some special values that will be updated over time
    $buffered_code = "";
    $indentation_level = 0;
    $paren_stack = [];
    $_ANS = null;

    // Start REPL
    $stdin = fopen("php://stdin", "r");
    while (true) {
        // Read
        if ($indentation_level > 0) {
            echo str_repeat("    ", $indentation_level);
        } else {
            echo("\n> ");
        }
        $code = trim(fgets($stdin, 1024));

        // Handle exit requests
        if (in_array($code, ["break", "break;", "return", "return;"])) {
            break;
        }

        // Buffer code if needed
        $opening_parens = ["{", "(", "["];
        $closing_parens = ["}", ")", "]"];
        foreach(str_split($code) as $char) {
            if (in_array($char, $opening_parens)) {
                $indentation_level++;
                $paren_stack[] = $char;
            } elseif (in_array($char, $closing_parens)) {
                $expected_paren = _oppositeParen(array_pop($paren_stack));
                if ($char != $expected_paren) {
                    $paren_stack[] = _oppositeParen($expected_paren);
                    echo("Incorrect closing paren. Expected $expected_paren, not $char");
                    $indentation_level = 0;
                    $buffered_code = "";
                    continue;
                }
                $indentation_level--;
            }
        }

        // Buffer code if needed
        if ($indentation_level > 0) {
            $buffered_code .= $code;
            continue;
        } elseif (_endsWith($code, "/", "\\")) {
            $buffered_code .= substr($code, 0, -1);
            continue;
        } else {
            $code = $buffered_code . $code;
            $buffered_code = "";
        }

        // If it's not returnable, just eval it
        if (!_replIsReturnable($code)) {
            if (_endsWith($code, ";")) {
                eval($code);
            } else {
                eval($code . ";");
            }

            continue;
        }

        // Execute "return $code"; and print the result
        $result = eval("return {$code};");
        $_ANS = $result;

        if (isset($options["printer"])) {
            $printer = $options["printer"];
            $printer($result);
        } else {
            echo(replPrintable($result));
        }
    }
    fclose($stdin);
}

/**
 * Returns a representation of the value which can be printed to the command line.
 *
 * @param mixed $val Value to be printed
 *
 * @return string Printable representation of the value
 */
function replPrintable($val)
{
    $val_type = gettype($val);
    switch ($val_type) {
        case "integer":
        case "float":
            return (string)$val;
        case "boolean":
            return $val ? "true" : "false";
        case "NULL":
            return "null";
        case "string":
            return "\"$val\"";
        case "array":
            $representation = [];
            $is_numeric = _isNumeric($val);
            foreach ($val as $key => &$elem) {
                $elem = replPrintable($elem);
                if ($is_numeric) {
                    $representation[] = $elem;
                } else {
                    $representation[] = "$key => $elem";
                }
            }
            return "[" . implode(",", $representation) . "]";
        case "object":
            if (method_exists($val, "__toString")) {
                return (string)$val;
            } else {
                $val_class = get_class($val);
                return "$val_class object";
            }
        default:
            return "Something of type $val_type";
     }
}

/**
 * Returns whether calling "return $code;" would be a valid thing to do
 *
 * @param string $code
 *
 * @return bool Whether calling "return $code;" is valid
 */
function _replIsReturnable($code)
{
    $non_returnables = [
        "declare",
        "do",
        "echo",
        "elseif",
        "for",
        "foreach",
        "global",
        "goto",
        "if",
        "include",
        "include_once",
        "list",
        "namespace",
        "print",
        "require",
        "require_once",
        "return",
        "switch",
        "throw",
        "try",
        "unset",
        "use",
        "while",
        "yield"
    ];

    foreach($non_returnables as $non_returnable) {
        $non_starters = [
            $non_returnable . "(",
            $non_returnable . " ",
            $non_returnable . "\t",
            $non_returnable . "\n",
            $non_returnable . "{",
            $non_returnable . ";"
        ];

        if (_startsWith($code, ...$non_starters)) {
            return false;
        }
    }

    if (_endsWith($code, ";", "}")) {
        return false;
    }

    return true;
}

/**
 * @param $str
 * @param ...$substrs
 *
 * @return bool Whether the $str starts with any of the $substrs
 */
function _startsWith($str, ...$substrs)
{
    $str = ltrim($str);
    foreach ($substrs as $substr) {
        $len = mb_strlen($substr);
        if ($len === 0) {
            continue;
        }
        if (mb_strpos($str, $substr) === 0) {
            return $substr;
        }
    }

    return false;
}

/**
 * @param $str
 * @param ...$substrs
 *
 * @return bool Whether the $str ends with any of the $substrs
 */
function _endsWith($str, ...$substrs)
{
    $str = trim($str);
    foreach ($substrs as $substr) {
        $len = mb_strlen($substr);
        if ($len === 0) {
            continue;
        }
        if (mb_substr($str, -$len) === $substr) {
            return $substr;
        }
    }

    return false;
}

/**
 * If we get errors, just echo 'em and try to carry on
 */
function _setReplErrorHandler()
{
    set_error_handler(function ($severity, $message) {
        echo($message . "\n");
    });
}
function _setReplShutdownFunction()
{
    register_shutdown_function(function() {
        $err = error_get_last();
        if ($err['type'] === E_ERROR || $err['type'] === E_USER_ERROR) {
            echo ("{$err['type']}\n");
        }
    });
}

/**
 * @param $paren
 *
 * @return null|string The opposite paren
 */
function _oppositeParen($paren)
{
    switch ($paren) {
        case "{":
            return "}";
        case "}":
            return "{";
        case "(":
            return ")";
        case ")":
            return "(";
        case "[":
            return "]";
        case "]":
            return "[";
        default:
            return NULL;
    }
}

/**
 * @param array $arr An array
 *
 * @return bool Whether the array is numeric
 */
function _isNumeric($arr)
{
    $next_key = 0;
    foreach (array_keys($arr) as $key) {
        if ($key === $next_key) {
            $next_key++;
        } else {
            return false;
        }
    }
    return true;
}
