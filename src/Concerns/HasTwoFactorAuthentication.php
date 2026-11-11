<?php

declare(strict_types=1);

namespace Kilke\FilamentAdmin2fa\Concerns;

use Illuminate\Support\Facades\Crypt;
use SensitiveParameter;

/**
 * Реализация контрактов Filament для TOTP-двухфакторной аутентификации.
 *
 * Подключается к модели пользователя панели вместе с интерфейсами:
 *
 *     use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
 *     use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
 *     use Kilke\FilamentAdmin2fa\Concerns\HasTwoFactorAuthentication;
 *
 *     class Admin extends Authenticatable implements
 *         FilamentUser,
 *         HasAppAuthentication,
 *         HasAppAuthenticationRecovery
 *     {
 *         use HasTwoFactorAuthentication;
 *     }
 *
 * Секрет и коды восстановления шифруются ключом приложения (APP_KEY).
 * Поэтому: потеря APP_KEY = потеря всех 2FA-секретов, админам нужно
 * привязывать приложение заново. Это сознательный выбор — секрет TOTP
 * обратимый по своей природе (им генерируются коды), хешировать его
 * нельзя, так что шифрование — максимум из возможного.
 *
 * Коды восстановления, напротив, хешируются самим Filament перед
 * вызовом saveAppAuthenticationRecoveryCodes(), и здесь шифруется уже
 * массив хешей.
 */
trait HasTwoFactorAuthentication
{
    /**
     * Прячет 2FA-поля из массивов и JSON модели.
     *
     * Вызывается Laravel при инициализации трейтов модели, поэтому
     * $hidden в самой модели перезаписывать не нужно — поля
     * добавляются к уже объявленным.
     */
    public function initializeHasTwoFactorAuthentication(): void
    {
        $this->hidden = array_values(array_unique(array_merge(
            $this->hidden,
            ['two_factor_secret', 'two_factor_recovery_codes'],
        )));
    }

    /**
     * Секрет TOTP или null, если 2FA не настроена.
     *
     * Нерасшифруемое значение (сменили APP_KEY, битая строка) трактуется
     * как «2FA не настроена»: иначе страница входа падала бы с 500 и
     * админ не смог бы войти вообще. Fail open здесь осознанный — выбор
     * между «вход по одному паролю» и «вход невозможен никому».
     */
    public function getAppAuthenticationSecret(): ?string
    {
        $secret = $this->getAttribute('two_factor_secret');

        if (blank($secret)) {
            return null;
        }

        try {
            return Crypt::decryptString($secret);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Сохраняет секрет TOTP (null — отключает 2FA).
     *
     * @param  ?string  $secret  сырой секрет; шифруется перед записью
     */
    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->setAttribute(
            'two_factor_secret',
            $secret === null ? null : Crypt::encryptString($secret),
        );

        if ($this->hasTwoFactorConfirmedAtColumn()) {
            $this->setAttribute('two_factor_confirmed_at', $secret === null ? null : now());
        }

        $this->save();
    }

    /**
     * Подпись записи в приложении-аутентификаторе — по ней админ
     * понимает, к какому аккаунту относится код.
     *
     * Переопределите в модели, если нужен не email.
     */
    public function getAppAuthenticationHolderName(): string
    {
        return (string) ($this->getAttribute('email') ?? $this->getAttribute('name') ?? $this->getKey());
    }

    /**
     * Хеши кодов восстановления или null, если их нет.
     *
     * @return ?array<string>
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        $codes = $this->getAttribute('two_factor_recovery_codes');

        if (blank($codes)) {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($codes), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : null;
    }

    /**
     * Сохраняет коды восстановления (уже хешированные Filament).
     *
     * @param  ?array<string>  $codes  null — убрать коды
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->setAttribute(
            'two_factor_recovery_codes',
            $codes === null ? null : Crypt::encryptString(json_encode(array_values($codes), JSON_THROW_ON_ERROR)),
        );

        $this->save();
    }

    /**
     * Включена ли 2FA у этого пользователя.
     *
     * Удобно для таблиц и политик: `->boolean('has_two_factor')`.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->getAppAuthenticationSecret());
    }

    /**
     * Полностью снимает 2FA: секрет и коды восстановления.
     *
     * Используется командой `admin-2fa:disable` и действием сброса в
     * админке. После вызова пользователь входит по одному паролю (а при
     * `admin-2fa.required = true` — будет отправлен на настройку заново).
     */
    public function disableTwoFactorAuthentication(): void
    {
        $this->setAttribute('two_factor_secret', null);
        $this->setAttribute('two_factor_recovery_codes', null);

        if ($this->hasTwoFactorConfirmedAtColumn()) {
            $this->setAttribute('two_factor_confirmed_at', null);
        }

        $this->save();
    }

    /**
     * Есть ли в таблице необязательная колонка подтверждения.
     *
     * Колонка добавляется миграцией пакета, но на панели, куда 2FA
     * прикручивали раньше вручную, её может не быть — тогда пакет
     * просто обходится без отметки времени.
     */
    protected function hasTwoFactorConfirmedAtColumn(): bool
    {
        static $has = [];

        $table = $this->getTable();

        return $has[$table] ??= $this->getConnection()
            ->getSchemaBuilder()
            ->hasColumn($table, 'two_factor_confirmed_at');
    }
}
