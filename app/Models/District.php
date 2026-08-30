<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $fillable = [
        'province_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'province_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(
            Province::class
        );
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(
            Sector::class
        );
    }
}
