<?php

namespace Tests\Unit\Authentication;

use Tests\Support\RaptorTestCase;
use Raptor\Authentication\LoginController;

/**
 * LoginController-ийн spam хамгаалалтын private методуудыг тестлэх.
 *
 * - isGibberishUsername(): Санамсаргүй/утгагүй username илрүүлэх
 * - normalizeEmail(): Gmail dot/alias trick арилгах
 */
class LoginSpamProtectionTest extends RaptorTestCase
{
    private \ReflectionMethod $isGibberish;
    private \ReflectionMethod $normalizeEmail;
    private LoginController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        // Constructor-г алгасах (PDO шаардахгүй, зөвхөн utility методуудыг тестлэх)
        $ref = new \ReflectionClass(LoginController::class);
        $this->controller = $ref->newInstanceWithoutConstructor();

        $this->isGibberish = new \ReflectionMethod(LoginController::class, 'isGibberishUsername');
        $this->isGibberish->setAccessible(true);

        $this->normalizeEmail = new \ReflectionMethod(LoginController::class, 'normalizeEmail');
        $this->normalizeEmail->setAccessible(true);
    }

    // =============================================
    // isGibberishUsername() - Gibberish илрүүлэх
    // =============================================

    /**
     * @dataProvider validUsernameProvider
     */
    public function testValidUsernamesAreNotGibberish(string $username): void
    {
        $this->assertFalse(
            $this->isGibberish->invoke($this->controller, $username),
            "Valid username '$username' should NOT be detected as gibberish"
        );
    }

    /**
     * @dataProvider gibberishUsernameProvider
     */
    public function testGibberishUsernamesAreDetected(string $username): void
    {
        $this->assertTrue(
            $this->isGibberish->invoke($this->controller, $username),
            "Gibberish username '$username' SHOULD be detected as gibberish"
        );
    }

    public static function validUsernameProvider(): array
    {
        return [
            'simple lowercase'      => ['john'],
            'with underscore'       => ['john_doe'],
            'with dot'              => ['john.doe'],
            'with numbers'          => ['user123'],
            'mongolian name'        => ['baterdene'],
            'mongolian name 2'      => ['naraa'],
            'mongolian name 3'      => ['tsolmon'],
            'mongolian munkh'       => ['munkhtseteg'],
            'mongolian orgil'       => ['munkh_orgil'],
            'mongolian tseren'      => ['tserenbold'],
            'mongolian gantulga'    => ['gantulga'],
            'camelCase'             => ['johnDoe'],
            'PascalCase'            => ['JohnDoe'],
            'short name'            => ['ali'],
            'name with year'        => ['bat2024'],
            'common pattern'        => ['admin01'],
            'mixed name number'     => ['sarnai99'],
            'developer name'        => ['dev.bold'],
            'slavic name'           => ['khrystyna'],
            'german name'           => ['schmidt'],
        ];
    }

    public static function gibberishUsernameProvider(): array
    {
        return [
            'original spam signup'  => ['CdIrBVTolzIvAxjqdF'],
            'random chars'          => ['xKjQwRtZpLmN'],
            'consonant cluster'     => ['brtxkwqmzl'],
            'high entropy random'   => ['aZbYcXdWfVgU'],
            'keyboard smash'        => ['qwrtpsdfg'],
            'random mixed case'     => ['HgTkLpQrWxZn'],
            'no vowels long'        => ['bcdfghjklmn'],
            'bot pattern'           => ['XyZaBcDeFgHi'],
        ];
    }

    // =============================================
    // Тусгай edge case-үүд
    // =============================================

    public function testShortUsernamesSkipGibberishCheck(): void
    {
        // 3 тэмдэгт, letters < 4 тул шалгахгүй
        $this->assertFalse($this->isGibberish->invoke($this->controller, 'abc'));
        $this->assertFalse($this->isGibberish->invoke($this->controller, 'ab1'));
    }

    public function testUsernameWithManyNumbersNotGibberish(): void
    {
        // Тоонууд хасагдаад letters < 4 болно
        $this->assertFalse($this->isGibberish->invoke($this->controller, 'user12345'));
    }

    // =============================================
    // normalizeEmail() - Gmail normalization
    // =============================================

    /**
     * @dataProvider gmailNormalizationProvider
     */
    public function testGmailNormalization(string $input, string $expected): void
    {
        $this->assertEquals(
            $expected,
            $this->normalizeEmail->invoke($this->controller, $input),
            "Email '$input' should normalize to '$expected'"
        );
    }

    public static function gmailNormalizationProvider(): array
    {
        return [
            'dots removed' => [
                'y.i.r.o.h.o.b.o@gmail.com',
                'yirohobo@gmail.com',
            ],
            'original spam email' => [
                'yir.o.h.obo.j.u.k.1.0@gmail.com',
                'yirohobojuk10@gmail.com',
            ],
            'plus addressing removed' => [
                'user+spam@gmail.com',
                'user@gmail.com',
            ],
            'dots and plus combined' => [
                'john.doe+newsletter@gmail.com',
                'johndoe@gmail.com',
            ],
            'googlemail normalized' => [
                'test@googlemail.com',
                'test@gmail.com',
            ],
            'googlemail dots and plus' => [
                'te.st+junk@googlemail.com',
                'test@gmail.com',
            ],
            'uppercase normalized' => [
                'John.Doe@Gmail.Com',
                'johndoe@gmail.com',
            ],
            'already clean gmail' => [
                'cleanuser@gmail.com',
                'cleanuser@gmail.com',
            ],
        ];
    }

    /**
     * @dataProvider nonGmailProvider
     */
    public function testNonGmailEmailsNotModified(string $input, string $expected): void
    {
        $this->assertEquals(
            $expected,
            $this->normalizeEmail->invoke($this->controller, $input),
            "Non-Gmail email '$input' should only be lowercased, not dot-stripped"
        );
    }

    public static function nonGmailProvider(): array
    {
        return [
            'yahoo dots preserved' => [
                'john.doe@yahoo.com',
                'john.doe@yahoo.com',
            ],
            'outlook dots preserved' => [
                'j.doe@outlook.com',
                'j.doe@outlook.com',
            ],
            'custom domain preserved' => [
                'admin@my.company.com',
                'admin@my.company.com',
            ],
            'uppercase non-gmail lowered' => [
                'Admin@Company.com',
                'admin@company.com',
            ],
        ];
    }

    // =============================================
    // Хоёр функц хамтдаа: Бодит spam жишээ
    // =============================================

    public function testRealSpamSignupWouldBeBlocked(): void
    {
        $spamUsername = 'CdIrBVTolzIvAxjqdF';
        $spamEmail = 'yir.o.h.obo.j.u.k.1.0@gmail.com';

        // Username gibberish гэж илрэх ёстой
        $this->assertTrue(
            $this->isGibberish->invoke($this->controller, $spamUsername),
            'The real spam username should be detected as gibberish'
        );

        // Email normalize хийгдэх ёстой
        $normalized = $this->normalizeEmail->invoke($this->controller, $spamEmail);
        $this->assertEquals('yirohobojuk10@gmail.com', $normalized);

        // Хэрэв ижил хүн дахин бүртгүүлбэл email давхцах ёстой
        $secondAttempt = 'y.iro.hobo.juk.10@gmail.com';
        $this->assertEquals(
            $normalized,
            $this->normalizeEmail->invoke($this->controller, $secondAttempt),
            'Different dot variations of same Gmail should normalize to same address'
        );
    }
}
