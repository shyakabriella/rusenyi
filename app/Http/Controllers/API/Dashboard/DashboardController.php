<?php

namespace App\Http\Controllers\API\Dashboard;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    public function overview(
        Request $request,
        DashboardService $service
    ): JsonResponse {
        $user = $request->user();

        if (
            !in_array(
                $user->role,
                [
                    User::ROLE_ADMIN,
                    User::ROLE_ACCOUNTANT,
                ],
                true
            )
        ) {
            return $this->sendError(
                'You are not allowed to access the web dashboard.',
                null,
                403
            );
        }

        return $this->sendResponse(
            $service->overview($user->role),
            'Dashboard overview retrieved successfully.'
        );
    }
}
