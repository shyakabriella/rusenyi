<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cell extends Model
{
    protected $fillable = [
        'sector_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sector_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(
            Sector::class
        );
    }

    public function villages(): HasMany
    {
        return $this->hasMany(
            Village::class
        );
    }
}
