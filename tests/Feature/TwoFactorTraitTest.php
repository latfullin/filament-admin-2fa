<?php

declare(strict_types=1);

namespace Kilke\FilamentAdmin2fa\Tests\Feature;

use Kilke\FilamentAdmin2fa\Testing\InteractsWithTwoFactor;
use Kilke\FilamentAdmin2fa\Tests\Fixtures\TestAdmin;
use Kilke\FilamentAdmin2fa\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFactorTraitTest extends TestCase
{
    use InteractsWithTwoFactor;

    private function admin(): TestAdmin
    {
        return TestAdmin::create([
            'name' => 'Админ',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret-password'),
        ]);
    }

    #[Test]
    public function миграция_добавила_колонки(): void
    {
        $this->assertTrue(\Schema::hasColumn('test_admins', 'two_factor_secret'));
        $this->assertTrue(\Schema::hasColumn('test_admins', 'two_factor_recovery_codes'));
        $this->assertTrue(\Schema::hasColumn('test_admins', 'two_factor_confirmed_at'));
    }

    #[Test]
    public function по_умолчанию_2fa_выключена(): void
    {
        $admin = $this->admin();

        $this->assertFalse($admin->hasTwoFactorEnabled());
        $this->assertNull($admin->getAppAuthenticationSecret());
    }

    #[Test]
    public function секрет_сохраняется_и_читается(): void
    {
        $admin = $this->admin();

        $secret = $this->enableTwoFactor($admin);

        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());
        $this->assertSame($secret, $admin->fresh()->getAppAuthenticationSecret());
    }

    #[Test]
    public function секрет_хранится_зашифрованным(): void
    {
        $admin = $this->admin();

        $secret = $this->enableTwoFactor($admin);

        $stored = \DB::table('test_admins')->where('id', $admin->id)->value('two_factor_secret');

        $this->assertNotSame($secret, $stored);
        $this->assertStringNotContainsString($secret, (string) $stored);
    }

    #[Test]
    public function нерасшифруемый_секрет_читается_как_выключенная_2fa(): void
    {
        $admin = $this->admin();
        $this->enableTwoFactor($admin);

        \DB::table('test_admins')->where('id', $admin->id)->update([
            'two_factor_secret' => 'это-не-шифртекст',
        ]);

        $this->assertNull($admin->fresh()->getAppAuthenticationSecret());
        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());
    }

    #[Test]
    public function коды_восстановления_сохраняются(): void
    {
        $admin = $this->admin();
        $codes = ['code-one', 'code-two'];

        $admin->saveAppAuthenticationRecoveryCodes($codes);

        $this->assertSame($codes, $admin->fresh()->getAppAuthenticationRecoveryCodes());
    }

    #[Test]
    public function коды_восстановления_хранятся_зашифрованными(): void
    {
        $admin = $this->admin();

        $admin->saveAppAuthenticationRecoveryCodes(['code-one']);

        $stored = \DB::table('test_admins')->where('id', $admin->id)->value('two_factor_recovery_codes');

        $this->assertStringNotContainsString('code-one', (string) $stored);
    }

    #[Test]
    public function отключение_чистит_секрет_и_коды(): void
    {
        $admin = $this->admin();
        $this->enableTwoFactor($admin);
        $admin->saveAppAuthenticationRecoveryCodes(['code-one']);

        $admin->disableTwoFactorAuthentication();

        $fresh = $admin->fresh();
        $this->assertFalse($fresh->hasTwoFactorEnabled());
        $this->assertNull($fresh->getAppAuthenticationSecret());
        $this->assertNull($fresh->getAppAuthenticationRecoveryCodes());
        $this->assertNull($fresh->two_factor_confirmed_at);
    }

    #[Test]
    public function поля_2fa_скрыты_из_json(): void
    {
        $admin = $this->admin();
        $this->enableTwoFactor($admin);

        $array = $admin->fresh()->toArray();

        $this->assertArrayNotHasKey('two_factor_secret', $array);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $array);
        $this->assertArrayNotHasKey('password', $array, 'трейт не должен терять уже скрытые поля');
    }

    #[Test]
    public function текущий_код_проходит_проверку(): void
    {
        $admin = $this->admin();
        $secret = $this->enableTwoFactor($admin);

        $code = $this->currentTwoFactorCode($admin);

        $this->assertNotNull($code);
        $this->assertTrue(app(Google2FA::class)->verifyKey($secret, $code));
    }

    #[Test]
    public function подпись_записи_это_email(): void
    {
        $this->assertSame('admin@example.com', $this->admin()->getAppAuthenticationHolderName());
    }
}
