<?php

namespace HelloNico\GuzzleCliHandler\Test;

use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use HelloNico\GuzzleCliHandler\CliHandler;
use PHPUnit\Framework\TestCase;

class CliHandlerTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testGlobals()
    {
        $handler = $this->getHandler('globals/index.php', function (array &$globals) {
            $globals['_ENV'] = ['a' => 'Lorem', 'b' => 'ipsum'];
        });

        /** @var Response $response */
        $response = $handler(
            new Request(
                'POST',
                'https://phpunit.localhost/cool-url/?G[a]=dolor&G[b]=sit',
                [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Cookie'       => 'C[a]=adipiscing; C[b]=elit',
                ],
                \http_build_query([
                    'P' => [
                        'a' => 'amet',
                        'b' => 'consectetur'
                    ],
                ])
            )
        )->wait();

        $this->assertEquals(
            \trim(\file_get_contents(__DIR__ . '/cases/globals/output.json')),
            \trim($response->getBody()->getContents())
        );
    }

    /**
     * @throws Exception
     */
    // public function testSession()
    // {
    //     $handler = $this->getHandler('session/index.php');

    //     $cookies = [];
    //     foreach (range(1, 10) as $try) {
    //         /** @var Response */
    //         $response = $handler(new Request(
    //             'GET',
    //             'https://phpunit.localhost/session/index.php',
    //             [
    //                 'Cookie' => implode('; ', $cookies),
    //             ]
    //         ))->wait();

    //         if ($response->hasHeader('Set-Cookie')) {
    //             $cookies = array_unique(array_merge($cookies, $response->getHeader('Set-Cookie')));
    //         }

    //         $this->assertEquals((string) $try, $response->getBody()->getContents());
    //     }
    // }

    /**
     * @throws Exception
     */
    public function testHttpCode()
    {
        $handler = $this->getHandler('http-code/index.php');

        /** @var Response $response */
        $response = $handler(
            new Request(
                'GET',
                'https://phpunit.localhost/http-code/',
                [
                    'Content-Type' => 'text/html',
                ]
            )
        )->wait();

        $this->assertEquals(
            404,
            $response->getStatusCode()
        );
    }

    /**
     * @throws Exception
     */
    public function testEnv()
    {
        $handler = $this->getHandler('env/index.php', function (array &$globals) {
            $globals['_ENV'] = ['foo' => 'bar'];
        });

        /** @var Response $response */
        $response = $handler(
            new Request(
                'POST',
                'https://phpunit.localhost/env/index.php',
                [
                    'Content-Type' => 'text/html',
                ]
            )
        )->wait();

        $this->assertEquals(
            \trim('barbar'),
            \trim($response->getBody()->getContents())
        );
    }

    /**
     * @param callable|null $globalsHandler
     *
     * @return CliHandler
     *
     * @throws Exception
     */
    private function getHandler($filePath = null, ?callable $globalsHandler = null): CliHandler
    {
        return new CliHandler(
            __DIR__ . '/cases',
            $filePath,
            $globalsHandler
        );
    }
}
