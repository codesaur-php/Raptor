<?php

namespace Tests\Unit\Content;

use Tests\Support\RaptorTestCase;

use Dashboard\File\ProtectedFilesController;

/**
 * ProtectedFilesController::authorizeRead() - эрхийн hook тест.
 *
 * Default зан төлөв нь permissive: нэвтэрсэн хэрэглэгч бүр (coder ч,
 * энгийн ч) protected файл уншиж болно. Гол security-механизм нь hook-ийг
 * subclass дотор override хийж хатууруулах боломж тул түүнийг мөн шалгана.
 * Нэмж, read() нь файл дамжуулахаас өмнө hook-ийг дуудаж байгааг source-code
 * түвшинд баталгаажуулна (override цэгийг санамсаргүй хасахаас сэргийлж).
 */
class ProtectedFilesAuthTest extends RaptorTestCase
{
    private function makeController(\Dashboard\Authentication\User $user): ProtectedFilesController
    {
        $request = $this->createMockRequest([
            'pdo'  => $this->createMock(\PDO::class),
            'user' => $user,
        ]);

        return new ProtectedFilesController($request);
    }

    private function callAuthorize(ProtectedFilesController $c, string $path): bool
    {
        $method = new \ReflectionMethod($c, 'authorizeRead');
        $method->setAccessible(true);

        return $method->invoke($c, $path);
    }

    public function testCoderIsAllowed(): void
    {
        $controller = $this->makeController($this->createCoder());

        $this->assertTrue(
            $this->callAuthorize($controller, 'contracts/5/secret.pdf')
        );
    }

    public function testAuthenticatedUserAllowedByDefault(): void
    {
        // Default permissive: coder биш энгийн хэрэглэгч ч уншиж болно
        $controller = $this->makeController($this->createAdmin());

        $this->assertTrue(
            $this->callAuthorize($controller, 'contracts/5/secret.pdf')
        );
    }

    public function testSubclassCanRestrictAccess(): void
    {
        // Гол security-механизм: subclass authorizeRead()-ийг override
        // хийж хандалтыг хатууруулж чадна (энд бүгдийг татгалзсан жишээ).
        $request = $this->createMockRequest([
            'pdo'  => $this->createMock(\PDO::class),
            'user' => $this->createAdmin(),
        ]);
        $controller = new class($request) extends ProtectedFilesController {
            protected function authorizeRead(string $relativePath): bool
            {
                return false;
            }
        };

        $this->assertFalse($this->callAuthorize($controller, 'contracts/5/secret.pdf'));
    }

    public function testReadCallsAuthorizeBeforeServing(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/dashboard/file/ProtectedFilesController.php'
        );

        $this->assertStringContainsString(
            'authorizeRead(',
            $source,
            'read() must call the authorizeRead() hook'
        );

        // Hook-ийн дуудалт readfile()-аас өмнө байх ёстой (gate идэвхтэй)
        $authPos = \strpos($source, '$this->authorizeRead(');
        $readPos = \strpos($source, '\readfile(');
        $this->assertNotFalse($authPos);
        $this->assertNotFalse($readPos);
        $this->assertLessThan(
            $readPos,
            $authPos,
            'authorizeRead() must be checked before readfile()'
        );
    }
}
