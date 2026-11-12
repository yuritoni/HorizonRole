<?php

namespace App\Models;

use App\Support\PrivilegeRegistry;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'photo_path',
        'role',
        'account_status',
        'activated_at',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canManageRecords(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return collect(array_keys(\App\Support\ErpModules::all()))
            ->contains(fn ($module) => $this->hasPrivilege("modules.{$module}.create") || $this->hasPrivilege("modules.{$module}.edit"));
    }

    public function hasPrivilege(string $key): bool
    {
        if ($this->isAdmin() && in_array($key, ['dashboard.view', 'reports.view', 'users.manage', 'privileges.manage', 'master-data.manage', 'employee.portal', 'employee.manage', 'announcements.view', 'announcements.manage', 'calendar.view', 'calendar.manage'], true)) {
            return true;
        }

        if (! Schema::hasTable('privileges') || ! Schema::hasTable('role_privileges')) {
            return in_array($key, PrivilegeRegistry::defaultKeysForRole($this->role), true);
        }

        $privilege = Privilege::where('key', $key)->first();

        if (! $privilege) {
            return false;
        }

        if (Schema::hasTable('user_privileges')) {
            $override = $this->userPrivilegeOverrides()
                ->where('privilege_id', $privilege->id)
                ->first();

            if ($override) {
                return $override->effect === 'grant';
            }
        }

        return DB::table('role_privileges')
            ->where('role', $this->role)
            ->where('privilege_id', $privilege->id)
            ->exists();
    }

    public function canAccess(string $key): bool
    {
        return $this->hasPrivilege($key);
    }

    public function isActive(): bool
    {
        return $this->account_status === 'active';
    }

    public function activate(): void
    {
        $this->forceFill([
            'account_status' => 'active',
            'activated_at' => now(),
        ])->save();
    }

    public function erpNotifications()
    {
        return $this->hasMany(ErpNotification::class);
    }

    public function unreadErpNotifications()
    {
        return $this->erpNotifications()->whereNull('read_at');
    }

    public function fcmDeviceTokens()
    {
        return $this->hasMany(FcmDeviceToken::class);
    }

    public function privileges()
    {
        return $this->belongsToMany(Privilege::class, 'user_privileges')
            ->withPivot('effect', 'notes')
            ->withTimestamps();
    }

    public function userPrivilegeOverrides()
    {
        return $this->hasMany(UserPrivilege::class);
    }

    public function employeeProfile()
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function announcementReads()
    {
        return $this->hasMany(EmployeeAnnouncementRead::class);
    }
}
