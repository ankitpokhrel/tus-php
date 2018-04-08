<?php

namespace TusPhp\Middleware;

use TusPhp\Request;
use TusPhp\Response;
use TusPhp\Tus\Server;
use Illuminate\Http\Request as HttpRequest;

class GlobalHeaders implements MiddlewareInterface
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
            $headers += ['Tus-Resumable' => Server::TUS_PROTOCOL_VERSION];
        }

        $response->setHeaders($headers);
    }
}
