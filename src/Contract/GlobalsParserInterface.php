<?php

namespace HelloNico\GuzzleCliHandler\Contract;

use Psr\Http\Message\RequestInterface;

interface GlobalsParserInterface
{
    public function parse(RequestInterface $request, ?string $documentRoot, ?string $filePath): array;
}
