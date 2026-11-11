<?php

declare(strict_types=1);

namespace Kilke\FilamentAdmin2fa;

use Illuminate\Support\ServiceProvider;
use Kilke\FilamentAdmin2fa\Commands\DisableTwoFactorCommand;
use Kilke\FilamentAdmin2fa\Commands\TwoFactorStatusCommand;

/**
 * Регистрация пакета: конфиг, миграция, artisan-команды.
 *
 * Миграция публикуется, а не загружается автоматически: имя таблицы
 * берётся из конфига, и проект должен видеть файл у себя, чтобы
 * порядок миграций был предсказуемым (колонки добавляются в уже
 * созданную таблицу пользователей панели).
 */
class TwoFactorServiceProvider extends ServiceProvider
{
    /**
     * Сливает конфиг пакета с конфигом приложения.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-2fa.php', 'admin-2fa');
    }

    /**
     * Публикуемые ресурсы и команды.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/admin-2fa.php' => config_path('admin-2fa.php'),
        ], 'admin-2fa-config');

        $this->publishes([
            __DIR__.'/../database/migrations/0001_01_01_000000_add_two_factor_columns.php' => database_path('migrations/'.date('Y_m_d_His').'_add_two_factor_columns.php'),
        ], 'admin-2fa-migrations');

        $this->commands([
            DisableTwoFactorCommand::class,
            TwoFactorStatusCommand::class,
        ]);
    }
}
