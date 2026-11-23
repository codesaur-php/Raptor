<?php

namespace Tests\Unit\Controller;

use Tests\Support\RaptorTestCase;

use codesaur\Template\MemoryTemplate;

use Dashboard\Content\ReferenceInitial;
use Dashboard\Content\ReferenceModel;
use Dashboard\Notification\DiscordListener;
use Dashboard\Notification\DiscordNotifier;
use Dashboard\Notification\UserEvent;
use Dashboard\User\UsersController;

/**
 * Хэрэглэгчийн дүр / байгууллага өөрчлөгдөхөд:
 *   - хэрэглэгчид 'access-changed' и-мэйл очно (өөрчлөлт бүрд)
 *   - өндөр эрхтэй дүр олгогдсон / хасагдсан бол Discord-д админуудад очно
 * configureRoles() / configureOrgs() дууддаг action бүр notifyAccessChanged()-г дуудна.
 */
class AccessChangeNotificationTest extends RaptorTestCase
{
    private function methodBody(string $method): string
    {
        $ref = new \ReflectionMethod(UsersController::class, $method);
        $lines = \file($ref->getFileName());
        return \implode('', \array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
    }

    public function testEveryActionThatChangesAccessNotifies(): void
    {
        $callers = [];
        foreach ((new \ReflectionClass(UsersController::class))->getMethods() as $m) {
            if ($m->getDeclaringClass()->getName() !== UsersController::class
                || \in_array($m->getName(), ['configureRoles', 'configureOrgs'], true)
            ) {
                continue;
            }
            $body = $this->methodBody($m->getName());
            if (\str_contains($body, '$this->configureRoles(') || \str_contains($body, '$this->configureOrgs(')) {
                $callers[] = $m->getName();
                $this->assertStringContainsString('$this->notifyAccessChanged(', $body, $m->getName());
            }
        }
        \sort($callers);
        $this->assertSame(['setOrganization', 'setRole', 'update'], $callers);
    }

    public function testConfigureMethodsRecordChanges(): void
    {
        $roles = $this->methodBody('configureRoles');
        $this->assertStringContainsString("\$this->accessChanges['roles_added'][]", $roles);
        $this->assertStringContainsString("\$this->accessChanges['roles_removed'][]", $roles);
        $orgs = $this->methodBody('configureOrgs');
        $this->assertStringContainsString("\$this->accessChanges['orgs_added']", $orgs);
        $this->assertStringContainsString("\$this->accessChanges['orgs_removed']", $orgs);
    }

    public function testRolesAreLabelledWithDescription(): void
    {
        /* Энгийн хэрэглэгч "system_editor"-оос илүү тайлбараар ойлгоно */
        $body = $this->methodBody('notifyAccessChanged');
        $this->assertStringContainsString('SELECT id, alias, name, description FROM', $body);
        $this->assertStringContainsString('" ($description)"', $body);
        $this->assertStringContainsString("implode('; ', \$names)", $this->methodBody('mailAccessChanged'));
    }

    public function testDiscordListenerRoutesPrivilegedRoleChange(): void
    {
        $notifier = $this->getMockBuilder(DiscordNotifier::class)
            ->setConstructorArgs(['Admin User', 'example.com'])
            ->onlyMethods(['userAccessChanged'])
            ->getMock();
        $notifier->expects($this->once())
            ->method('userAccessChanged')
            ->with('bat', ['system_admin'], [], 'Admin User');
        (new DiscordListener($notifier))->onUserEvent(new UserEvent(
            'access_changed', 'bat', 'bat@example.com', ['roles_added' => ['system_admin'], 'roles_removed' => []]
        ));
    }

    public function testTemplateListsOnlyChangedParts(): void
    {
        $captured = [];
        $model = $this->getMockBuilder(ReferenceModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['insert'])
            ->getMock();
        $model->method('insert')->willReturnCallback(
            function (array $record, array $content) use (&$captured) {
                if ($record['keyword'] === 'access-changed') {
                    $captured = $content;
                }
                return [];
            }
        );
        ReferenceInitial::reference_templates($model);
        $this->assertNotEmpty($captured, 'access-changed template must be seeded');

        $body = new MemoryTemplate();
        $body->source($captured['en']['content']);
        foreach (['username' => 'bat', 'email' => 'bat@example.com', 'date' => '2026-11-23 18:00',
            'login' => 'https://example.com/dashboard/login', 'roles_added' => 'system_editor',
            'roles_removed' => '', 'orgs_added' => '', 'orgs_removed' => 'Tenant A'] as $k => $v) {
            $body->set($k, $v);
        }
        $html = $body->output();
        $this->assertStringContainsString('Roles added: system_editor', $html);
        $this->assertStringContainsString('Organizations removed: Tenant A', $html);
        $this->assertStringNotContainsString('Roles removed', $html);
        $this->assertStringNotContainsString('Organizations added', $html);
    }
}
