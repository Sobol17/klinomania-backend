<?php

namespace App\Modules\Complaints\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CleaningOrder;
use App\Models\Complaint;
use App\Modules\Complaints\Actions\CreateComplaint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientComplaintController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Client, 403);

        $complaints = Complaint::query()
            ->with('order:id,public_id')
            ->where('client_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (Complaint $complaint): array => $this->response($complaint));

        return response()->json(['data' => $complaints]);
    }

    public function store(Request $request, CleaningOrder $order, CreateComplaint $action): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Client, 403);
        abort_unless($order->client_id === $request->user()->id, 403);

        $input = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $complaint = $action->execute($request->user(), $order, $input)->load('order:id,public_id');

        return response()->json(['data' => $this->response($complaint)], 201);
    }

    /** @return array<string, mixed> */
    private function response(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'order_id' => $complaint->order->public_id,
            'status' => $complaint->status->value,
            'status_label' => $complaint->status->getLabel(),
            'subject' => $complaint->subject,
            'message' => $complaint->message,
            'admin_comment' => $complaint->admin_comment,
            'resolved_at' => $complaint->resolved_at,
            'created_at' => $complaint->created_at,
            'updated_at' => $complaint->updated_at,
        ];
    }
}
