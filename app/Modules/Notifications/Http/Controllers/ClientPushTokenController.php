<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ClientProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ClientPushTokenController extends Controller
{
    public function store(Request $request): Response
    {
        abort_unless($request->user()->role === UserRole::Client, 403);

        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            ClientProfile::query()
                ->where('fcm_token', $data['token'])
                ->where('user_id', '!=', $request->user()->id)
                ->update(['fcm_token' => null]);

            $request->user()->clientProfile()->updateOrCreate([], ['fcm_token' => $data['token']]);
        });

        return response()->noContent();
    }
}
