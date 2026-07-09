<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Enums\AuthCodePurpose;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuthCode;
use App\Models\User;
use App\Modules\Identity\Contracts\SmsGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ClientAuthController extends Controller
{
    public function requestCode(Request $request, SmsGateway $smsGateway): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $code = (string) random_int(1000, 9999);

        AuthCode::create([
            'phone' => $data['phone'],
            'code_hash' => Hash::make($code),
            'purpose' => AuthCodePurpose::ClientLogin,
            'expires_at' => now()->addMinutes(5),
        ]);

        $smsGateway->send($data['phone'], "Код входа Klinomania: {$code}");

        return response()->json(['message' => 'Code sent.']);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'digits:4'],
        ]);

        $authCode = AuthCode::query()
            ->where('phone', $data['phone'])
            ->where('purpose', AuthCodePurpose::ClientLogin)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $authCode || $authCode->attempts >= 5 || ! Hash::check($data['code'], $authCode->code_hash)) {
            $authCode?->increment('attempts');

            return response()->json(['message' => 'Invalid code.'], 422);
        }

        $authCode->forceFill(['consumed_at' => now()])->save();

        $user = User::firstOrCreate(
            ['phone' => $data['phone']],
            ['role' => UserRole::Client, 'name' => null]
        );

        $user->clientProfile()->firstOrCreate([]);

        return response()->json([
            'token' => $user->createToken('client-mobile')->plainTextToken,
            'user' => $user,
        ]);
    }
}
