<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFcmTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    use ApiResponseTrait;

    // POST /api/fcm-tokens (auth) — register/refresh a device token for the current user
    public function store(StoreFcmTokenRequest $request): JsonResponse
    {
        $request->user()->fcmTokens()->updateOrCreate(
            ['token' => $request->string('token')],
            ['device_type' => $request->input('device_type')]
        );

        return $this->success(null, 'Token registered');
    }

    // DELETE /api/fcm-tokens (auth) — unregister a device token (e.g. on logout)
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $request->user()->fcmTokens()->where('token', $request->string('token'))->delete();

        return $this->success(null, 'Token removed');
    }
}
