<?php

namespace App\Modules\Profiles\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function client(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Client, 403);

        return response()->json([
            'data' => $request->user()->load('clientProfile'),
        ]);
    }

    public function updateClient(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Client, 403);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'push_notifications_enabled' => ['sometimes', 'boolean'],
            'email_marketing_enabled' => ['sometimes', 'boolean'],
        ]);

        $userData = [];

        foreach (['name', 'email'] as $field) {
            if (array_key_exists($field, $data)) {
                $userData[$field] = $data[$field];
            }
        }

        $request->user()->forceFill($userData)->save();

        $profileData = [];

        foreach (['name', 'address', 'push_notifications_enabled', 'email_marketing_enabled'] as $field) {
            if (array_key_exists($field, $data)) {
                $profileData[$field] = $data[$field];
            }
        }

        $request->user()->clientProfile()->updateOrCreate([], $profileData);

        return response()->json([
            'data' => $request->user()->refresh()->load('clientProfile'),
        ]);
    }

    public function cleaner(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Cleaner, 403);

        return response()->json([
            'data' => $request->user()->load('cleanerProfile'),
        ]);
    }
}
