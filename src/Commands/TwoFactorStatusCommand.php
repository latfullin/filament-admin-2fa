<?php

declare(strict_types=1);

namespace Kilke\FilamentAdmin2fa\Commands;

use Illuminate\Console\Command;
use Kilke\FilamentAdmin2fa\Support\TwoFactorUsers;

/**
 * Показывает, у кого из пользователей панели включена 2FA.
 *
 *     php artisan admin-2fa:status
 *
 * Нужна перед переключением `admin-2fa.required` в true: сразу видно,
 * кто ещё не привязал приложение.
 */
class TwoFactorStatusCommand extends Command
{
    protected $signature = 'admin-2fa:status {--pending : Показать только тех, у кого 2FA не настроена}';

    protected $description = 'Показать состояние двухфакторной аутентификации у пользователей панели';

    /**
     * Выводит таблицу «email → 2FA включена».
     */
    public function handle(TwoFactorUsers $users): int
    {
        try {
            $all = $users->all();
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        $pendingOnly = (bool) $this->option('pending');

        foreach ($all as $user) {
            $enabled = $users->isEnabled($user);

            if ($pendingOnly && $enabled) {
                continue;
            }

            $rows[] = [
                (string) ($user->getAttribute('email') ?? $user->getKey()),
                $enabled ? 'да' : 'нет',
            ];
        }

        if ($rows === []) {
            $this->components->info($pendingOnly
                ? 'У всех пользователей панели 2FA настроена.'
                : 'Пользователей панели не найдено.');

            return self::SUCCESS;
        }

        $this->table(['Пользователь', '2FA'], $rows);

        $pending = count(array_filter($rows, fn (array $row): bool => $row[1] === 'нет'));

        if ($pending > 0 && ! (bool) config('admin-2fa.required', false)) {
            $this->components->warn(
                "Без 2FA: {$pending}. Включать admin-2fa.required стоит после того, как они привяжут приложение.",
            );
        }

        return self::SUCCESS;
    }
}
