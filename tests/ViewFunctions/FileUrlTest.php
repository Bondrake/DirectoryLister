<?php

declare(strict_types=1);

namespace Tests\ViewFunctions;

use App\ViewFunctions\FileUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(FileUrl::class)]
class FileUrlTest extends TestCase
{
    #[Test]
    public function it_can_return_a_url(): void
    {
        $url = $this->container->get(FileUrl::class);

        $this->assertEquals('', $url('/'));
        $this->assertEquals('', $url('./'));
        $this->assertEquals('?dir=some/path', $url('some/path'));
        $this->assertEquals('?dir=some/path', $url('./some/path'));
        $this->assertEquals('?dir=some/path', $url('./some/path'));
        $this->assertEquals('?dir=some/file.test', $url('some/file.test'));
        $this->assertEquals('?dir=some/file.test', $url('./some/file.test'));
        $this->assertEquals('?dir=0/path', $url('0/path'));
        $this->assertEquals('?dir=1/path', $url('1/path'));
        $this->assertEquals('?dir=0', $url('0'));
    }

    #[Test]
    public function it_can_return_a_url_with_back_slashes(): void
    {
        $url = $this->container->make(FileUrl::class, ['directorySeparator' => '\\']);

        $this->assertEquals('', $url('\\'));
        $this->assertEquals('', $url('.\\'));
        $this->assertEquals('?dir=some\path', $url('some\path'));
        $this->assertEquals('?dir=some\path', $url('.\some\path'));
        $this->assertEquals('?dir=some\file.test', $url('some\file.test'));
        $this->assertEquals('?dir=some\file.test', $url('.\some\file.test'));
        $this->assertEquals('?dir=0\path', $url('0\path'));
        $this->assertEquals('?dir=1\path', $url('1\path'));
    }

    public function test_url_segments_are_url_encoded(): void
    {
        $url = $this->container->get(FileUrl::class);

        $this->assertEquals('?dir=foo/bar%2Bbaz', $url('foo/bar+baz'));
        $this->assertEquals('?dir=foo/bar%23baz', $url('foo/bar#baz'));
        $this->assertEquals('?dir=foo/bar%26baz', $url('foo/bar&baz'));
    }
}
