<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CollectionPoint extends Model
{
    protected $fillable = [
        'village_id',
        'name',
        'code',
        'latitude',
        'longitude',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'village_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(
            Village::class
        );
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'agent_collection_points',
            'collection_point_id',
            'user_id'
        )
            ->withPivot([
                'is_active',
                'assigned_at',
                'unassigned_at',
            ])
            ->withTimestamps();
    }
}
