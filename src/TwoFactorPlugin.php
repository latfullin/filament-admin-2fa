<?php

declare(strict_types=1);

namespace Kilke\FilamentAdmin2fa;

use Closure;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Плагин панели: включает TOTP-двухфакторную аутентификацию.
 *
 * Подключение в PanelProvider одной строкой:
 *
 *     ->plugin(TwoFactorPlugin::make())
 *
 * Всё поведение берётся из config/admin-2fa.php; при необходимости
 * переопределяется по месту:
 *
 *     ->plugin(
 *         TwoFactorPlugin::make()
 *             ->required()
 *             ->brandName('Админка сервиса')
 *             ->recoveryCodes(count: 10),
 *     )
 *
 * Пакет не переписывает криптографию и защиту от повторного ввода кода —
 * это делает встроенный в Filament AppAuthentication. Плагин отвечает за
 * то, что иначе копируется руками в каждый проект: конфиг, миграцию,
 * трейт модели, команды и стартовые проверки.
 */
class TwoFactorPlugin implements Plugin
{
    protected ?bool $isRequired = null;

    protected string|Closure|null $brandName = null;

    protected ?bool $hasRecoveryCodes = null;

    protected ?int $recoveryCodeCount = null;

    protected ?bool $canRegenerateRecoveryCodes = null;

    protected ?int $codeWindow = null;

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Идентификатор плагина в реестре Filament.
     */
    public function getId(): string
    {
        return 'admin-2fa';
    }

    /**
     * Регистрирует TOTP-провайдер в панели.
     *
     * При `required` Filament сам добавляет middleware, который не пускает
     * в панель пользователя без привязанного приложения и уводит его на
     * страницу настройки.
     */
    public function register(Panel $panel): void
    {
        $panel->multiFactorAuthentication(
            providers: [$this->makeProvider()],
            isRequired: $this->isRequired(),
        );
    }

    /**
     * Хук Filament после загрузки панели; пакету здесь нечего делать.
     */
    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Собирает встроенный провайдер Filament с настройками пакета.
     */
    protected function makeProvider(): AppAuthentication
    {
        $provider = AppAuthentication::make()
            ->codeWindow($this->resolveCodeWindow());

        $brandName = $this->resolveBrandName();

        if (filled($brandName)) {
            $provider->brandName($brandName);
        }

        if ($this->hasRecoveryCodes()) {
            $provider
                ->recoverable()
                ->recoveryCodeCount($this->recoveryCodeCount())
                ->regenerableRecoveryCodes($this->canRegenerateRecoveryCodes());
        } else {
            $provider->recoverable(false);
        }

        return $provider;
    }

    /**
     * Делает 2FA обязательной: без привязанного приложения панель
     * недоступна.
     */
    public function required(bool $condition = true): static
    {
        $this->isRequired = $condition;

        return $this;
    }

    /**
     * Подпись записи в приложении-аутентификаторе.
     */
    public function brandName(string|Closure|null $name): static
    {
        $this->brandName = $name;

        return $this;
    }

    /**
     * Настраивает коды восстановления.
     *
     * @param  bool  $enabled  выключение оставляет единственный путь сброса — консоль
     */
    public function recoveryCodes(bool $enabled = true, ?int $count = null, ?bool $regenerable = null): static
    {
        $this->hasRecoveryCodes = $enabled;

        if ($count !== null) {
            $this->recoveryCodeCount = $count;
        }

        if ($regenerable !== null) {
            $this->canRegenerateRecoveryCodes = $regenerable;
        }

        return $this;
    }

    /**
     * Сколько 30-секундных интервалов вокруг текущего принимается.
     */
    public function codeWindow(int $window): static
    {
        $this->codeWindow = $window;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->isRequired ?? (bool) config('admin-2fa.required', false);
    }

    protected function resolveBrandName(): ?string
    {
        $name = $this->brandName instanceof Closure
            ? ($this->brandName)()
            : $this->brandName;

        $name ??= config('admin-2fa.brand_name');

        return filled($name) ? (string) $name : null;
    }

    protected function hasRecoveryCodes(): bool
    {
        return $this->hasRecoveryCodes ?? (bool) config('admin-2fa.recovery_codes.enabled', true);
    }

    protected function recoveryCodeCount(): int
    {
        return $this->recoveryCodeCount ?? (int) config('admin-2fa.recovery_codes.count', 8);
    }

    protected function canRegenerateRecoveryCodes(): bool
    {
        return $this->canRegenerateRecoveryCodes ?? (bool) config('admin-2fa.recovery_codes.regenerable', true);
    }

    protected function resolveCodeWindow(): int
    {
        return $this->codeWindow ?? (int) config('admin-2fa.code_window', 8);
    }
}
