<?php
if (defined('LOG_PHP_INCLUDED')) return;
define('LOG_PHP_INCLUDED', true);

date_default_timezone_set('Europe/Brussels');

if (!function_exists('debug_log')) {
    function debug_log($msg, $extra = []) {
        $logfile = __DIR__ . '/error_log.txt';
        $time = date("[Y-m-d H:i:s]");
        $data = !empty($extra) ? print_r($extra, true) : '';
        if (!file_exists($logfile)) {
            @touch($logfile);
            @chmod($logfile, 0666);
        }
        @file_put_contents($logfile, "$time $msg $data\n", FILE_APPEND | LOCK_EX);
    }
}

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    $logfile = __DIR__ . '/error_log.txt';
    $datetime = date('Y-m-d H:i:s');
    $error_type = match ($errno) {
        E_ERROR => 'FATAL ERROR', E_WARNING => 'WARNING', E_NOTICE => 'NOTICE',
        E_PARSE => 'PARSE ERROR', E_CORE_ERROR => 'CORE ERROR', E_COMPILE_ERROR => 'COMPILE ERROR',
        E_USER_ERROR => 'USER ERROR', E_USER_WARNING => 'USER WARNING', E_USER_NOTICE => 'USER NOTICE',
        E_STRICT => 'STRICT', E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
        E_DEPRECATED => 'DEPRECATED', E_USER_DEPRECATED => 'USER DEPRECATED',
        default => 'UNKNOWN',
    };
    $log_message = "[$datetime] PHP $error_type: $errstr in $errfile on line $errline\n";
    if (!file_exists($logfile)) { @touch($logfile); @chmod($logfile, 0666); }
    @file_put_contents($logfile, $log_message, FILE_APPEND | LOCK_EX);
    if (in_array($errno, [E_WARNING, E_NOTICE, E_STRICT, E_DEPRECATED, E_USER_DEPRECATED])) return true;
    return false;
});

set_exception_handler(function ($e) {
    $logfile = __DIR__ . '/error_log.txt';
    $datetime = date('Y-m-d H:i:s');
    $log_message = "[$datetime] UNCAUGHT EXCEPTION/ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
    if (!file_exists($logfile)) { @touch($logfile); @chmod($logfile, 0666); }
    @file_put_contents($logfile, $log_message, FILE_APPEND | LOCK_EX);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        $logfile = __DIR__ . '/error_log.txt';
        $datetime = date('Y-m-d H:i:s');
        $log_message = "[$datetime] PHP FATAL ERROR: {$error['message']} in {$error['file']} on line {$error['line']}\n";
        if (!file_exists($logfile)) { @touch($logfile); @chmod($logfile, 0666); }
        @file_put_contents($logfile, $log_message, FILE_APPEND | LOCK_EX);
    }
});