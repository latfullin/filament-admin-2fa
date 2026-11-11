<?php

declare(strict_types=1);

namespace Kilke\FilamentAdmin2fa\Testing;

use Illuminate\Database\Eloquent\Model;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * Хелперы для тестов проекта, где подключён пакет.
 *
 * Нужны потому, что проверить вход с 2FA иначе нельзя: код зависит от
 * секрета и текущего времени, а секрет лежит зашифрованным.
 */
trait InteractsWithTwoFactor
{
    /**
     * Включает 2FA пользователю и возвращает сырой секрет.
     *
     * @param  ?string  $secret  свой секрет; null — сгенерировать
     */
    public function enableTwoFactor(Model $user, ?string $secret = null): string
    {
        $secret ??= $this->google2FA()->generateSecretKey(16);

        /** @var Model&object{saveAppAuthenticationSecret: callable} $user */
        $user->saveAppAuthenticationSecret($secret);

        return $secret;
    }

    /**
     * Снимает 2FA с пользователя.
     */
    public function disableTwoFactor(Model $user): void
    {
        /** @var Model&object{disableTwoFactorAuthentication: callable} $user */
        $user->disableTwoFactorAuthentication();
    }

    /**
     * Действующий сейчас TOTP-код пользователя — его можно подставить
     * в форму challenge.
     *
     * @param  ?string  $secret  известный сырой секрет; null — прочитать из модели
     */
    public function currentTwoFactorCode(Model $user, ?string $secret = null): ?string
    {
        /** @var Model&object{getAppAuthenticationSecret: callable} $user */
        $secret ??= $user->getAppAuthenticationSecret();

        if (blank($secret)) {
            return null;
        }

        return $this->google2FA()->getCurrentOtp($secret);
    }

    /**
     * Общий экземпляр Google2FA.
     */
    protected function google2FA(): Google2FA
    {
        return app(Google2FA::class);
    }
}
