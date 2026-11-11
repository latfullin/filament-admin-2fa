<?php

declare(strict_types=1);

namespace Kilke\FilamentAdmin2fa\Tests\Fixtures;

use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Kilke\FilamentAdmin2fa\Concerns\HasTwoFactorAuthentication;

/**
 * Модель пользователя панели для тестов пакета.
 */
class TestAdmin extends Authenticatable implements HasAppAuthentication, HasAppAuthenticationRecovery
{
    use HasTwoFactorAuthentication;

    protected $table = 'test_admins';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password'];
}
