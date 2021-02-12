<?php

namespace Neat\Router\Test;

use Neat\Router\Mapper;
use PHPUnit\Framework\TestCase;

class MapperTest extends TestCase
{
    public function testIn()
    {
        $mapper = new Mapper('');
        $group  = $mapper->map(['test']);
        $this->assertNotSame($mapper, $group);
    }

    private function router(): Mapper
    {
        $mapper = new Mapper('');
        $mapper->map(['test'])->setHandler('test');
        $mapper->map(['test', '$id:\d+'])->setHandler('test-id-number');
        $mapper->map(['test', '$id:\w+'])->setHandler('test-id-word');
        $mapper->map(['test', '$extension:test\.(?<ext>pdf|html)'])->setHandler('test-extension');
        $mapper->map(['arg', '*'])->setHandler('test-arg');

        return $mapper;
    }

    public function testAll()
    {
        $mapper = $this->router();

        $this->assertSame('test', $mapper->match(['test'])->current()->getHandler());
        $this->assertSame('test-id-number', $mapper->match(['test', '5'], $parameters)->current()->getHandler());
        $this->assertSame(['id' => '5'], $parameters);
        $this->assertSame('test-id-word', $mapper->match(['test', 'hello'], $parameters)->current()->getHandler());
        $this->assertSame(['id' => 'hello'], $parameters);
        $this->assertSame('test-extension', $mapper->match(['test', 'test.pdf'], $parameters)->current()->getHandler());
        $this->assertSame(['extension' => 'test.pdf', 'ext' => 'pdf'], $parameters);
        $this->assertSame('test-arg', $mapper->match(['arg', 'bla', '5'], $parameters)->current()->getHandler());
        $this->assertSame(['bla', '5'], $parameters);
        $this->assertSame(
            'test-arg',
            $mapper->match(['arg', 'bla', '5', 'and', 'more'], $parameters)->current()->getHandler()
        );
        $this->assertSame(['bla', '5', 'and', 'more'], $parameters);
    }

    public function testVariadic()
    {
        $mapper = new Mapper('');
        $mapper->map(['test'])->setHandler('Test');
        $mapper->map(['test', '...$all'])->setHandler('TestVariadic');
        $mapper->map(['...$all'])->setHandler('RootVariadic');

        $this->assertSame('Test', $mapper->match(['test'], $arguments)->current()->getHandler());
        $this->assertSame([], $arguments);

        $this->assertSame('TestVariadic', $mapper->match(['test', 'first'], $arguments)->current()->getHandler());
        $this->assertSame(['all' => ['first']], $arguments);

        $this->assertSame(
            'TestVariadic',
            $mapper->match(['test', 'first', 'second'], $arguments)->current()->getHandler()
        );
        $this->assertSame(['all' => ['first', 'second']], $arguments);

        $this->assertSame(
            'RootVariadic',
            $mapper->match(['root', 'first', 'second'], $arguments)->current()->getHandler()
        );
        $this->assertSame(['all' => ['root', 'first', 'second']], $arguments);
    }

    public function testWildcardVersusPartialMatch()
    {
        $mapper = new Mapper('');
        $mapper->map(['partial', 'path'])->setHandler('test-partial-path');
        $mapper->map(['*'])->setHandler('test-wildcard');

        $this->assertSame('test-wildcard', $mapper->match(['partial'])->current()->getHandler());
    }

    public function testEmptyPathSegments()
    {
        $mapper = new Mapper('');
        $mapper->map(['a', 'b'])->setHandler('test-a-b');
        $mapper->map(['c', 'd'])->setHandler('test-c-d');
        $mapper->map(['e'])->setHandler('test-e');
        $mapper->map([])->setHandler('test-root');

        $this->assertSame('test-a-b', $mapper->match(['a', 'b'])->current()->getHandler());
        $this->assertSame('test-c-d', $mapper->match(['c', 'd'])->current()->getHandler());
        $this->assertSame('test-e', $mapper->match(['e'])->current()->getHandler());
        $this->assertSame('test-root', $mapper->match([])->current()->getHandler());
    }

    /**
     * Test middleware
     */
    public function testMiddleware()
    {
        $mapper = new Mapper('');
        $mapper->map([''])->setHandler('HomeController');
        $mapper->map(['admin'])->setHandler('AdminController')->setMiddleware(['AuthenticationMiddleware']);
        $mapper->map(['admin', 'firewall'])->setHandler('AdminController')->setMiddleware(['FirewallMiddleware']);

        $mapper->match([], $arguments, $middleware)->current();
        $this->assertSame([], $middleware);

        $mapper->match(['admin'], $arguments, $middleware)->current();
        $this->assertSame(['AuthenticationMiddleware'], $middleware);

        $mapper->match(['admin', 'firewall'], $arguments, $middleware)->current();
        $this->assertSame(['AuthenticationMiddleware', 'FirewallMiddleware'], $middleware);
    }

    /**
     * Test middleware
     */
    public function testRecursiveMiddleware()
    {
        $mapper = new Mapper('');
        $mapper->setHandler('HomeController');

        $mapper->map(['admin'])->setHandler('AdminController')->setMiddleware(['AuthenticationMiddleware']);
        $mapper->map(['admin', 'post'])->setHandler('AdminController')->setMiddleware(['CsrfMiddleware']);

        $mapper->map(['admin', 'firewall'])->setHandler('AdminController')->setMiddleware(['FirewallMiddleware']);
        $mapper->map(['admin', 'firewall', 'post'])->setHandler('AdminController')->setMiddleware(['CsrfMiddleware']);

        $mapper->match([], $arguments, $middleware)->current();
        $this->assertSame([], $middleware);

        $mapper->match(['admin'], $arguments, $middleware)->current();
        $this->assertSame(['AuthenticationMiddleware'], $middleware);

        $mapper->match(['admin', 'post'], $arguments, $middleware)->current();
        $this->assertSame(['AuthenticationMiddleware', 'CsrfMiddleware'], $middleware);

        $mapper->match(['admin', 'firewall'], $arguments, $middleware)->current();
        $this->assertSame(['AuthenticationMiddleware', 'FirewallMiddleware'], $middleware);

        $mapper->match(['admin', 'firewall', 'post'], $arguments, $middleware)->current();
        $this->assertSame(['AuthenticationMiddleware', 'FirewallMiddleware', 'CsrfMiddleware'], $middleware);
    }

    public function testMultipleMatches()
    {
        $mapper = new Mapper('');
        $mapper->map(['admin', 'firewall', 'post'])->setHandler('literal');
        $mapper->map(['admin', 'firewall', '$test'])->setHandler('variable');
        $mapper->map(['admin', '...$admin'])->setHandler('variadic');
        $mapper->map(['*'])->setHandler('arg');

        $matches = $mapper->match(['admin', 'firewall', 'post'], $arguments, $middleware);
        $this->assertSame('literal', $matches->current()->getHandler());
        $this->assertSame([], $arguments);
        $matches->next();
        $this->assertSame('variable', $matches->current()->getHandler());
        $this->assertSame(['test' => 'post'], $arguments);
        $matches->next();
        $this->assertSame('variadic', $matches->current()->getHandler());
        $this->assertSame(['admin' => ['firewall', 'post']], $arguments);
        $matches->next();
        $this->assertSame('arg', $matches->current()->getHandler());
        $this->assertSame(['admin', 'firewall', 'post'], $arguments);
    }

    public function testNoMatches()
    {
        $mapper = new Mapper('');
        $mapper->map(['admin', 'firewall', 'post'])->setHandler('literal');
        $this->assertNull($mapper->match(['test'])->current());
    }
}
