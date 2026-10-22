<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password', 'role', 'customer_id', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Permission keys for this user's role, loaded once per request. */
    private ?array $permissionKeys = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /** Password reset link, sent through MailService so it appears in the email log. */
    public function sendPasswordResetNotification($token): void
    {
        $url = route('password.reset', ['token' => $token, 'email' => $this->email]);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        app(\App\Services\MailService::class)->send($this->email, new \App\Mail\PasswordResetMail($this, $url, $minutes), 'password_reset', $this);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role', 'slug');
    }

    /** Dealer account for dealer-portal users. */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isDealer(): bool
    {
        return $this->role === 'dealer';
    }

    /** True when the user's role grants the permission. Super Admin has every permission. */
    public function hasPermission(string $key): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $this->permissionKeys ??= DB::table('permission_role')
            ->join('roles', 'roles.id', '=', 'permission_role.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('roles.slug', $this->role)
            ->pluck('permissions.key')
            ->flip()
            ->all();

        return isset($this->permissionKeys[$key]);
    }

    public function roleLabel(): string
    {
        return $this->roleModel?->name ?? $this->role;
    }

    /** Dealers this user may see: all, or only those assigned to them as sales rep. */
    public function canSeeAllDealers(): bool
    {
        return $this->hasPermission('customers.view_all');
    }
}
