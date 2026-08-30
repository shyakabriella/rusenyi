<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class LocationPagination
{
    public static function make(
        LengthAwarePaginator $paginator,
        string $resourceClass,
        Request $request
    ): array {
        return [
            'items' => $resourceClass::collection(
                $paginator->getCollection()
            )->resolve($request),

            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
