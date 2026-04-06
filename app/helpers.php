<?php

if (! function_exists('excel_safe')) {
    /**
     * Sanitize a user-supplied string value for safe rendering in an Excel cell.
     *
     * Prevents CSV/Excel formula injection by prefixing values that start with
     * a formula-trigger character (=, +, -, @, tab, carriage-return) with a
     * single quote, which Excel treats as a text prefix rather than executing
     * the value as a formula.
     */
    function excel_safe(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
