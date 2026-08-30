<?php

namespace App\Http\Controllers\API;

use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends BaseController
{
    /**
     * Display all roles.
     */
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->orderBy('id')
            ->get();

        return $this->sendResponse(
            $roles,
            'Roles retrieved successfully.'
        );
    }

    /**
     * Display active roles only.
     *
     * Useful when creating or editing a user.
     */
    public function active(): JsonResponse
    {
        $roles = Role::query()
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        return $this->sendResponse(
            $roles,
            'Active roles retrieved successfully.'
        );
    }

    /**
     * Display a specific role.
     */
    public function show(int $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->sendError(
                'Role not found.',
                null,
                404
            );
        }

        return $this->sendResponse(
            $role,
            'Role retrieved successfully.'
        );
    }

    /**
     * Update role information.
     *
     * The role system name is intentionally not editable.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->sendError(
                'Role not found.',
                null,
                404
            );
        }

        $validator = Validator::make($request->all(), [
            'display_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation error.',
                $validator->errors(),
                422
            );
        }

        $role->update($validator->validated());

        return $this->sendResponse(
            $role->fresh(),
            'Role updated successfully.'
        );
    }
}
