<?php

namespace Tests\Unit\Content;

use Tests\Support\RaptorTestCase;

use codesaur\Template\MemoryTemplate;

use Dashboard\Content\ReferenceInitial;
use Dashboard\Content\ReferenceModel;

/**
 * 'password-changed' и-мэйл загвар: system_coder бусдын нууц үгийг сольсон үед
 * (by_admin) "системийн админ сольсон" гэж бичиж, админы IP-г харуулахгүй;
 * бусад үед "өөрөө солиогүй бол сэргээнэ үү" гэсэн анхааруулгатай.
 */
class PasswordChangedTemplateTest extends RaptorTestCase
{
    /** @return array<string, array{title:string, content:string}> хэл => контент */
    private function seededTemplate(): array
    {
        $captured = [];
        $model = $this->getMockBuilder(ReferenceModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['insert'])
            ->getMock();
        $model->method('insert')->willReturnCallback(
            function (array $record, array $content) use (&$captured) {
                if ($record['keyword'] === 'password-changed') {
                    $captured = $content;
                }
                return [];
            }
        );
        ReferenceInitial::reference_templates($model);
        $this->assertNotEmpty($captured, 'password-changed template must be seeded');
        return $captured;
    }

    private function render(string $content, bool $byAdmin): string
    {
        $body = new MemoryTemplate();
        $body->source($content);
        foreach (['username' => 'bat', 'email' => 'bat@example.com', 'date' => '2026-11-23 18:00',
            'ip' => '203.0.113.9', 'login' => 'https://example.com/dashboard/login', 'by_admin' => $byAdmin] as $k => $v) {
            $body->set($k, $v);
        }
        return $body->output();
    }

    public function testAdminChangeSaysAdministratorAndHidesIp(): void
    {
        $tpl = $this->seededTemplate();
        $mn = $this->render($tpl['mn']['content'], true);
        $this->assertStringContainsString('системийн админ', $mn);
        $this->assertStringNotContainsString('203.0.113.9', $mn);
        $this->assertStringNotContainsString('өөрөө солиогүй', $mn);

        $en = $this->render($tpl['en']['content'], true);
        $this->assertStringContainsString('changed by a system administrator', $en);
        $this->assertStringNotContainsString('203.0.113.9', $en);
        $this->assertStringNotContainsString('If you did not do this', $en);
    }

    public function testOwnChangeWarnsAndShowsIp(): void
    {
        $tpl = $this->seededTemplate();
        foreach (['mn' => 'өөрөө солиогүй', 'en' => 'If you did not do this'] as $code => $warning) {
            $html = $this->render($tpl[$code]['content'], false);
            $this->assertStringContainsString($warning, $html, $code);
            $this->assertStringContainsString('203.0.113.9', $html, $code);
            $this->assertStringContainsString('https://example.com/dashboard/login', $html, $code);
        }
    }

    public function testUsersControllerFlagsAdminChange(): void
    {
        $ref = new \ReflectionMethod(\Dashboard\User\UsersController::class, 'setPassword');
        $lines = \file($ref->getFileName());
        $body = \implode('', \array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
        $this->assertStringContainsString('$this->notifyPasswordChanged($record, null, !$isSelf)', $body);
    }
}
