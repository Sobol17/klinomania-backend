<?php

namespace App\Modules\Profiles\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function client(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Client, 403);

        return response()->json([
            'data' => $request->user()->load('clientProfile'),
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
