<?php

namespace Nawasara\Vault\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Credential extends Model
{
    use LogsActivity;

    protected $table = 'nawasara_vault_credentials';

    protected $fillable = [
        'group',
        'key',
        'value',
        'instance',
        'description',
        'last_rotated_at',
        'last_accessed_at',
        'rotated_by',
    ];

    protected $casts = [
        'value' => 'encrypted',
        'last_rotated_at' => 'datetime',
        'last_accessed_at' => 'datetime',
    ];

    protected $hidden = ['value'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['group', 'key', 'instance', 'description'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Credential {$this->group}.{$this->key} {$eventName}");
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class, 'credential_id');
    }

    public function rotatedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'rotated_by');
    }

    public function getMaskedValueAttribute(): string
    {
        $raw = $this->value;

        if (! $raw || strlen($raw) <= 8) {
            return '••••••••';
        }

        return substr($raw, 0, 4).'••••••••'.substr($raw, -4);
    }

    public function scopeForGroup($query, ?string $group)
    {
        return $group ? $query->where('group', $group) : $query;
    }

    public function scopeForInstance($query, ?string $instance)
    {
        return $instance ? $query->where('instance', $instance) : $query->whereNull('instance');
    }
}
