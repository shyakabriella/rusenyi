<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Village extends Model
{
    protected $fillable = [
        'cell_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cell_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function cell(): BelongsTo
    {
        return $this->belongsTo(
            Cell::class
        );
    }

    public function collectionPoints(): HasMany
    {
        return $this->hasMany(
            CollectionPoint::class
        );
    }
}
