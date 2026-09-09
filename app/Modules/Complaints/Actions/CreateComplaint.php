<?php

namespace App\Modules\Complaints\Actions;

use App\Enums\ComplaintStatus;
use App\Models\CleaningOrder;
use App\Models\Complaint;
use App\Models\User;
use App\Modules\Complaints\Events\ComplaintCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class CreateComplaint
{
    /** @param array{subject: string, message: string} $input */
    public function execute(User $client, CleaningOrder $order, array $input): Complaint
    {
        abort_unless($order->client_id === $client->id, 403);

        if (! $order->status->allowsComplaint()) {
            abort(response()->json([
                'message' => 'A complaint cannot be submitted for an order in its current status.',
                'code' => 'complaint_not_allowed',
            ], 409));
        }

        return DB::transaction(function () use ($client, $order, $input): Complaint {
            $complaint = Complaint::query()->create([
                'cleaning_order_id' => $order->id,
                'client_id' => $client->id,
                'status' => ComplaintStatus::New,
                'subject' => $input['subject'],
                'message' => $input['message'],
            ]);

            Event::dispatch(new ComplaintCreated($complaint->id));

            return $complaint;
        });
    }
}
