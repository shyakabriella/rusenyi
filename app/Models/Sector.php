<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    protected $fillable = [
        'district_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'district_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(
            District::class
        );
    }

    public function cells(): HasMany
    {
        return $this->hasMany(
            Cell::class
        );
    }
}
