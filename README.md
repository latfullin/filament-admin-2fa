# filament-admin-2fa

Переиспользуемая TOTP-двухфакторная аутентификация для панелей Filament 5.

Filament 5 умеет multi-factor сам — криптография, QR, коды восстановления и
защита от повторного ввода кода там уже написаны и протестированы. Этот пакет
их **не переписывает**. Он убирает то, что иначе копируется руками в каждый
новый проект:

- миграция с колонками `two_factor_secret` / `two_factor_recovery_codes` /
  `two_factor_confirmed_at` для вашей таблицы;
- трейт модели, реализующий контракты Filament, **с шифрованием секрета**
  (Filament хранение не определяет — это ваша часть);
- плагин панели: подключение одной строкой вместо ручной сборки провайдера;
- `admin-2fa:disable` — путь восстановления при потере телефона;
- `admin-2fa:status` — кто ещё не привязал приложение (нужно перед включением
  обязательной 2FA);
- конфиг с env-переменными и test-хелперы.

## Установка

```bash
composer require kilke/filament-admin-2fa
```

Пакет требует `filament/filament ^5.0` и PHP 8.3+.

### 1. Конфиг и миграция

```bash
php artisan vendor:publish --tag=admin-2fa-config
php artisan vendor:publish --tag=admin-2fa-migrations
```

В `config/admin-2fa.php` укажите модель и таблицу пользователей панели:

```php
'model' => 'App\Models\Admin',
'table' => 'admins',
```

Затем:

```bash
php artisan migrate
```

### 2. Модель

```php
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Kilke\FilamentAdmin2fa\Concerns\HasTwoFactorAuthentication;

class Admin extends Authenticatable implements
    FilamentUser,
    HasAppAuthentication,
    HasAppAuthenticationRecovery
{
    use HasTwoFactorAuthentication;
}
```

Трейт сам добавляет 2FA-поля в `$hidden`.

### 3. Панель

```php
use Kilke\FilamentAdmin2fa\TwoFactorPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(TwoFactorPlugin::make());
}
```

Готово. В профиле появляется раздел настройки 2FA с QR-кодом, при входе —
запрос кода.

> Страница профиля должна быть включена (`->profile()`), иначе админу негде
> привязать приложение, пока 2FA не обязательна. При `required => true`
> Filament уводит на отдельную страницу настройки, и профиль не нужен.

## Настройка

Всё читается из конфига, переопределяется по месту:

```php
->plugin(
    TwoFactorPlugin::make()
        ->required()                        // без 2FA панель недоступна
        ->brandName('Админка сервиса')      // подпись в Google Authenticator
        ->recoveryCodes(count: 10)          // коды восстановления
        ->codeWindow(4),                    // ±2 минуты вместо ±4
)
```

| env | по умолчанию | что делает |
| --- | --- | --- |
| `ADMIN_2FA_REQUIRED` | `false` | обязательна ли 2FA |
| `ADMIN_2FA_BRAND_NAME` | brandName панели | подпись записи в приложении |
| `ADMIN_2FA_RECOVERY_CODES` | `true` | коды восстановления |
| `ADMIN_2FA_RECOVERY_CODE_COUNT` | `8` | сколько кодов |
| `ADMIN_2FA_RECOVERY_REGENERABLE` | `true` | можно ли перегенерировать |
| `ADMIN_2FA_CODE_WINDOW` | `8` | запас на расхождение часов (×30 с) |
| `ADMIN_2FA_MODEL` | `App\Models\Admin` | модель пользователей панели |
| `ADMIN_2FA_TABLE` | `admins` | таблица для миграции |

## Внедрение на живой панели

Порядок, который не запирает админов снаружи:

1. Подключите пакет с `required => false`.
2. Админы привязывают приложение в профиле.
3. `php artisan admin-2fa:status --pending` — убедитесь, что список пуст.
4. Переключите `ADMIN_2FA_REQUIRED=true`.

Сразу `true` тоже работает (Filament отправит на страницу настройки), но тогда
у админа нет пути назад, если TOTP-приложение недоступно.

## Потерян телефон

```bash
php artisan admin-2fa:disable admin@example.com
```

Коды восстановления — первый путь; эта команда — второй, если их не
сохранили. Веб-страницы «сбросить себе 2FA» в пакете нет сознательно: она
обходила бы второй фактор через угнанную сессию.

## Как хранится секрет

- **Секрет TOTP** шифруется `APP_KEY` (`Crypt::encryptString`). Хешировать его
  нельзя — им генерируются коды, нужно обратимое преобразование.
  **Потеря `APP_KEY` = потеря всех секретов**, админам нужно привязывать
  приложение заново.
- **Коды восстановления** Filament хешует (`Hash::make`) до передачи в модель;
  пакет шифрует уже массив хешей.
- Нерасшифруемый секрет (сменили `APP_KEY`) трактуется как «2FA не настроена».
  Это fail open: выбор между входом по одному паролю и невозможностью войти
  вообще. При `required => true` такой админ попадёт на настройку заново.

## Защита от повторного ввода кода

Filament помнит принятый код в кеше, чтобы его нельзя было использовать дважды
(RFC 6238). Стор берётся **по умолчанию** (`Cache::store()`) — своего выбора
Filament 5.11 не даёт. Поэтому на нескольких воркерах или серверах кеш по
умолчанию должен быть общим (`redis`, `memcached`): с `array` или
`file`-на-каждом-сервере защита от повтора фактически не работает.

## Тесты

```php
use Kilke\FilamentAdmin2fa\Testing\InteractsWithTwoFactor;

class AdminLoginTest extends TestCase
{
    use InteractsWithTwoFactor;

    public function test_admin_with_2fa_is_challenged(): void
    {
        $admin = Admin::factory()->create();
        $this->enableTwoFactor($admin);

        $this->assertTrue($admin->hasTwoFactorEnabled());
        $this->assertNotNull($this->currentTwoFactorCode($admin));
    }
}
```

## Лицензия

MIT.
