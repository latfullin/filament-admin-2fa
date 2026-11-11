<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Колонки TOTP-двухфакторной аутентификации для таблицы пользователей панели.
 *
 * Таблица берётся из config('admin-2fa.table') — пакет не знает заранее,
 * это `admins`, `users` или что-то своё.
 *
 * Колонки добавляются по одной с проверкой: на панели, где 2FA уже
 * прикручивали вручную, часть из них может существовать, и слепой
 * `addColumn` уронил бы миграцию на живой базе.
 */
return new class extends Migration
{
    /**
     * Добавляет секрет и коды восстановления.
     */
    public function up(): void
    {
        $table = $this->table();

        if (! Schema::hasTable($table)) {
            throw new RuntimeException(
                "Таблица [{$table}] не найдена. Проверьте config('admin-2fa.table') "
                .'и порядок миграций: колонки 2FA добавляются в уже существующую таблицу.'
            );
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'two_factor_secret')) {
                $blueprint->text('two_factor_secret')->nullable();
            }

            if (! Schema::hasColumn($table, 'two_factor_recovery_codes')) {
                $blueprint->text('two_factor_recovery_codes')->nullable();
            }

            if (! Schema::hasColumn($table, 'two_factor_confirmed_at')) {
                $blueprint->timestamp('two_factor_confirmed_at')->nullable();
            }
        });
    }

    /**
     * Убирает колонки 2FA. Секреты и коды при этом теряются — после
     * откат-накат админам придётся привязывать приложение заново.
     */
    public function down(): void
    {
        $table = $this->table();

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            $columns = array_values(array_filter(
                ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'],
                fn (string $column): bool => Schema::hasColumn($table, $column),
            ));

            if ($columns !== []) {
                $blueprint->dropColumn($columns);
            }
        });
    }

    /**
     * Имя таблицы пользователей панели из конфига пакета.
     */
    private function table(): string
    {
        return (string) config('admin-2fa.table', 'admins');
    }
};
