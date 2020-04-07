<?php

namespace Neat\Router;

class Splitter
{
    /** @var string */
    private $splitter;

    public function __construct(string $delimiter)
    {
        $this->splitter = $delimiter;
    }

    public function split(string $path): array
    {
        $parts = explode($this->splitter, $path);
        $filtered = array_filter($parts);

        return array_values($filtered);
    }
}
