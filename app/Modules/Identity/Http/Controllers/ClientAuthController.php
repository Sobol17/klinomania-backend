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

        $code = $this->generateClientCode();

        AuthCode::create([
            'phone' => $data['phone'],
            'code_hash' => Hash::make($code),
            'purpose' => AuthCodePurpose::ClientLogin,
            'expires_at' => now()->addMinutes(5),
        ]);

        $smsGateway->send($data['phone'], "Klinomania: Ваш код для входа: {$code}");

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

        if (! $authCode || $authCode->attempts >= 5 || ! $this->isValidClientCode($data['code'], $authCode)) {
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

    private function isValidClientCode(string $code, AuthCode $authCode): bool
    {
        if (config('klinomania.auth.client_otp_stub_enabled')) {
            return hash_equals((string) config('klinomania.auth.client_otp_stub_code'), $code);
        }

        return Hash::check($code, $authCode->code_hash);
    }

    private function generateClientCode(): string
    {
        $stubCode = (string) config('klinomania.auth.client_otp_stub_code');

        if (config('klinomania.auth.client_otp_stub_enabled')) {
            return $stubCode;
        }

        do {
            $code = (string) random_int(1000, 9999);
        } while ($code === $stubCode);

        return $code;
    }
}
