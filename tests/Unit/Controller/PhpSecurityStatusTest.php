<?php

namespace Tests\Unit\Controller;

use Tests\Support\RaptorTestCase;

use Dashboard\Home\PhpSecurityStatus;

/**
 * PhpSecurityStatus - PHP хувилбарын засвартай эсэхийг салбар бүрээр зөв ялгаж,
 * түр хамгаалалтын ini тохиргоог бичлэгээс үл хамааран зөв харьцуулна.
 */
class PhpSecurityStatusTest extends RaptorTestCase
{
    public function testVulnerableVersions(): void
    {
        foreach (['8.2.12', '8.2.34', '8.3.35', '8.4.26', '8.5.11', '8.1.33', '7.4.33'] as $v) {
            $this->assertFalse(PhpSecurityStatus::check($v)['patched'], $v);
        }
        $this->assertSame('8.2.35', PhpSecurityStatus::check('8.2.12')['required']);
        $this->assertSame('8.4.27', PhpSecurityStatus::check('8.4.1')['required']);
        $this->assertSame('8.2.35', PhpSecurityStatus::check('8.1.33')['required']);
    }

    public function testPatchedVersions(): void
    {
        foreach (['8.2.35', '8.2.40', '8.3.36', '8.4.27', '8.5.12', '8.6.0', '8.3.36-cl1'] as $v) {
            $status = PhpSecurityStatus::check($v);
            $this->assertTrue($status['patched'], $v);
        }
        $this->assertNull(PhpSecurityStatus::check('8.6.0')['required']);
    }

    public function testDistributionBackports(): void
    {
        /* Ubuntu-ийн засвартай багц upstream хувилбараа хадгална (8.3.6 < 8.3.36) */
        foreach (['8.3.6-0ubuntu0.24.04.13', '8.3.6-0ubuntu0.24.04.14', '8.1.2-1ubuntu2.28', '8.1.2-1ubuntu2.28+esm1', '8.5.9-0ubuntu2.1'] as $v) {
            $status = PhpSecurityStatus::check($v);
            $this->assertTrue($status['patched'], $v);
            $this->assertTrue($status['backport'], $v);
            $this->assertSame($v, $status['package']);
        }
        foreach (['8.3.6-0ubuntu0.24.04.12', '8.3.6-0ubuntu0.24.04', '8.5.4-0ubuntu1', '8.5.4-0ubuntu1.4'] as $v) {
            $this->assertFalse(PhpSecurityStatus::check($v)['patched'], $v);
        }
        /* Шаардлагатай хувилбар нь upstream биш, дистрибьюторын засвартай багц */
        $this->assertSame('8.3.6-0ubuntu0.24.04.13', PhpSecurityStatus::check('8.3.6-0ubuntu0.24.04.5')['required']);

        /* PHP_VERSION-д залгаасгүй build - багцын хувилбарыг тусад нь өгнө */
        $this->assertTrue(PhpSecurityStatus::check('8.3.6', '8.3.6-0ubuntu0.24.04.13')['patched']);
        $this->assertFalse(PhpSecurityStatus::check('8.3.6', '')['patched']);
        /* Ажиллаж буй хувилбартай таарахгүй багц (өөр PHP) тооцогдохгүй */
        $this->assertFalse(PhpSecurityStatus::check('8.3.5', '8.3.6-0ubuntu0.24.04.13')['patched']);
        /* Суурь яг таарах ёстой */
        $this->assertNull(PhpSecurityStatus::backportFixed('8.1.2-1ubuntu20.1'));
        /* Debian upstream засвартай хувилбар руу шилждэг - PATCHED-ээр танигдана */
        $status = PhpSecurityStatus::check('8.2.35-1~deb12u1');
        $this->assertTrue($status['patched']);
        $this->assertFalse($status['backport']);
    }

    public function testRuntimeReportsSapiAndMitigationSettings(): void
    {
        $status = PhpSecurityStatus::check();
        $this->assertSame(\PHP_SAPI, $status['sapi']);
        $this->assertSame(\array_keys(PhpSecurityStatus::MITIGATION), \array_keys($status['ini']));
        $this->assertSame(
            !\in_array(false, \array_column($status['ini'], 'ok'), true),
            $status['mitigated']
        );
    }

    public function testIniMatchesBooleanSpellings(): void
    {
        foreach (['', '0', 'Off', 'off', 'false', 'No'] as $v) {
            $this->assertTrue(PhpSecurityStatus::iniMatches($v, 'Off'), \var_export($v, true));
        }
        foreach (['1', 'On', 'on', 'true'] as $v) {
            $this->assertFalse(PhpSecurityStatus::iniMatches($v, 'Off'), $v);
        }
        $this->assertTrue(PhpSecurityStatus::iniMatches('-1', '-1'));
        $this->assertFalse(PhpSecurityStatus::iniMatches('128M', '-1'));
        $this->assertFalse(PhpSecurityStatus::iniMatches('', '-1'));
    }
}
