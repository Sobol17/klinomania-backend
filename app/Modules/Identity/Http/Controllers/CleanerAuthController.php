<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CleanerAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'digits:6'],
        ]);

        $cleaner = User::query()
            ->where('phone', $data['phone'])
            ->where('role', UserRole::Cleaner)
            ->with('cleanerProfile')
            ->first();

        if (! $cleaner || ! $cleaner->cleanerProfile?->is_active) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        if (! $this->isValidCleanerCode($data['code'], $cleaner->cleanerProfile->access_code_hash)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        return response()->json([
            'token' => $cleaner->createToken('cleaner-mobile')->plainTextToken,
            'user' => $cleaner,
        ]);
    }

    private function isValidCleanerCode(string $code, ?string $codeHash): bool
    {
        if (config('klinomania.auth.cleaner_code_stub_enabled')) {
            return hash_equals((string) config('klinomania.auth.cleaner_code_stub_code'), $code);
        }

        return $codeHash !== null && Hash::check($code, $codeHash);
    }
}
