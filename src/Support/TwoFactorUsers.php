<?php

declare(strict_types=1);

namespace Kilke\FilamentAdmin2fa\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Kilke\FilamentAdmin2fa\Concerns\HasTwoFactorAuthentication;
use RuntimeException;

/**
 * Доступ к пользователям панели по настройке `admin-2fa.model`.
 *
 * Отдельный класс, а не статика в командах: пакет не знает, на какой
 * модели стоит панель, и ошибку конфигурации надо объяснять один раз
 * и понятно.
 */
class TwoFactorUsers
{
    /**
     * Новый запрос к модели пользователей панели.
     *
     * @throws RuntimeException если `admin-2fa.model` указывает не на
     *                          Eloquent-модель с трейтом пакета
     */
    public function query(): Builder
    {
        return $this->model()->newQuery();
    }

    /**
     * Все пользователи панели.
     *
     * @return Collection<int, Model>
     */
    public function all(): Collection
    {
        return $this->query()->orderBy('email')->get();
    }

    /**
     * Ищет пользователя панели по email.
     */
    public function findByEmail(string $email): ?Model
    {
        return $this->query()->where('email', $email)->first();
    }

    /**
     * Включена ли 2FA у пользователя.
     */
    public function isEnabled(Model $user): bool
    {
        return filled($user->getAttribute('two_factor_secret'));
    }

    /**
     * Снимает 2FA с пользователя.
     */
    public function disable(Model $user): void
    {
        /** @var Model&object{disableTwoFactorAuthentication: callable} $user */
        $user->disableTwoFactorAuthentication();
    }

    /**
     * Экземпляр модели из конфига, с проверками.
     *
     * @throws RuntimeException с объяснением, что именно настроено не так
     */
    protected function model(): Model
    {
        $class = (string) config('admin-2fa.model', '');

        if ($class === '' || ! class_exists($class)) {
            throw new RuntimeException(
                "Модель [{$class}] не найдена. Укажите модель пользователей панели в "
                ."config('admin-2fa.model') (например App\\Models\\Admin).",
            );
        }

        $model = new $class;

        if (! $model instanceof Model) {
            throw new RuntimeException("[{$class}] не является Eloquent-моделью.");
        }

        if (! in_array(HasTwoFactorAuthentication::class, class_uses_recursive($model), true)) {
            throw new RuntimeException(
                "[{$class}] не использует трейт ".HasTwoFactorAuthentication::class
                .'. Добавьте его в модель вместе с интерфейсами HasAppAuthentication '
                .'и HasAppAuthenticationRecovery.',
            );
        }

        return $model;
    }
}
