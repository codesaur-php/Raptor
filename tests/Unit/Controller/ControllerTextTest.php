<?php

namespace Tests\Unit\Controller;

use Tests\Support\RaptorTestCase;

/**
 * Controller::text(), getLanguageCode(), getLanguages() методуудын тест.
 *
 * Controller нь abstract тул тест дотор anonymous class ашиглан
 * конкрет instance үүсгэнэ.
 */
class ControllerTextTest extends RaptorTestCase
{
    private function createController(array $localization): object
    {
        $request = $this->createMockRequest([
            'pdo'          => $this->createMock(\PDO::class),
            'localization' => $localization,
        ]);

        // Raptor\Controller нь abstract тул anonymous class-аар өргөтгөх
        return new class($request) extends \Raptor\Controller {};
    }

    public function testTextReturnsTranslation(): void
    {
        $controller = $this->createController([
            'code' => 'mn',
            'text' => [
                'hello'   => 'Сайн уу',
                'goodbye' => 'Баяртай',
            ],
        ]);

        $this->assertEquals('Сайн уу', $controller->text('hello'));
        $this->assertEquals('Баяртай', $controller->text('goodbye'));
    }

    public function testTextReturnsDefaultWhenMissing(): void
    {
        $controller = $this->createController([
            'code' => 'mn',
            'text' => [],
        ]);

        $this->assertEquals('Fallback', $controller->text('missing_key', 'Fallback'));
    }

    public function testTextReturnsBracketedKeyWhenNoDefault(): void
    {
        $controller = $this->createController([
            'code' => 'mn',
            'text' => [],
        ]);

        $this->assertEquals('{missing_key}', $controller->text('missing_key'));
    }

    public function testGetLanguageCode(): void
    {
        $controller = $this->createController([
            'code' => 'en',
            'text' => [],
        ]);

        $this->assertEquals('en', $controller->getLanguageCode());
    }

    public function testGetLanguageCodeEmpty(): void
    {
        $controller = $this->createController([]);

        $this->assertEquals('', $controller->getLanguageCode());
    }

    public function testGetLanguages(): void
    {
        $languages = [
            ['code' => 'mn', 'title' => 'Монгол'],
            ['code' => 'en', 'title' => 'English'],
        ];

        $controller = $this->createController([
            'code'     => 'mn',
            'language' => $languages,
            'text'     => [],
        ]);

        $this->assertEquals($languages, $controller->getLanguages());
    }

    public function testGetLanguagesEmpty(): void
    {
        $controller = $this->createController([]);

        $this->assertEquals([], $controller->getLanguages());
    }
}
