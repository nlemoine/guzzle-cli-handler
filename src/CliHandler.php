<?php

namespace HelloNico\GuzzleCliHandler;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Utils;
use HelloNico\GuzzleCliHandler\Contract\GlobalsParserInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class CliHandler
{
    public const ENV_NAME = 'GUZZLE_CLI_HANDLER';

    /** @var callable|null */
    private $globalsHandler;

    /** @var string */
    private string $documentRoot;

    /** @var string|null */
    private ?string $filePath;

    /** @var string */
    private string $prependFile;

    /** @var GlobalsParserInterface */
    private GlobalsParserInterface $globalsParser;

    /** @var callable|null */
    private $messageParser;

    /**
     * @param string $documentRoot
     * @param string|null $filePath
     * @param callable|null $globalsHandler
     * @param GlobalsParserInterface|null $globalsParser
     * @param callable|null $messageParser
     * @param string $prependFile
     *
     * @throws \Exception
     */
    public function __construct(
        string $documentRoot,
        ?string $filePath = null,
        ?callable $globalsHandler = null,
        ?GlobalsParserInterface $globalsParser = null,
        ?callable $messageParser = null,
        string $prependFile = null
    ) {
        $this->documentRoot = \rtrim($documentRoot, '/\\');
        $this->filePath = $filePath ?? $this->documentRoot . DIRECTORY_SEPARATOR . 'index.php';

        if ($prependFile && !\file_exists($prependFile)) {
            throw new \Exception(\sprintf("Specified append file '%' doesn't exist", $prependFile));
        }

        $this->prependFile = $prependFile ?: __DIR__ . '/prepend.php';
        $this->globalsParser = $globalsParser ?? new GlobalsParser();
        $this->messageParser = $messageParser ?? [Message::class, 'parseResponse'];
        $this->globalsHandler = $globalsHandler;
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function __invoke(RequestInterface $request): PromiseInterface
    {
        $uri = $request->getUri();

        $extension = \pathinfo($uri->getPath(), PATHINFO_EXTENSION);
        if ('php' === $extension) {
            $this->filePath = $this->documentRoot . DIRECTORY_SEPARATOR . $uri->getPath();
        }

        if (!\is_file((string) $this->filePath)) {
            throw new \Exception('File not found');
        }

        $globals = $this->globalsParser->parse($request, $this->documentRoot, $this->filePath);

        $this->globalsHandler && ($this->globalsHandler)($globals);

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
                self::ENV_NAME => Utils::jsonEncode(\compact('globals'))
            ], $globals['_ENV']),
            null,
            null
        );

        $process->run();

        $rawOutput = $process->getOutput();

        if (!\is_callable($this->messageParser)) {
            throw new \InvalidArgumentException('$messageParser mus be a callable');
        }
        $response = \call_user_func_array($this->messageParser, [$rawOutput]);

        return new FulfilledPromise($response);
    }
}
