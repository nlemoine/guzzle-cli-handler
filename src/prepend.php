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

    http_response_code(200);

    register_shutdown_function(function () {
        while (@ob_end_flush());
        $httpStatusCode = (http_response_code() ?: 200);
        echo "{$httpStatusCode}";
    });

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
    json_decode((string) getenv('GUZZLE_CLI_HANDLER'), true)
);
