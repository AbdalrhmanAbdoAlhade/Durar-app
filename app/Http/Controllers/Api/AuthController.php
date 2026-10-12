<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\CartService;
use App\Services\WhatsAppService;
use App\Traits\ApiResponseTrait;
use App\Traits\ImageConverterTrait;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponseTrait;
    use ImageConverterTrait;

    public function __construct(
        protected CartService $carts
    ) {}

    // POST /api/auth/register
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->string('name'),
            'email'    => $request->string('email'),
            'phone'    => $request->input('phone'),
            'password' => Hash::make($request->string('password')),
        ]);

        // WhatsApp - Account Created (بعد الرد عشان ميستناش)
        dispatch(fn () => WhatsAppService::accountCreated($user))->afterResponse();

        // دمج سلة الزائر
        $guestId = $this->carts->resolveGuestId($request);
        if ($guestId) {
            $this->carts->mergeGuestCartIntoUser($guestId, $user->id);
        }

        $token = $user->createToken('api')->plainTextToken;

        return $this->success([
            'user'  => new UserResource($user),
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

        // دمج سلة الزائر
        $guestId = $this->carts->resolveGuestId($request);
        if ($guestId) {
            $this->carts->mergeGuestCartIntoUser($guestId, $user->id);
        }

        $token = $user->createToken('api')->plainTextToken;

        return $this->success([
            'user'  => new UserResource($user->load('roles')),
            'token' => $token,
        ], 'Logged in successfully');
    }

    // POST /api/auth/logout
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        return $this->success(null, 'Logged out successfully');
    }

    // GET /api/auth/me
    public function me(Request $request): JsonResponse
    {
        return $this->success(
            new UserResource($request->user()->load('roles'))
        );
    }

    // PUT /api/auth/profile
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->only(['name', 'email', 'phone']);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete(ltrim($user->avatar, '/'));
            }

            $data['avatar'] = $this->storeImageAsWebp(
                $request->file('avatar'),
                'avatars',
                85,
                800,
                800
            );
        }

        $user->update($data);

        return $this->success(
            new UserResource($user->fresh()->load('roles')),
            'Profile updated successfully'
        );
    }

    // POST /api/auth/forgot-password
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? $this->success(null, __($status))
            : $this->error(__($status), 422);
    }

    // POST /api/auth/reset-password
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? $this->success(null, __($status))
            : $this->error(__($status), 422);
    }
	
  	// DELETE /api/auth/account
      public function deleteAccount(Request $request): JsonResponse
      {
          $user = $request->user();

          // حذف صورة البروفايل لو موجودة
          if ($user->avatar) {
              Storage::disk('public')->delete(ltrim($user->avatar, '/'));
          }

          // حذف كل التوكنات
          $user->tokens()->delete();

          // حذف الحساب نهائيًا
          $user->delete();

          return $this->success(null, 'تم حذف الحساب نهائيًا');
      }
  	
    // PUT /api/auth/password
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->string('current_password'), $user->password)) {
            return $this->error('Current password is incorrect', 422);
        }

        $user->update([
            'password' => Hash::make($request->string('password')),
        ]);

        // امسح باقي التوكنات (اختياري للأمان)
        $user->tokens()
            ->where('id', '!=', $user->currentAccessToken()->id)
            ->delete();

        return $this->success(null, 'Password changed successfully');
    }
}