<?php

namespace Neat\Router;

use Generator;

class Router
{
    /** @var Mapper */
    private $mapper;

    /** @var Splitter */
    private $splitter;

    public function __construct(Mapper $mapper, Splitter $splitter)
    {
        $this->mapper   = $mapper;
        $this->splitter = $splitter;
    }

    public function map(string $path): Mapper
    {
        return $this->mapper->map($this->splitter->split($path));
    }

    public function match(string $path, array &$arguments = null, array &$middleware = null): Generator
    {
        yield from $this->mapper->match($this->splitter->split($path), $arguments, $middleware);
    }
}
