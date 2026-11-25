<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $this->errorResponse('Email atau kata sandi admin salah.', 401);
        }

        if (! $user->isAdmin()) {
            return $this->errorResponse('Akses ditolak. Akun ini bukan administrator.', 403);
        }

        if (! $user->is_active) {
            return $this->errorResponse('Akun administrator sedang dinonaktifkan.', 403);
        }

        $token = $user->createToken('postman-admin')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 'Login administrator berhasil.');
    }
}