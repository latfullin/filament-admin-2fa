<?php

declare(strict_types=1);

namespace Kilke\FilamentAdmin2fa\Tests\Feature;

use Kilke\FilamentAdmin2fa\Testing\InteractsWithTwoFactor;
use Kilke\FilamentAdmin2fa\Tests\Fixtures\TestAdmin;
use Kilke\FilamentAdmin2fa\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CommandsTest extends TestCase
{
    use InteractsWithTwoFactor;

    private function admin(string $email = 'admin@example.com'): TestAdmin
    {
        return TestAdmin::create([
            'name' => 'Админ',
            'email' => $email,
            'password' => bcrypt('secret-password'),
        ]);
    }

    #[Test]
    public function disable_снимает_2fa(): void
    {
        $admin = $this->admin();
        $this->enableTwoFactor($admin);

        $this->artisan('admin-2fa:disable', ['email' => 'admin@example.com', '--force' => true])
            ->assertSuccessful();

        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());
    }

    #[Test]
    public function disable_сообщает_о_ненайденном_пользователе(): void
    {
        $this->artisan('admin-2fa:disable', ['email' => 'нет@example.com', '--force' => true])
            ->assertFailed();
    }

    #[Test]
    public function disable_не_падает_если_2fa_и_так_выключена(): void
    {
        $this->admin();

        $this->artisan('admin-2fa:disable', ['email' => 'admin@example.com', '--force' => true])
            ->assertSuccessful();
    }

    #[Test]
    public function status_показывает_обоих(): void
    {
        $with = $this->admin('with@example.com');
        $this->enableTwoFactor($with);
        $this->admin('without@example.com');

        $this->artisan('admin-2fa:status')
            ->expectsOutputToContain('with@example.com')
            ->expectsOutputToContain('without@example.com')
            ->assertSuccessful();
    }

    #[Test]
    public function status_pending_показывает_только_без_2fa(): void
    {
        $with = $this->admin('with@example.com');
        $this->enableTwoFactor($with);
        $this->admin('without@example.com');

        $this->artisan('admin-2fa:status', ['--pending' => true])
            ->expectsOutputToContain('without@example.com')
            ->doesntExpectOutputToContain('with@example.com')
            ->assertSuccessful();
    }

    #[Test]
    public function команда_объясняет_неверную_модель_в_конфиге(): void
    {
        config()->set('admin-2fa.model', 'App\\Models\\НетТакой');

        $this->artisan('admin-2fa:status')->assertFailed();
    }
}
