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

        if (! $cleaner || ! $cleaner->cleanerProfile?->is_active || ! $cleaner->cleanerProfile->access_code_hash) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        if (! Hash::check($data['code'], $cleaner->cleanerProfile->access_code_hash)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        return response()->json([
            'token' => $cleaner->createToken('cleaner-mobile')->plainTextToken,
            'user' => $cleaner,
        ]);
    }
}
