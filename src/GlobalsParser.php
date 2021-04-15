<?php

namespace HelloNico\GuzzleCliHandler;

use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Psr7\Header;
use HelloNico\GuzzleCliHandler\Contract\GlobalsParserInterface;
use Psr\Http\Message\RequestInterface;

class GlobalsParser implements GlobalsParserInterface
{
    public const DEFAULT_REQUEST_ORDER = 'EGPCS';

    /** @var string */
    private $requestOrder;

    public function __construct(string $requestOrder = self::DEFAULT_REQUEST_ORDER)
    {
        $this->requestOrder = $requestOrder;
    }

    /**
     * @param RequestInterface $request
     * @param string|null $documentRoot
     * @param string|null $filePath
     * @return array
     */
    public function parse(RequestInterface $request, ?string $documentRoot = null, ?string $filePath = null): array
    {
        $globals = [
            '_ENV'     => [],
            '_GET'     => [],
            '_POST'    => [],
            '_COOKIE'  => [],
            '_SESSION' => [],
            '_REQUEST' => [],
            '_SERVER'  => [],
        ];

        $uri = $request->getUri();
        $extension = \pathinfo($uri->getPath(), PATHINFO_EXTENSION);

        // $_GET
        \parse_str($uri->getQuery(), $globals['_GET']);

        // $_POST
        $contentType = $request->getHeader('Content-Type');
        switch ($contentType) {
            case 'application/x-www-form-urlencoded':
            default:
                \parse_str($request->getBody()->getContents(), $globals['_POST']);
        }

        // $_COOKIES
        $cookies = Header::parse($request->getHeader('Cookie'));
        foreach ($cookies as $cookieGroup) {
            $cookiesObjects = \array_map(function ($name, $value) {
                return SetCookie::fromString(\sprintf('%s=%s', $name, $value));
            }, \array_keys($cookieGroup), $cookieGroup);
            foreach ($cookiesObjects as $cookie) {
                $globals['_COOKIE'] = \array_merge($globals['_COOKIE'], [$cookie->getName() => $cookie->getValue()]);
            }
        }

        // $_REQUEST
        if ($this->requestOrder) {
            foreach (\str_split($this->requestOrder, 1) as $globalPart) {
                foreach (\array_keys($globals) as $globalPartFullName) {
                    if (\strpos($globalPartFullName, "_{$globalPart}") === 0) {
                        $globals['_REQUEST'] = \array_merge($globals['_REQUEST'], $globals[$globalPartFullName]);
                    }
                }
            }
        }

        // $_SERVER
        $phpSelf = 'php' === $extension ? $uri->getPath() : \str_replace((string) $documentRoot, '', (string) $filePath);
        $globals['_SERVER'] = \array_merge($globals['_SERVER'], [
            'REQUEST_METHOD'  => $request->getMethod(),
            'REQUEST_URI'     => $uri->getPath(),
            'HTTP_HOST'       => $uri->getHost(),
            'SERVER_NAME'     => $uri->getHost(),
            'QUERY_STRING'    => \urldecode($uri->getQuery()),
            'PHP_SELF'        => $phpSelf,
            'SCRIPT_NAME'     => $phpSelf,
            'SCRIPT_FILENAME' => $filePath,
            'DOCUMENT_ROOT'   => $documentRoot ?? '',
            'SERVER_PROTOCOL' => 'HTTP/' . $request->getProtocolVersion(),
            'SERVER_PORT'     => $uri->getScheme() === 'https' ? '443' : '80',
            'HTTPS'           => $uri->getScheme() === 'https' ? 'on' : 'off',
        ]);

        return $globals;
    }
}
