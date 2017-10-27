<?php

/**
 * Utilize Reflection to check that the argument count is valid for a function or method
 * Note: This function does not check for the minimum number of arguments since PHP core handles that on invocation
 *
 * @param int $argumentCount The number of arguments to check against (for the current call scope, use func_num_args())
 * @param string|\Closure $nameOrClosure The name of the function or a closure to check the constraints for
 * @param string|null $className The name of the class, if any. This MUST be null for closures or global functions
 * @param array|null &$details Variable to be populated with details about constraints of the method or function
 *     'exactly' => boolean Indicates if there were no optional or variadic parameters
 *     'required' => int The number of required parameters
 *     'maximum' => int The total number of parameters accepted
 *     'variadic' => boolean Indicates if the function accepts a variadic of parameters
 *
 * @return bool Returns true if the $argumentCount is within the argument constraints of the provided function or method
 */
function check_num_args(int $argumentCount, $nameOrClosure, string $className = null, array &$details = null) : bool
{
    assert(func_num_args() <= 4);
    assert($argumentCount >= 0);
    assert($nameOrClosure instanceof \Closure || strlen($nameOrClosure) > 0);
    assert(is_null($className) || is_string($nameOrClosure) && strlen($className) > 0);

    if (null === $className) {
        assert($nameOrClosure instanceof \Closure || function_exists($nameOrClosure));
        $reflection = new \ReflectionFunction($nameOrClosure);
    } else {
        assert(method_exists($className, $nameOrClosure));
        $reflection = new \ReflectionMethod($className, $nameOrClosure);
    }

    $requiredParameters = $reflection->getNumberOfRequiredParameters();
    $maximumParameters = $reflection->getNumberOfParameters();
    $details = [
        'exactly' => false,
        'required' => $requiredParameters,
        'maximum' => $maximumParameters,
        'variadic' => false
    ];

    // Check if last parameter is variadic. If so, number of arguments is verified
    if (true === $reflection->isVariadic()) {
        $details['variadic'] = true;
        return true;
    }

    if ($requiredParameters === $maximumParameters) {
        $details['exactly'] = true;
        return $argumentCount === $maximumParameters;
    } else {
        return $argumentCount <= $maximumParameters;
    }
}

/**
 * Check that the current call scope does not exceed the maximum number of arguments
 * When utilizing this function from a Closure, the closure must be provided as the 2nd argument ($targetClosure)
 *
 * Example uses:
 *
 * // Basic usage
 * function doSomething(string $foo)
 * {
 *     assert(valid_num_args());
 * }
 *
 * // If you want to utilize the message that is generated for you, provide a variable for the 2nd argument
 * function doSomething(string $foo)
 * {
 *     assert(valid_num_args(null, $message), $message);
 * }
 *
 * // This usage is not valid because the closure must be provided as the first argument
 * call_user_func(function (string $first, bool $second) {
 *     assert(valid_num_args(null)); // Throws an InvalidArgumentException
 * }, 'a', 'b', 'c');
 *
 * // The is the correct usage
 * call_user_func($func = function (string $first, bool $second) use (&$func) {
 *     assert(valid_num_args($func)); // assertion fails because closure was called with 3 arguments
 * }, 'a', 'b', 'c');
 *
 * @param \Closure|null $targetClosure The Closure that is invoking the method, if any
 * @param string &$message This variable is populated with an error message on argument count violation
 *
 * @return bool Returns true if the function or method received a valid number of arguments
 * @throws \InvalidArgumentException When $targetClosure is not provided when invoked from a \Closure, or vice versa
 */
function valid_num_args(\Closure $targetClosure = null, string &$message = null) : bool
{
    assert(func_num_args() <= 2);

    $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
    assert(is_array($trace[1]), 'Parent scope (index 1) was returned');

    $parentScope = $trace[1];
    assert(
        is_string($parentScope['class'] ?? '')
        && is_string($parentScope['function'])
        && is_array($parentScope['args'])
    );

    $argumentCount = count($parentScope['args']);
    $functionName = $parentScope['function'];
    $className = $parentScope['class'] ?? null;

    $target = $functionName;
    if (substr_count($functionName, '{closure}') > 0) {
        $target = $targetClosure;
        $className = null;
        if (is_null($targetClosure)) {
            throw new \InvalidArgumentException('Argument 1 must be provided when invoked from a Closure');
        }
    } elseif (false === is_null($targetClosure)) {
        throw new \InvalidArgumentException('Argument 1 may only be provided when invoked from a Closure');
    }

    $checkedDetails = [];
    if (false === check_num_args($argumentCount, $target, $className, $checkedDetails)) {
        assert(is_bool($checkedDetails['exactly']) && is_int($checkedDetails['maximum']));

        $display = is_string($className) ? $className . '::' . $functionName : $functionName;
        $requirement = ($checkedDetails['exactly'] === true) ? 'exactly' : 'at most';
        $message = sprintf(
            '%s expected %s %d argument(s), but was provided %d.',
            $display,
            $requirement,
            $checkedDetails['maximum'],
            $argumentCount
        );
        return false;
    }

    return true;
}
