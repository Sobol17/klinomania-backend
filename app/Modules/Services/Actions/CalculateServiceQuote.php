<?php

namespace App\Modules\Services\Actions;

use App\Models\CleaningService;
use App\Models\ServiceOption;
use App\Models\ServiceQuote;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CalculateServiceQuote
{
    /** @param array{service_id:string, area_sqm:int, room_option_id:string, cleaning_option_id:string, extra_option_ids?:array<int, string>} $input */
    public function execute(User $user, array $input): ServiceQuote
    {
        $service = CleaningService::query()->where('slug', $input['service_id'])->where('is_active', true)->first();

        if ($service === null) {
            throw ValidationException::withMessages(['service_id' => ['The selected service is unavailable.']]);
        }

        $area = $input['area_sqm'];
        if ($area < $service->min_area || $area > $service->max_area || (($area - $service->min_area) % $service->area_step) !== 0) {
            throw ValidationException::withMessages(['area_sqm' => ['The area is outside the service limits.']]);
        }

        $codes = array_merge([$input['room_option_id'], $input['cleaning_option_id']], $input['extra_option_ids'] ?? []);
        if (count($codes) !== count(array_unique($codes))) {
            throw ValidationException::withMessages(['extra_option_ids' => ['Each option may only be selected once.']]);
        }

        $options = ServiceOption::query()->where('cleaning_service_id', $service->id)->where('is_active', true)->whereIn('code', $codes)->get()->keyBy('code');
        if ($options->count() !== count($codes)) {
            throw ValidationException::withMessages(['extra_option_ids' => ['One or more options are unavailable.']]);
        }

        $room = $options[$input['room_option_id']];
        $cleaning = $options[$input['cleaning_option_id']];
        if ($room->group !== 'room' || $room->is_addon || $cleaning->group !== 'cleaning' || $cleaning->is_addon) {
            throw ValidationException::withMessages(['room_option_id' => ['The selected configuration is invalid.']]);
        }

        $extras = collect($input['extra_option_ids'] ?? [])->map(fn (string $code) => $options[$code]);
        if ($extras->contains(fn (ServiceOption $option) => ! $option->is_addon)) {
            throw ValidationException::withMessages(['extra_option_ids' => ['Extra options must be add-ons.']]);
        }

        foreach ($extras as $extra) {
            $allowed = $extra->allowedWith()->pluck('service_options.id');
            if ($allowed->isNotEmpty() && ! $allowed->contains($room->id)) {
                throw ValidationException::withMessages(['extra_option_ids' => ["Option {$extra->code} is incompatible with {$room->code}."]]);
            }
        }

        $lineItems = [[
            'kind' => 'base', 'title' => $service->name, 'amount' => $service->base_price + ($service->price_per_sqm * $area),
        ]];
        foreach ([[$room, 'room_option'], [$cleaning, 'cleaning_option']] as [$option, $kind]) {
            if ($option->price_modifier !== 0) {
                $lineItems[] = ['kind' => $kind, 'option_id' => $option->code, 'title' => $option->title, 'amount' => $option->price_modifier];
            }
        }
        foreach ($extras as $extra) {
            $lineItems[] = ['kind' => 'extra_option', 'option_id' => $extra->code, 'title' => $extra->title, 'amount' => $extra->price_modifier];
        }
        $total = max($service->min_price, array_sum(array_column($lineItems, 'amount')));

        return ServiceQuote::create([
            'user_id' => $user->id, 'cleaning_service_id' => $service->id,
            'configuration' => ['service_id' => $service->slug, 'area_sqm' => $area, 'room_option_id' => $room->code, 'cleaning_option_id' => $cleaning->code, 'extra_option_ids' => $extras->pluck('code')->values()->all()],
            'line_items' => $lineItems, 'service_snapshot' => ['id' => $service->slug, 'title' => $service->name],
            'total_price' => $total, 'currency' => $service->currency, 'expires_at' => now()->addMinutes(15),
        ]);
    }
}
