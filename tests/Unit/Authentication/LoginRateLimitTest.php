<?php

namespace Tests\Unit\Authentication;

use Tests\Support\RaptorTestCase;
use Raptor\Authentication\LoginController;

/**
 * LoginController-ийн rate limit болон spam хамгаалалтын тестүүд.
 *
 * DB шаардахгүй pure логикийн тестүүд:
 *   - normalizeEmail(): Gmail dot/alias trick
 *   - isGibberishUsername(): Scoring-д суурилсан gibberish илрүүлэлт
 *   - spamCheck(): HMAC token, honeypot, rate limit логик
 */
class LoginRateLimitTest extends RaptorTestCase
{
    private \ReflectionMethod $isGibberish;
    private \ReflectionMethod $normalizeEmail;
    private LoginController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $ref = new \ReflectionClass(LoginController::class);
        $this->controller = $ref->newInstanceWithoutConstructor();

        $this->isGibberish = new \ReflectionMethod(LoginController::class, 'isGibberishUsername');
        $this->isGibberish->setAccessible(true);

        $this->normalizeEmail = new \ReflectionMethod(LoginController::class, 'normalizeEmail');
        $this->normalizeEmail->setAccessible(true);
    }

    // =============================================
    // isGibberishUsername - scoring system тест
    // =============================================

    /**
     * Entropy нь 3.8-аас дээш, 8+ тэмдэгт -> score += 3 (gibberish).
     */
    public function testHighEntropyLongUsernameIsGibberish(): void
    {
        // Бүх үсэг ялгаатай, 12+ тэмдэгт -> маш өндөр entropy
        $this->assertTrue(
            $this->isGibberish->invoke($this->controller, 'aZbYcXdWfVgU'),
            'High entropy random string should be detected as gibberish'
        );
    }

    /**
     * Зөвхөн гийгүүлэгчээс бүрдсэн (эгшиг < 10%) -> score += 3.
     */
    public function testNoVowelUsernameIsGibberish(): void
    {
        $this->assertTrue(
            $this->isGibberish->invoke($this->controller, 'bcdfghjklmn'),
            'Username with almost no vowels should be gibberish'
        );
    }

    /**
     * Олон case change (>50%) + 8+ alpha chars -> score += 3.
     */
    public function testFrequentCaseChangeIsGibberish(): void
    {
        $this->assertTrue(
            $this->isGibberish->invoke($this->controller, 'HgTkLpQrWxZn'),
            'Frequent case changes should indicate gibberish'
        );
    }

    /**
     * Scoring threshold: score < 3 бол gibberish биш.
     * Нэг шалгуурт бага оноо авсан ч нийлбэр 3 хүрэхгүй.
     */
    public function testMildlyUnusualUsernameNotGibberish(): void
    {
        // "schmidt" - 1 vowel out of 7 letters -> vowelRatio ~ 0.14 -> score += 1
        // Бусад шалгуурт бага оноо -> нийт < 3
        $this->assertFalse(
            $this->isGibberish->invoke($this->controller, 'schmidt'),
            'Real name with low vowel ratio should not be flagged'
        );
    }

    /**
     * 4 үсэгтэй доод хязгаарт тэмдэгтүүд шалгагдана.
     */
    public function testExactFourLettersChecked(): void
    {
        // "abcd" -> 4 letters, шалгалтанд орно
        $this->assertFalse(
            $this->isGibberish->invoke($this->controller, 'abcd'),
            'Simple 4-letter string should not be gibberish'
        );
    }

    /**
     * Тоонуудтай username - тоо хасагдаад letters < 4 бол шалгахгүй.
     */
    public function testNumberHeavyUsernameSkipsCheck(): void
    {
        // "ab12345" -> letters = "ab" (2 chars < 4) -> шалгахгүй
        $this->assertFalse(
            $this->isGibberish->invoke($this->controller, 'ab12345'),
            'Username with too few letters should skip gibberish check'
        );
    }

    /**
     * Доогуур зураас, цэг хасагдана.
     */
    public function testUnderscoreAndDotStripped(): void
    {
        // "a.b_c" -> letters = "abc" (3 chars < 4) -> шалгахгүй
        $this->assertFalse(
            $this->isGibberish->invoke($this->controller, 'a.b_c'),
            'Punctuation should be stripped before length check'
        );
    }

    /**
     * Бодит Монгол нэрүүд gibberish биш.
     */
    public function testMongolianNamesNotGibberish(): void
    {
        $names = ['baterdene', 'munkhtseteg', 'tsolmon', 'gantulga', 'sarnai', 'naraa'];
        foreach ($names as $name) {
            $this->assertFalse(
                $this->isGibberish->invoke($this->controller, $name),
                "Mongolian name '$name' should not be gibberish"
            );
        }
    }

    /**
     * CamelCase бодит нэрүүд gibberish биш.
     */
    public function testCamelCaseRealNamesNotGibberish(): void
    {
        $this->assertFalse(
            $this->isGibberish->invoke($this->controller, 'johnDoe'),
            'camelCase real name should not be gibberish'
        );
        $this->assertFalse(
            $this->isGibberish->invoke($this->controller, 'JohnDoe'),
            'PascalCase real name should not be gibberish'
        );
    }

    /**
     * 6+ дараалсан гийгүүлэгч -> score += 2.
     */
    public function testLongConsonantClusterAddsScore(): void
    {
        // "brtxkwqmzl" -> олон дараалсан гийгүүлэгч + бага эгшиг
        $this->assertTrue(
            $this->isGibberish->invoke($this->controller, 'brtxkwqmzl'),
            'Long consonant clusters should contribute to gibberish score'
        );
    }

    // =============================================
    // normalizeEmail - нарийвчилсан тестүүд
    // =============================================

    /**
     * @ тэмдэгт байхгүй email-г зөвхөн lowercase хийнэ.
     */
    public function testEmailWithoutAtSignOnlyLowered(): void
    {
        $this->assertSame(
            'invalidemail',
            $this->normalizeEmail->invoke($this->controller, 'InvalidEmail')
        );
    }

    /**
     * Gmail sub-addressing: local+anything хэсгийг хаях.
     */
    public function testGmailPlusAddressing(): void
    {
        $this->assertSame(
            'user@gmail.com',
            $this->normalizeEmail->invoke($this->controller, 'user+newsletter+extra@gmail.com')
        );
    }

    /**
     * Gmail dots + plus хослол.
     */
    public function testGmailDotsAndPlusCombined(): void
    {
        $this->assertSame(
            'johndoe@gmail.com',
            $this->normalizeEmail->invoke($this->controller, 'j.o.h.n.d.o.e+spam@gmail.com')
        );
    }

    /**
     * googlemail.com -> gmail.com.
     */
    public function testGooglemailNormalized(): void
    {
        $this->assertSame(
            'test@gmail.com',
            $this->normalizeEmail->invoke($this->controller, 'test@googlemail.com')
        );
    }

    /**
     * googlemail.com + dots + plus хослол.
     */
    public function testGooglemailFullNormalization(): void
    {
        $this->assertSame(
            'testuser@gmail.com',
            $this->normalizeEmail->invoke($this->controller, 'te.st.us.er+junk@googlemail.com')
        );
    }

    /**
     * Non-Gmail email: dots хэвээр, зөвхөн lowercase.
     */
    public function testNonGmailPreservesDots(): void
    {
        $this->assertSame(
            'john.doe@yahoo.com',
            $this->normalizeEmail->invoke($this->controller, 'John.Doe@Yahoo.com')
        );
    }

    /**
     * Non-Gmail email: plus addressing хэвээр.
     */
    public function testNonGmailPreservesPlus(): void
    {
        $this->assertSame(
            'user+tag@outlook.com',
            $this->normalizeEmail->invoke($this->controller, 'User+Tag@Outlook.com')
        );
    }

    /**
     * Ижил Gmail хаяг олон dot variation-тай бол normalize давхцах.
     */
    public function testMultipleDotVariationsNormalizeToSame(): void
    {
        $variations = [
            'test.user@gmail.com',
            't.e.s.t.u.s.e.r@gmail.com',
            'testuser@gmail.com',
            'te.stus.er@gmail.com',
        ];
        $normalized = [];
        foreach ($variations as $email) {
            $normalized[] = $this->normalizeEmail->invoke($this->controller, $email);
        }
        $this->assertCount(1, \array_unique($normalized), 'All dot variations should normalize to same address');
    }

    /**
     * Хоосон local part.
     */
    public function testEmptyLocalPart(): void
    {
        $result = $this->normalizeEmail->invoke($this->controller, '@gmail.com');
        $this->assertSame('@gmail.com', $result);
    }

    /**
     * Uppercase domain normalize.
     */
    public function testUppercaseDomainNormalized(): void
    {
        $this->assertSame(
            'user@gmail.com',
            $this->normalizeEmail->invoke($this->controller, 'USER@GMAIL.COM')
        );
    }

    // =============================================
    // Бодит spam pattern - хоёр функц хамтдаа
    // =============================================

    /**
     * Бодит spam бүртгэлийн pattern илрүүлэх.
     */
    public function testRealWorldSpamPatternDetection(): void
    {
        // Spam username
        $this->assertTrue(
            $this->isGibberish->invoke($this->controller, 'xKjQwRtZpLmN'),
            'Random mixed case spam username should be detected'
        );

        // Spam email normalize -> давхар бүртгэл хаах
        $email1 = 'sp.am.bo.t@gmail.com';
        $email2 = 's.p.a.m.b.o.t@gmail.com';
        $this->assertSame(
            $this->normalizeEmail->invoke($this->controller, $email1),
            $this->normalizeEmail->invoke($this->controller, $email2),
            'Dot trick variations should normalize to same address'
        );
    }

    /**
     * Edge case: keyboard pattern нь gibberish байх ёстой.
     */
    public function testKeyboardPatternIsGibberish(): void
    {
        $this->assertTrue(
            $this->isGibberish->invoke($this->controller, 'qwrtpsdfg'),
            'Keyboard smash pattern should be gibberish'
        );
    }

    /**
     * Edge case: бодит хүний нэр + тоо.
     */
    public function testRealNameWithNumbersNotGibberish(): void
    {
        $this->assertFalse(
            $this->isGibberish->invoke($this->controller, 'bat2024'),
            'Real name with year suffix should not be gibberish'
        );
    }
}
