<?php

namespace TusPhp\Test\Fixtures;

use TusPhp\Request;
use TusPhp\Response;
use TusPhp\Middleware\TusMiddleware;

class TestMiddleware implements TusMiddleware
{
    /**
     * {@inheritDoc}
     */
    public function handle(Request $request, Response $response)
    {
        // Pass.
    }
}
