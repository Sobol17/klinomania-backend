<?php

namespace App\Modules\Complaints\Actions;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeComplaintStatus
{
    public function execute(Complaint $complaint, ComplaintStatus $status, ?User $admin, ?string $comment): Complaint
    {
        $comment = filled($comment) ? trim($comment) : null;

        if (in_array($status, [ComplaintStatus::Resolved, ComplaintStatus::Rejected], true) && $comment === null) {
            throw ValidationException::withMessages([
                'admin_comment' => ['Комментарий обязателен при решении или отклонении жалобы.'],
            ]);
        }

        return DB::transaction(function () use ($complaint, $status, $admin, $comment): Complaint {
            $closed = in_array($status, [ComplaintStatus::Resolved, ComplaintStatus::Rejected], true);

            $complaint->update([
                'status' => $status,
                'admin_comment' => $comment ?? $complaint->admin_comment,
                'resolved_at' => $closed ? now() : null,
                'resolved_by' => $closed ? $admin?->id : null,
            ]);

            return $complaint->refresh();
        });
    }
}
