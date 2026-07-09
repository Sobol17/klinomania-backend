<?php

namespace App\Modules\Services\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CleaningService;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CleaningService::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }
}
