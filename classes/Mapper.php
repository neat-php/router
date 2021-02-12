<?php

namespace Neat\Router;

use Generator;

class Mapper
{
    /** @var string */
    private $segment;

    /** @var string */
    private $name;

    /** @var string */
    private $expression;

    /** @var self[] */
    private $literals = [];

    /** @var self[] */
    private $variables = [];

    /** @var self|null */
    private $wildcard;

    /** @var self|null */
    private $variadic;

    /** @var callable */
    private $handler;

    /** @var array */
    private $middleware = [];

    public function __construct(string $segment)
    {
        $this->segment = $segment;
        if (!$segment) {
            return;
        }
        if (strpos($segment, '...$') === 0) {
            $this->name = substr($segment, 4);
        }
        if (preg_match('/^\$([^:]+)(?::(.*))?$/', $segment, $match)) {
            $this->name       = $match[1];
            $this->expression = isset($match[2]) ? "/^$match[2]$/" : null;
        }
    }

    /**
     * Is variable segment?
     *
     * @return bool
     */
    private function isVariable(): bool
    {
        return $this->segment && $this->segment[0] == '$';
    }

    /**
     * Is variadic segment?
     *
     * @return bool
     */
    private function isVariadic(): bool
    {
        return $this->segment && strpos($this->segment, '...$') === 0;
    }

    /**
     * Is wildcard segment?
     *
     * @return bool
     */
    private function isWildcard(): bool
    {
        return $this->segment == '*';
    }

    /**
     * @param callable $handler
     * @return $this
     */
    public function setHandler($handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * @return callable|null
     */
    public function getHandler()
    {
        return $this->handler;
    }


    /**
     * @param array $middleware
     * @return $this
     */
    public function setMiddleware($middleware): self
    {
        $this->middleware = $middleware;

        return $this;
    }

    /**
     * @param array $segments
     * @return static
     */
    public function map(array $segments): self
    {
        if (!$segment = array_shift($segments)) {
            return $this;
        }

        $map = $this->literals[$segment]
            ?? $this->variables[$segment]
            ?? (strpos($segment, '...$') === null ? $this->variadic : null)
            ?? ($segment == '*' ? $this->wildcard : null);

        if (!$map) {
            $map = new static($segment);
            if ($map->isWildcard()) {
                $this->wildcard = $map;
            } elseif ($map->isVariable()) {
                $this->variables[$segment] = $map;
            } elseif ($map->isVariadic()) {
                $this->variadic = $map;
            } else {
                $this->literals[$segment] = $map;
            }
        }

        return $map->map($segments);
    }

    /**
     * @param array      $segments
     * @param array|null $arguments
     * @param array|null $middleware
     * @return Generator|static[]
     */
    public function match(array $segments, array &$arguments = null, array &$middleware = null): Generator
    {
        $arguments  = [];
        $middleware = $this->middleware;

        yield from $this->matchSegments($segments, $arguments, $middleware);
    }

    /**
     * @param array      $segments
     * @param array|null $arguments
     * @param array|null $middleware
     * @return Generator|static[]
     */
    private function matchSegments(array $segments, array &$arguments, array &$middleware): Generator
    {
        if (!$segments) {
            yield $this;
            return;
        }

        $segment = array_shift($segments);
        yield from $this->matchLiteral($segment, $segments, $arguments, $middleware);
        yield from $this->matchVariable($segment, $segments, $arguments, $middleware);
        yield from $this->matchVariadic($segment, $segments, $arguments, $middleware);
        yield from $this->matchWildcard($segment, $segments, $arguments, $middleware);
    }

    /**
     * @param string $segment
     * @param array  $segments
     * @param array  $arguments
     * @param array  $middleware
     * @return Generator
     */
    private function matchLiteral(string $segment, array $segments, array &$arguments, array &$middleware): Generator
    {
        $literal = $this->literals[$segment] ?? null;
        if (!$literal) {
            return;
        }
        foreach ($literal->matchSegments($segments, $arguments, $middleware) as $match) {
            if (!$match->handler) {
                continue;
            }
            array_splice($middleware, 0, 0, $literal->middleware);

            yield $match;
        }
    }

    private function matchVariable(string $segment, array $segments, array &$arguments, array &$middleware): Generator
    {
        foreach ($this->variables as $variable) {
            $matches = [];
            if ($variable->expression && !preg_match($variable->expression, $segment, $matches)) {
                continue;
            }
            foreach ($variable->matchSegments($segments, $arguments, $middleware) as $match) {
                $arguments[$variable->name] = $segment;
                foreach ($matches as $key => $value) {
                    if (is_int($key)) {
                        continue;
                    }
                    $arguments[$key] = $value;
                }
                array_splice($middleware, 0, 0, $variable->middleware);

                yield $match;
                unset($arguments[$variable->name]);
                foreach ($matches as $key => $value) {
                    if (is_int($key)) {
                        continue;
                    }
                    unset($arguments[$key]);
                }
            }
        }
    }

    private function matchVariadic(string $segment, array $segments, array &$arguments, array &$middleware): Generator
    {
        if ($this->variadic && $this->variadic->handler) {
            array_unshift($segments, $segment);
            $arguments[$this->variadic->name] = $segments;
            array_splice($middleware, 0, 0, $this->variadic->middleware);

            yield $this->variadic;
            unset($arguments[$this->variadic->name]);
        }
    }

    private function matchWildcard(string $segment, array $segments, array &$arguments, array &$middleware): Generator
    {
        if ($this->wildcard && $this->wildcard->handler) {
            array_unshift($segments, $segment);
            $arguments = array_merge($arguments, $segments);
            array_splice($middleware, 0, 0, $this->wildcard->middleware);

            yield $this->wildcard;
        }
    }
}
