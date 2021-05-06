<?php

namespace HelloNico\GuzzleCliHandler;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise as P;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7;
use GuzzleHttp\TransferStats;
use GuzzleHttp\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class CliHandler
{
    public const ENV_NAME = 'GUZZLE_CLI_HANDLER';

    /** @var string */
    private string $documentRoot;

    /** @var string|null */
    private ?string $filePath;

    /** @var callable|null */
    private $globalsHandler;

    /** @var string|null */
    private ?string $prependFile;

    /**
     * @param string $documentRoot
     * @param string|null $filePath
     * @param callable|null $globalsHandler
     * @param string|null $prependFile
     *
     * @throws \Exception
     */
    public function __construct(
        string $documentRoot,
        ?string $filePath = null,
        ?callable $globalsHandler = null,
        ?string $prependFile = null
    ) {
        $this->documentRoot = \rtrim($documentRoot, '/\\');

        // Absolute path
        if ($filePath) {
            if ($this->isAbsolutePath($filePath)) {
                $this->filePath = $filePath;
            } else {
                $this->filePath = $this->documentRoot . DIRECTORY_SEPARATOR . $filePath;
            }
        } else {
            $this->filePath = $this->documentRoot . DIRECTORY_SEPARATOR . 'index.php';
        }

        if ($prependFile && !\file_exists($prependFile)) {
            throw new \Exception(\sprintf("Specified append file '%' doesn't exist", $prependFile));
        }

        $this->prependFile = $prependFile ?: __DIR__ . '/prepend.php';
        $this->globalsHandler = $globalsHandler;
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function __invoke(RequestInterface $request, array $options = []): PromiseInterface
    {
        // Sleep if there is a delay specified.
        if (isset($options['delay'])) {
            \usleep($options['delay'] * 1000);
        }

        $startTime = isset($options['on_stats']) ? Utils::currentTime() : null;

        try {
            return $this->createResponse(
                $request,
                $options,
                $startTime
            );
        } catch (ProcessTimedOutException $e) {
            $e = new ConnectException($e->getMessage(), $request, $e);
            return P\Create::rejectionFor($e);
        } catch (\Exception $e) {
            $this->invokeStats($options, $request, $startTime, null, $e);
            return P\Create::rejectionFor($e);
        }
    }

    /**
     * @param RequestInterface $request
     * @param array $options
     * @param float|null $startTime
     * @return PromiseInterface
     */
    private function createResponse(RequestInterface $request, array $options, ?float $startTime): PromiseInterface
    {
        $uri = $request->getUri();

        $extension = \pathinfo($uri->getPath(), PATHINFO_EXTENSION);
        if ('php' === $extension) {
            $this->filePath = $this->documentRoot . DIRECTORY_SEPARATOR . $uri->getPath();
        }

        if (!\is_file((string) $this->filePath)) {
            throw new \Exception(\sprintf('File not found in %s', $this->filePath));
        }

        $globals = $this->getGlobals($request);

        if (\is_callable($this->globalsHandler)) {
            ($this->globalsHandler)($globals);
        }

        // Reset $_SERVER / $_ENV
        // @see https://github.com/symfony/process/blob/98cb8eeb72e55d4196dd1e36f1f16e7b3a9a088e/Process.php#L308
        $tmp_SERVER = $_SERVER;
        $tmp_ENV = $_ENV;
        $_SERVER = [];
        $_ENV = [];

        $process = new Process(
            \array_merge(
                [(new PhpExecutableFinder())->find()],
                [
                    "-d",
                    "auto_prepend_file={$this->prependFile}",
                    $this->filePath,
                ]
            ),
            null,
            // Make environement variables avalaibe via getenv / $_ENV
            \array_merge([
                self::ENV_NAME => Utils::jsonEncode(\compact('globals')),
            ], $globals['_ENV']),
            null,
            null
        );

        if (isset($options['timeout'])) {
            $process->setTimeout($options['timeout']);
        }

        try {
            $process->mustRun();
        } catch (\Exception $exception) {
            return P\Create::rejectionFor($exception);
        }

        // Restore $_ENV & $_SERVER
        $_SERVER = $tmp_SERVER;
        $_ENV = $tmp_ENV;

        $body = $process->getOutput();

        $status = (int) \substr($body, -3);

        $stream = Psr7\Utils::streamFor(\substr($body, 0, -3));
        $stream = $this->checkDecode($options, $stream);
        $stream = Psr7\Utils::streamFor($stream);

        try {
            $response = new Psr7\Response($status, [], $stream);
        } catch (\Exception $e) {
            return P\Create::rejectionFor(
                new RequestException('An error was encountered while creating the response', $request, null, $e)
            );
        }

        if (isset($options['on_headers'])) {
            try {
                $options['on_headers']($response);
            } catch (\Exception $e) {
                return P\Create::rejectionFor(
                    new RequestException('An error was encountered during the on_headers event', $request, $response, $e)
                );
            }
        }

        $this->invokeStats($options, $request, $startTime, $response, null);

        return new FulfilledPromise($response);
    }

    /**
     * @param RequestInterface $request
     * @return array
     */
    private function getGlobals(RequestInterface $request): array
    {
        \parse_str($request->getBody()->getContents(), $post);

        $serverRequest = Request::create(
            \urldecode((string) $request->getUri()),
            $request->getMethod(),
            $post,
            Psr7\Header::parse($request->getHeader('Cookie'))[0] ?? [],
            [],
            $this->getServerGlobals($request),
            $request->getBody()->getContents()
        );

        $serverRequest->headers->add($request->getHeaders());

        // @see \Symfony\Component\HttpFoundation\Request::overrideGlobals()
        $serverRequest->server->set('QUERY_STRING', \urldecode(Request::normalizeQueryString(\http_build_query($serverRequest->query->all(), '', '&'))));

        $globals = [
            '_ENV'     => [],
            '_GET'     => $serverRequest->query->all(),
            '_POST'    => $serverRequest->request->all(),
            '_COOKIE'  => $serverRequest->cookies->all(),
            '_SESSION' => [],
            '_SERVER'  => $serverRequest->server->all(),
        ];

        foreach ($serverRequest->headers->all() as $key => $value) {
            $key = \strtoupper(\str_replace('-', '_', $key));
            if (\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $globals['_SERVER'][$key] = \implode(', ', $value);
            } else {
                $globals['_SERVER']['HTTP_' . $key] = \implode(', ', $value);
            }
        }

        $request = ['g' => $globals['_GET'], 'p' => $globals['_POST'], 'c' => $globals['_COOKIE']];

        $requestOrder = \ini_get('request_order') ?: \ini_get('variables_order');
        /** @phpstan-ignore-next-line */
        $requestOrder = \preg_replace('#[^cgp]#', '', \strtolower($requestOrder)) ?: 'gp';

        $globals['_REQUEST'] = [[]];

        foreach (\str_split($requestOrder) as $order) {
            $globals['_REQUEST'][] = $request[$order];
        }

        $globals['_REQUEST'] = \array_merge(...$globals['_REQUEST']);

        return $globals;
    }

    /**
     * @param RequestInterface $request
     * @return array
     */
    public function getServerGlobals(RequestInterface $request): array
    {
        $uri = $request->getUri();
        $extension = \pathinfo($uri->getPath(), PATHINFO_EXTENSION);
        $phpSelf = 'php' === $extension ? $uri->getPath() : \str_replace((string) $this->documentRoot, '', (string) $this->filePath);

        return [
            'PHP_SELF'        => $phpSelf,
            'SCRIPT_NAME'     => $phpSelf,
            'SCRIPT_FILENAME' => $this->filePath,
            'DOCUMENT_ROOT'   => $this->documentRoot ?? '',
            'SERVER_PROTOCOL' => 'HTTP/' . $request->getProtocolVersion(),
        ];
    }

    /**
     * @param array $options
     * @param RequestInterface $request
     * @param float|null $startTime
     * @param ResponseInterface $response
     * @param \Throwable $error
     * @return void
     */
    private function invokeStats(
        array $options,
        RequestInterface $request,
        ?float $startTime,
        ResponseInterface $response = null,
        \Throwable $error = null
    ): void {
        if (isset($options['on_stats'])) {
            $stats = new TransferStats($request, $response, Utils::currentTime() - $startTime, $error, []);
            ($options['on_stats'])($stats);
        }
    }

    private function checkDecode(array $options, StreamInterface $stream): StreamInterface
    {
        // Automatically decode responses when instructed.
        if (!empty($options['decode_content'])) {
            $header = $stream->read(3);
            $stream->rewind();
            // Check gzip header
            if (0 === \strpos($header, "\x1f\x8b\x08")) {
                $stream = new Psr7\InflateStream(Psr7\Utils::streamFor($stream));
            }
        }

        return $stream;
    }

    /**
     * Returns whether the file path is an absolute path.
     *
     * @return bool
     */
    public function isAbsolutePath(string $file)
    {
        return '' !== $file && (
            \strspn($file, '/\\', 0, 1)
            || (
                \strlen($file) > 3 && \ctype_alpha($file[0])
                && ':' === $file[1]
                && \strspn($file, '/\\', 2, 1)
            )
            || null !== \parse_url($file, \PHP_URL_SCHEME)
        );
    }
}
