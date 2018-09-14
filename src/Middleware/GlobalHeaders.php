<?php

namespace TusPhp\Middleware;

use TusPhp\Request;
use TusPhp\Response;
use TusPhp\Tus\AbstractTus;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

class GlobalHeaders implements TusMiddleware
{
    /**
     * {@inheritDoc}
     */
    public function handle(Request $request, Response $response)
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (HttpRequest::METHOD_OPTIONS !== $request->method()) {
            $headers += ['Tus-Resumable' => AbstractTus::TUS_PROTOCOL_VERSION];
        }

        $response->setHeaders($headers);
    }
}
