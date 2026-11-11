<?php

declare(strict_types=1);

namespace Kilke\FilamentAdmin2fa\Commands;

use Illuminate\Console\Command;
use Kilke\FilamentAdmin2fa\Support\TwoFactorUsers;

/**
 * Снимает 2FA с пользователя панели — путь восстановления, когда
 * телефон потерян и коды восстановления не сохранились.
 *
 *     php artisan admin-2fa:disable admin@example.com
 *
 * Команда намеренно консольная: веб-страницы «сбросить себе 2FA» быть
 * не должно, иначе второй фактор обходится через угнанную сессию.
 */
class DisableTwoFactorCommand extends Command
{
    protected $signature = 'admin-2fa:disable
                            {email? : Email пользователя панели}
                            {--force : Не спрашивать подтверждение}';

    protected $description = 'Снять двухфакторную аутентификацию с пользователя панели';

    /**
     * Находит пользователя по email и очищает его 2FA-данные.
     */
    public function handle(TwoFactorUsers $users): int
    {
        $email = $this->argument('email') ?? $this->ask('Email пользователя');

        if (blank($email)) {
            $this->components->error('Email не указан.');

            return self::FAILURE;
        }

        try {
            $user = $users->findByEmail((string) $email);
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($user === null) {
            $this->components->error("Пользователь [{$email}] не найден.");

            return self::FAILURE;
        }

        if (! $users->isEnabled($user)) {
            $this->components->info("У [{$email}] двухфакторная аутентификация уже отключена.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Снять 2FA с [{$email}]? Он сможет войти по одному паролю.",
        )) {
            $this->components->info('Отменено.');

            return self::SUCCESS;
        }

        $users->disable($user);

        $this->components->info("2FA снята с [{$email}].");

        if ((bool) config('admin-2fa.required', false)) {
            $this->components->warn(
                'admin-2fa.required = true: при следующем входе он будет отправлен на настройку заново.',
            );
        }

        return self::SUCCESS;
    }
}
