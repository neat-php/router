<?php

namespace Neat\Router\Test;

use Neat\Router\Mapper;
use Neat\Router\Router;
use Neat\Router\Splitter;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    public function testIn()
    {
        $router = new Router(new Mapper(''), new Splitter(' '));
        $group  = $router->map('test');
        $this->assertNotSame($router, $group);
    }

    private function router(): Router
    {
        $router = new Router(new Mapper(''), new Splitter('/'));
        $router->map('/test')->setHandler('test');
        $router->map('/test/$id:\d+')->setHandler('test-id-number');
        $router->map('/test/$id:\w+')->setHandler('test-id-word');
        $router->map('/arg/*')->setHandler('test-arg');

        return $router;
    }

    public function testAll()
    {
        $router = $this->router();

        $this->assertSame('test', $router->match('test')->current()->getHandler());
        $this->assertSame('test-id-number', $router->match('/test/5', $parameters)->current()->getHandler());
        $this->assertSame(['id' => '5'], $parameters);
        $this->assertSame('test-id-word', $router->match('/test/hello', $parameters)->current()->getHandler());
        $this->assertSame(['id' => 'hello'], $parameters);
        $this->assertSame('test-arg', $router->match('/arg/bla/5', $parameters)->current()->getHandler());
        $this->assertSame(['bla', '5'], $parameters);
        $this->assertSame('test-arg', $router->match('/arg/bla/5/and/more', $parameters)->current()->getHandler());
        $this->assertSame(['bla', '5', 'and', 'more'], $parameters);
    }

    public function testVariadic()
    {
        $router = new Router(new Mapper(''), new Splitter('/'));
        $router->map('/test')->setHandler('Test');
        $router->map('/test/...$all')->setHandler('TestVariadic');
        $router->map('/...$all')->setHandler('RootVariadic');

        $this->assertSame('Test', $router->match('/test', $arguments)->current()->getHandler());
        $this->assertSame([], $arguments);

        $this->assertSame('TestVariadic', $router->match('/test/first', $arguments)->current()->getHandler());
        $this->assertSame(['all' => ['first']], $arguments);

        $this->assertSame('TestVariadic', $router->match('/test/first/second', $arguments)->current()->getHandler());
        $this->assertSame(['all' => ['first', 'second']], $arguments);

        $this->assertSame('RootVariadic', $router->match('/root/first/second', $arguments)->current()->getHandler());
        $this->assertSame(['all' => ['root', 'first', 'second']], $arguments);
    }

    public function testWildcardVersusPartialMatch()
    {
        $router = new Router(new Mapper(''), new Splitter('/'));
        $router->map('/partial/path')->setHandler('test-partial-path');
        $router->map('/*')->setHandler('test-wildcard');

        $this->assertSame('test-wildcard', $router->match('/partial/')->current()->getHandler());
        $this->assertSame('test-wildcard', $router->match('/partial')->current()->getHandler());
        $this->assertSame('test-wildcard', $router->match('partial')->current()->getHandler());
    }

    public function testEmptyPathSegments()
    {
        $router = new Router(new Mapper(''), new Splitter('/'));
        $router->map('/a/b')->setHandler('test-a-b');
        $router->map('/c//d')->setHandler('test-c-d');
        $router->map('e')->setHandler('test-e');
        $router->map('')->setHandler('test-root');

        $this->assertSame('test-a-b', $router->match('a/b')->current()->getHandler());
        $this->assertSame('test-a-b', $router->match('/a//b')->current()->getHandler());
        $this->assertSame('test-a-b', $router->match('//a/b')->current()->getHandler());
        $this->assertSame('test-a-b', $router->match('//a//b')->current()->getHandler());

        $this->assertSame('test-c-d', $router->match('c/d')->current()->getHandler());
        $this->assertSame('test-c-d', $router->match('/c//d')->current()->getHandler());
        $this->assertSame('test-c-d', $router->match('//c/d')->current()->getHandler());
        $this->assertSame('test-c-d', $router->match('//c//d')->current()->getHandler());

        $this->assertSame('test-e', $router->match('e')->current()->getHandler());
        $this->assertSame('test-e', $router->match('/e')->current()->getHandler());
        $this->assertSame('test-e', $router->match('//e')->current()->getHandler());

        $this->assertSame('test-root', $router->match('')->current()->getHandler());
        $this->assertSame('test-root', $router->match('/')->current()->getHandler());
    }

    /**
     * Test middleware
     */
    public function testMiddleware()
    {
        $router = new Router(new Mapper(''), new Splitter('/'));
        $router->map('/')->setHandler('HomeController');
        $router->map('/admin')->setHandler('AdminController')->setMiddleware(['AuthenticationMiddleware']);
        $router->map('/admin/firewall')->setHandler('AdminController')->setMiddleware(['FirewallMiddleware']);

        $router->match('/', $arguments, $middleware)->current();
        $this->assertSame([], $middleware);

        $router->match('/admin', $arguments, $middleware)->current();
        $this->assertSame(['AuthenticationMiddleware'], $middleware);

        $router->match('/admin/firewall', $arguments, $middleware)->current();
        $this->assertSame(['AuthenticationMiddleware', 'FirewallMiddleware'], $middleware);
    }

    /**
     * Test middleware
     */
    public function testRecursiveMiddleware()
    {
        $router = new Router(new Mapper(''), new Splitter('/'));
        $router->map('')->setHandler('HomeController');

        $router->map('/admin')->setHandler('AdminController')->setMiddleware(['AuthenticationMiddleware']);
        $router->map('/admin/post')->setHandler('AdminController')->setMiddleware(['CsrfMiddleware']);

        $router->map('/admin/firewall')->setHandler('AdminController')->setMiddleware(['FirewallMiddleware']);
        $router->map('/admin/firewall/post')->setHandler('AdminController')->setMiddleware(['CsrfMiddleware']);

        $router->match('/', $arguments, $middleware)->current();
        $this->assertSame([], $middleware);

        $router->match('/admin', $arguments, $middleware)->current();
        $this->assertSame(['AuthenticationMiddleware'], $middleware);

        $router->match('/admin/post', $arguments, $middleware)->current();
        $this->assertSame(['AuthenticationMiddleware', 'CsrfMiddleware'], $middleware);

        $router->match('/admin/firewall', $arguments, $middleware)->current();
        $this->assertSame(['AuthenticationMiddleware', 'FirewallMiddleware'], $middleware);

        $router->match('/admin/firewall/post', $arguments, $middleware)->current();
        $this->assertSame(['AuthenticationMiddleware', 'FirewallMiddleware', 'CsrfMiddleware'], $middleware);
    }

    public function testMultipleMatches()
    {
        $mapper = new Router(new Mapper(''), new Splitter('/'));
        $mapper->map('/admin/firewall/post')->setHandler('literal');
        $mapper->map('/admin/firewall/$test')->setHandler('variable');
        $mapper->map('/admin/...$admin')->setHandler('variadic');
        $mapper->map('*')->setHandler('arg');

        $matches = $mapper->match('/admin/firewall/post', $arguments, $middleware);
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
        $mapper = new Router(new Mapper(''), new Splitter('/'));
        $mapper->map('/admin/firewall/post')->setHandler('literal');
        $this->assertNull($mapper->match('/test')->current());
    }
}
