<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, LogsActivity;

    const ROLE_OWNER_ADMIN = 'owner_admin';
    const ROLE_BRANCH_MANAGER = 'branch_manager';
    const ROLE_PHARMACIST_CLERK = 'pharmacist_clerk';

    const ROLES = [
        self::ROLE_OWNER_ADMIN => 'Owner / Admin',
        self::ROLE_BRANCH_MANAGER => 'Branch Manager',
        self::ROLE_PHARMACIST_CLERK => 'Pharmacist / Clerk',
    ];

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'branch_id',
        'active',
        'dashboard_shortcuts',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'active' => 'boolean',
        'dashboard_shortcuts' => 'array',
    ];

    /**
     * Usernames are the login identifier, so they are always stored trimmed
     * and lower-cased. That makes lookups and the unique index behave the same
     * on every database (MySQL's collation is case-insensitive, SQLite's is
     * not). An empty value is stored as NULL.
     */
    public function setUsernameAttribute($value): void
    {
        $value = trim((string) $value);

        $this->attributes['username'] = $value === '' ? null : Str::lower($value);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst(str_replace('_', ' ', $this->role ?? ''));
    }
}
