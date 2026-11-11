<?php

declare(strict_types=1);

namespace Kilke\FilamentAdmin2fa\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kilke\FilamentAdmin2fa\Tests\Fixtures\TestAdmin;
use Kilke\FilamentAdmin2fa\TwoFactorServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Таблица пользователей панели и колонки 2FA из миграции пакета.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_admins', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->artisan('migrate')->run();
    }

    /**
     * @return array<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FilamentServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            NotificationsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            TwoFactorServiceProvider::class,
        ];
    }

    /**
     * SQLite в памяти, APP_KEY для шифрования секретов, модель-фикстура.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('admin-2fa.model', TestAdmin::class);
        $app['config']->set('admin-2fa.table', 'test_admins');
    }
}
