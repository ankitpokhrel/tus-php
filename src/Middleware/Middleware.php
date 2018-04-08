<?php

namespace TusPhp\Middleware;

class Middleware
{
    /** @var array */
    protected $globalMiddleware = [];

    /**
     * Middleware constructor.
     */
    public function __construct()
    {
        $this->globalMiddleware = [
            GlobalHeaders::class => new GlobalHeaders(),
            Cors::class => new Cors(),
        ];
    }

    /**
     * Set middleware.
     *
     * @param array $middleware
     *
     * @return Middleware
     */
    public function add(...$middleware) : self
    {
        foreach ($middleware as $m) {
            if ($m instanceof MiddlewareInterface) {
                $this->globalMiddleware[get_class($m)] = $m;
            } else if (is_string($m)) {
                $this->globalMiddleware[$m] = new $m;
            }
        }

        return $this;
    }

    /**
     * Get registered middleware.
     *
     * @return array
     */
    public function list() : array
    {
        return $this->globalMiddleware;
    }

    /**
     * Skip middleware.
     *
     * @param array ...$middleware
     *
     * @return Middleware
     */
    public function skip(...$middleware) : self
    {
        foreach ($middleware as $m) {
            unset($this->globalMiddleware[$m]);
        }

        return $this;
    }
}
