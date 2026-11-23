<?php

namespace Tests\Unit\Controller;

use Tests\Support\RaptorTestCase;

use Dashboard\User\UsersController;

/**
 * Өөрийн нууц үгийг солихдоо одоогийн нууц үгийг заавал шалгана
 * (UsersController::assertCurrentPassword()); system_coder бусдын нууц үгийг
 * солиход шаардахгүй. UsersController::update() нууц үг хүлээж авахгүй -
 * эс бөгөөс энэ шалгалтыг тойрох зам болно.
 */
class UserChangeOwnPasswordTest extends RaptorTestCase
{
    private function assertCurrent(int $actorId, int $targetId, array $body): void
    {
        $controller = new UsersController($this->createMockRequest([
            'pdo' => $this->createMock(\PDO::class),
            'user' => $this->createUser([], ['id' => $actorId])
        ]));
        $method = new \ReflectionMethod(UsersController::class, 'assertCurrentPassword');
        $method->invoke($controller, $targetId, ['password' => \password_hash('old-secret', \PASSWORD_BCRYPT)], $body);
    }

    public function testOwnPasswordWithCorrectCurrentPasswordPasses(): void
    {
        $this->assertCurrent(7, 7, ['password_current' => 'old-secret']);
        $this->addToAssertionCount(1);
    }

    public function testOwnPasswordWithWrongCurrentPasswordIsRejected(): void
    {
        $this->expectExceptionCode(403);
        $this->assertCurrent(7, 7, ['password_current' => 'wrong']);
    }

    public function testOwnPasswordWithoutCurrentPasswordIsRejected(): void
    {
        $this->expectExceptionCode(403);
        $this->assertCurrent(7, 7, []);
    }

    public function testOtherUsersPasswordDoesNotNeedCurrentPassword(): void
    {
        /* Эрхийн шалгалт (system_coder) setPassword()-ийн эхэнд хийгдэнэ */
        $this->assertCurrent(1, 7, []);
        $this->addToAssertionCount(1);
    }

    public static function passwordChangingMethods(): array
    {
        return [
            'own / system_coder change' => [UsersController::class],
            'forgot-password reset' => [\Dashboard\Authentication\LoginController::class]
        ];
    }

    /**
     * Нууц үг өөрчилдөг зам бүр "password-changed" мэдэгдэл илгээнэ (OWASP ASVS V2.2.3).
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('passwordChangingMethods')]
    public function testEveryPasswordChangeNotifiesTheOwner(string $class): void
    {
        $this->assertContains(\Dashboard\User\PasswordChangedNotification::class, \class_uses($class));
        $ref = new \ReflectionMethod($class, 'setPassword');
        $lines = \file($ref->getFileName());
        $body = \implode('', \array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
        $this->assertStringContainsString('$this->notifyPasswordChanged(', $body);
    }

    public function testProfileUpdateDoesNotAcceptPassword(): void
    {
        $source = \file_get_contents(\dirname(__DIR__, 3) . '/application/dashboard/user/UsersController.php');
        $method = new \ReflectionMethod(UsersController::class, 'update');
        $lines = \array_slice(\explode("\n", $source), $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);
        $body = \implode("\n", $lines);
        $this->assertDoesNotMatchRegularExpression('/\$body\[[\'"]password[\'"]\]/', $body);
        $this->assertStringNotContainsString("\$payload['password']", $body);
    }
}
