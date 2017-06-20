ReplBreakpoint lets you start a REPL at any point in a PHP script and gives you access to the state of execution at that
point.

## Install

    composer require jonathrg/repl-breakpoint

## Usage

Here we define a variable and a function, and then call `repl(get_defined_vars())`.

    // File: examples/simple_example.php
    <?php
    require_once __DIR__.'/../vendor/autoload.php';
    use function ReplBreakpoint\repl;
    
    $a = 2;
    function sq($x) { return $x * $x; }
    
    repl(get_defined_vars());
Execute the script with PHP, and a REPL will be started on line 8.
It has access to the variables we defined in the parent script:

    $ php examples/simple_example.php
    REPL launched
    To exit, write "exit" or press Ctrl+C.
    > $a
    2
    > [$a, $a+2]
    [2, 4]
    > sq($a)
    4
A variable called `$_ANS` always keeps track of the last result:

    > $_ANS
    4

### Multiline code

Multiline code is fine: input is automatically buffered if
the parentheses are unbalanced...

    > function cube($x) {
        return sq($x)*$x;
        }
    
    > cube($_ANS)
    64
... or whenever you end a line with `\` or `/`.

    > $_ANS \
    
    > -2
    62

## Options

### Printer

You can swap out the function ReplBreakpoint uses to print results. Just add it as an option in your code:

    // File: examples/simple_example.php
    ...
    repl(get_defined_vars(), ['printer' => 'print_r']);

Results are now printed with `print_r`.

    $ php examples/simple_example.php
    REPL launched
    To exit, write "exit" or press Ctrl+C.
    > $a
    2
    > [$a, $a+2]
    Array
    (
        [0] => 2
        [1] => 4
    )

Any function which takes one parameter (the value to be printed) is OK.

### Function name, file name and line number

Provide `repl()` with a file and a line number to have it printed at the start of the session:

    // File: examples/simple_example.php
    ...
    repl(get_defined_vars(), ['file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__]);

Run it (we called `repl()` outside of a function context, so no function is shown):

    $ php examples/simple_example.php
    REPL launched in file path\to\examples\simple_example.php on line 8
    To exit, write "exit" or press Ctrl+C.
    > 

Or just set the 'quiet' flag to skip the info altogether:

    // File: examples/simple_example.php
    ...
    repl(get_defined_vars(), ['quiet' => true]);

Run it:

    $ php examples/simple_example.php
    >

### Error handler and shutdown function

By default, `repl()` will try to just echo the error and carry on. You can override this behaviour by providing your own
callbacks as 'error\_handler' and 'shutdown\_function' options. The callback must be compatible with `set_error_handler`
and `register_shutdown_function`, respectively.
