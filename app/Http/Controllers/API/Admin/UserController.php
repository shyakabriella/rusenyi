<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class UserController extends BaseController
{
    public function __construct(
        private readonly UserCredentialService $credentialService
    ) {
    }

    /**
     * List users.
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = trim(
                        (string) $request->search
                    );

                    $query->where(
                        function ($q) use ($search) {
                            $q->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'phone',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->when(
                $request->filled('role'),
                fn ($query) => $query->where(
                    'role',
                    $request->role
                )
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->status
                )
            )
            ->orderBy('name')
            ->paginate(
                min(
                    max(
                        (int) $request->get(
                            'per_page',
                            20
                        ),
                        1
                    ),
                    100
                )
            );

        return $this->sendResponse(
            new UserCollection($users),
            'Users retrieved successfully.'
        );
    }

    /**
     * Create new user.
     */
    public function store(
        StoreUserRequest $request
    ): JsonResponse {
        $temporaryPassword =
            $this->credentialService
                ->generateTemporaryPassword();

        $user = User::create([
            'name' => trim($request->name),

            'email' => strtolower(
                trim($request->email)
            ),

            'phone' => trim(
                $request->phone
            ),

            'role' => $request->role,

            'password' => $temporaryPassword,

            'status' => 'active',

            'is_active' => true,

            'must_change_password' => true,
        ]);

        $emailSent = true;

        try {
            $this->credentialService
                ->sendCredentials(
                    $user,
                    $temporaryPassword
                );
        } catch (Throwable $e) {
            report($e);

            $emailSent = false;
        }

        return $this->sendResponse(
            [
                'user' => new UserResource(
                    $user->fresh()
                ),

                'credentials_email_sent' =>
                    $emailSent,
            ],
            $emailSent
                ? 'User created successfully. Login credentials were sent by email.'
                : 'User created successfully, but the credentials email could not be sent.',
            201
        );
    }

    /**
     * View user.
     */
    public function show(
        User $user
    ): JsonResponse {
        return $this->sendResponse(
            new UserResource($user),
            'User retrieved successfully.'
        );
    }

    /**
     * Update user.
     */
    public function update(
        UpdateUserRequest $request,
        User $user
    ): JsonResponse {
        $data = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Protect Admin Role
        |--------------------------------------------------------------------------
        */

        if (
            isset($data['role'])
            && $user->role === User::ROLE_ADMIN
            && $data['role'] !== User::ROLE_ADMIN
        ) {
            /*
             * Admin cannot remove their own Admin role.
             */
            if (
                $request->user()->id === $user->id
            ) {
                return $this->sendError(
                    'You cannot remove your own Admin role.',
                    null,
                    422
                );
            }

            /*
             * Never leave the system without
             * another active Admin.
             */
            if (
                !$this->hasAnotherActiveAdmin(
                    $user
                )
            ) {
                return $this->sendError(
                    'The last active Admin cannot be assigned another role.',
                    null,
                    422
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize Input
        |--------------------------------------------------------------------------
        */

        if (isset($data['name'])) {
            $data['name'] = trim(
                $data['name']
            );
        }

        if (isset($data['email'])) {
            $data['email'] = strtolower(
                trim($data['email'])
            );
        }

        if (isset($data['phone'])) {
            $data['phone'] = trim(
                $data['phone']
            );
        }

        $user->update($data);

        return $this->sendResponse(
            new UserResource(
                $user->fresh()
            ),
            'User updated successfully.'
        );
    }

    /**
     * Activate, deactivate or suspend user.
     */
    public function updateStatus(
        Request $request,
        User $user
    ): JsonResponse {
        $request->validate([
            'status' => [
                'required',

                Rule::in([
                    'active',
                    'inactive',
                    'suspended',
                ]),
            ],
        ]);

        $newStatus = $request->status;

        /*
        |--------------------------------------------------------------------------
        | Prevent Self Deactivation
        |--------------------------------------------------------------------------
        */

        if (
            $request->user()->id === $user->id
            && $newStatus !== 'active'
        ) {
            return $this->sendError(
                'You cannot deactivate or suspend your own account.',
                null,
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Protect Last Active Admin
        |--------------------------------------------------------------------------
        */

        if (
            $user->role === User::ROLE_ADMIN
            && $newStatus !== 'active'
            && !$this->hasAnotherActiveAdmin(
                $user
            )
        ) {
            return $this->sendError(
                'The last active Admin cannot be deactivated or suspended.',
                null,
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Update Account Status
        |--------------------------------------------------------------------------
        */

        $user->update([
            'status' => $newStatus,

            'is_active' =>
                $newStatus === 'active',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Revoke Sessions
        |--------------------------------------------------------------------------
        */

        if ($newStatus !== 'active') {
            $user->tokens()->delete();
        }

        return $this->sendResponse(
            new UserResource(
                $user->fresh()
            ),
            'User status updated successfully.'
        );
    }

    /**
     * Generate and send new temporary credentials.
     */
    public function resetCredentials(
        Request $request,
        User $user
    ): JsonResponse {
        if (
            !$user->is_active
            || $user->status !== 'active'
        ) {
            return $this->sendError(
                'Credentials cannot be reset for an inactive or suspended user.',
                null,
                422
            );
        }

        $temporaryPassword =
            $this->credentialService
                ->generateTemporaryPassword();

        try {
            /*
             * Password update, token revocation and
             * notification are treated as one operation.
             *
             * If notification fails, the database
             * changes are rolled back.
             */
            DB::transaction(
                function () use (
                    $user,
                    $temporaryPassword
                ) {
                    $user->update([
                        'password' =>
                            $temporaryPassword,

                        'must_change_password' =>
                            true,
                    ]);

                    /*
                     * Logout user from all devices.
                     */
                    $user->tokens()->delete();

                    $this->credentialService
                        ->sendCredentials(
                            $user,
                            $temporaryPassword
                        );
                }
            );
        } catch (Throwable $e) {
            report($e);

            return $this->sendError(
                'New credentials could not be generated and sent. The existing credentials remain unchanged.',
                null,
                500
            );
        }

        return $this->sendResponse(
            new UserResource(
                $user->fresh()
            ),
            'New login credentials were sent successfully.'
        );
    }

    /**
     * Resend credentials.
     *
     * Existing passwords cannot be retrieved because
     * they are hashed, therefore this creates a new
     * temporary password.
     */
    public function resendCredentials(
        Request $request,
        User $user
    ): JsonResponse {
        return $this->resetCredentials(
            $request,
            $user
        );
    }

    /**
     * Check whether another active Admin exists.
     */
    private function hasAnotherActiveAdmin(
        User $user
    ): bool {
        return User::query()
            ->where(
                'role',
                User::ROLE_ADMIN
            )
            ->where(
                'status',
                'active'
            )
            ->where(
                'is_active',
                true
            )
            ->where(
                'id',
                '!=',
                $user->id
            )
            ->exists();
    }
}
