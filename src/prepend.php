<?php

(function ($cliEntInput) {
    if (empty($cliEntInput)) {
        throw new Exception("GUZZLE_CLI_HANDLER environment variable hasn't been passed");
    }

    if (!is_array($cliEntInput)) {
        throw new Exception(sprintf('GUZZLE_CLI_HANDLER should be array, %s given', gettype($cliEntInput)));
    }

    foreach ($cliEntInput['globals'] ?? [] as $global => $variables) {
        $GLOBALS[$global] = $variables + ($GLOBALS[$global] ?? []);
    }

    ob_start(function ($output, $phase) {
        if ($phase & PHP_OUTPUT_HANDLER_FINAL || $phase & PHP_OUTPUT_HANDLER_END) {
            return 'HTTP/1.1 ' . (http_response_code() ?: 200) . "\r\n\r\n" . $output;
        }
        return $output;
    });

    // register_shutdown_function(function() {
    //     $httpStatusCode = (http_response_code() ?: 200);
    //     echo 'CLI_ENT_OUTPUT=' . base64_encode(
    //         "HTTP/1.1 {$httpStatusCode}" PHP_EOL . PHP_EOL
    //     ) . PHP_EOL;
    // });

    // [1] For some reason without this "useless" code we would have empty global variables
    // $_SERVER = $_SERVER;
    // $_GET = $_GET;
    // $_POST = $_POST;
    // $_FILES = $_FILES;
    // $_COOKIE = $_COOKIE;
    // $_SESSION = $_SESSION;
    // $_REQUEST = $_REQUEST;

    // Not set in cli
    $_ENV = $_ENV;
})(
    // JSON config
    json_decode((string) getenv('GUZZLE_CLI_HANDLER'), true)
);
