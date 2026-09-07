<?php

namespace App\Modules\Identity\Actions;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class ResetCleanerAccessCode
{
    public function execute(User $user): string
    {
        if ($user->role !== UserRole::Cleaner || ! $profile = $user->cleanerProfile) {
            throw new InvalidArgumentException('Требуется профиль клинера.');
        }

        do {
            $code = (string) random_int(100000, 999999);
        } while ($profile->access_code_hash && Hash::check($code, $profile->access_code_hash));

        $profile->update(['access_code_hash' => Hash::make($code)]);

        return $code;
    }
}
