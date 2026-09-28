<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected CartService $carts)
    {
    }

    // POST /api/auth/register
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->string('password')),
        ]);

        $this->carts->mergeGuestCartIntoUser($request->session()->getId(), $user->id);

        $token = $user->createToken('api')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Registered successfully', 201);
    }

    // POST /api/auth/login
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            return $this->error('Invalid credentials', 401);
        }

        $this->carts->mergeGuestCartIntoUser($request->session()->getId(), $user->id);

        $token = $user->createToken('api')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user->load('roles')),
            'token' => $token,
        ], 'Logged in successfully');
    }

    // POST /api/auth/logout (auth)
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        return $this->success(null, 'Logged out successfully');
    }

    // GET /api/auth/me (auth)
    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()->load('roles')));
    }
}
