# Guzzle CLI handler

Guzzle [handler](https://docs.guzzlephp.org/en/stable/handlers-and-middleware.html#handlers) to imitate HTTP calls
through CLI.

## Motivation

Execute requests without running a webserver.

## Usage

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use HelloNico\GuzzleCliHandler\CliHandler;

// Document root
$docRoot = '/var/www';

// File path
// - If absolute, will directly use $filePath
// - If relative, will be `$docRoot . DIRECTORY_SEPARATOR . $filePath`
// - If URL requested ends with .php, will be overrided and set to `$docRoot . DIRECTORY_SEPARATOR . $urlPath`
$filePath = 'index.php';

$cliHandler = new CliHandler(
    $docRoot,
    $filePath,
    function (array &$globals) {
        // you can mofify global variables here before execution
        $globals['_ENV'] = ['a' => 'Lorem', 'b' => 'ipsum'];
    }
);

$client = new Client(['handler' => HandlerStack::create($cliHandler)]);
$response = $client->get('http://localhost/install.php');
```

## Installing

```
composer require hellonico/guzzle-cli-handler
```

## Limitations

Due to PHP running in CLI, it's unfortunately not possible to get response headers and maintain sessions.

## Developing

* `composer test` - run phpunit
* `composer analyse` - run phpstan
* `composer lint` - run ecs (coding standards)
