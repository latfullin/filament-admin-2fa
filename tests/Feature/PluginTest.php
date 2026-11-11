<?php

declare(strict_types=1);

namespace Kilke\FilamentAdmin2fa\Tests\Feature;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Panel;
use Kilke\FilamentAdmin2fa\Tests\TestCase;
use Kilke\FilamentAdmin2fa\TwoFactorPlugin;
use PHPUnit\Framework\Attributes\Test;

class PluginTest extends TestCase
{
    #[Test]
    public function обязательность_берётся_из_конфига(): void
    {
        config()->set('admin-2fa.required', true);

        $this->assertTrue(TwoFactorPlugin::make()->isRequired());
    }

    #[Test]
    public function явная_настройка_перебивает_конфиг(): void
    {
        config()->set('admin-2fa.required', false);

        $this->assertTrue(TwoFactorPlugin::make()->required()->isRequired());
    }

    #[Test]
    public function плагин_регистрирует_провайдер_в_панели(): void
    {
        $panel = Panel::make()->id('test-panel');

        TwoFactorPlugin::make()->register($panel);

        $providers = $panel->getMultiFactorAuthenticationProviders();

        $this->assertArrayHasKey('app', $providers);
        $this->assertInstanceOf(
            AppAuthentication::class,
            $providers['app'],
        );
    }

    #[Test]
    public function обязательность_передаётся_в_панель(): void
    {
        $panel = Panel::make()->id('required-panel');

        TwoFactorPlugin::make()->required()->register($panel);

        $this->assertTrue($panel->isMultiFactorAuthenticationRequired());
    }

    #[Test]
    public function идентификатор_плагина_стабилен(): void
    {
        $this->assertSame('admin-2fa', TwoFactorPlugin::make()->getId());
    }
}
