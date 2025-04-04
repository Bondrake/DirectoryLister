<?php

declare(strict_types=1);

namespace Tests\ViewFunctions;

use App\ViewFunctions\Translate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Tests\TestCase;

#[CoversClass(Translate::class)]
class TranslateTest extends TestCase
{
    /** @var Translator Translator component */
    private $translator;

    public function setUp(): void
    {
        parent::setUp();

        $this->translator = new Translator('en');
        $this->translator->addLoader('array', new ArrayLoader);
        $this->translator->addResource('array', [
            'foo' => 'Foo', 'bar' => ['baz' => 'Bar Baz'],
        ], 'en');
        $this->translator->addResource('array', [
            'foo' => 'Le Foo', 'bar' => ['baz' => 'Le Bar Baz'],
        ], 'fr');
    }

    #[Test]
    public function it_can_get_a_translation_for_the_defualt_locale(): void
    {
        $translate = new Translate($this->translator);

        $this->assertEquals('Foo', $translate('foo'));
        $this->assertEquals('Bar Baz', $translate('bar.baz'));
    }

    #[Test]
    public function it_can_get_a_translation_for_an_alternative_locale(): void
    {
        $this->translator->setLocale('fr');
        $translate = new Translate($this->translator);

        $this->assertEquals('Le Foo', $translate('foo'));
        $this->assertEquals('Le Bar Baz', $translate('bar.baz'));
    }
}
