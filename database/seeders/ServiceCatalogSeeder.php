<?php

namespace Database\Seeders;

use App\Models\CleaningService;
use App\Models\ServiceOption;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->services() as $serviceData) {
            $options = $serviceData['options'];
            unset($serviceData['options']);

            $service = CleaningService::query()->updateOrCreate(
                ['slug' => $serviceData['slug']],
                $serviceData,
            );

            foreach ($options as $option) {
                ServiceOption::query()->updateOrCreate(
                    ['cleaning_service_id' => $service->id, 'code' => $option['code']],
                    $option,
                );
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function services(): array
    {
        return [
            $this->service('standard', 'Базовый минимум', 'Регулярная чистота без лишнего', 'Поддерживающая уборка для квартиры, в которой уже поддерживают порядок.', 7700, 10, 1, '1 клинер', '2–3 часа'),
            $this->service('premium', 'Генеральская', 'Глубокая уборка каждой зоны', 'Тщательная генеральная уборка кухни, ванной и жилых комнат.', 11900, 20, 2, '2 клинера', '4–5 часов'),
            $this->service('cottage', 'Роскошный максимум', 'Максимум заботы для большого дома', 'Расширенная уборка коттеджа с дополнительным вниманием к сложным зонам.', 18900, 30, 3, '3 клинера', '6–8 часов'),
        ];
    }

    /** @return array<string, mixed> */
    private function service(string $slug, string $name, string $subtitle, string $description, int $basePrice, int $areaPrice, int $sortOrder, string $cleanersLabel, string $durationLabel): array
    {
        return [
            'slug' => $slug,
            'name' => $name,
            'subtitle' => $subtitle,
            'description' => $description,
            'short_description' => $description,
            'long_description' => $description.' В стоимость включены все основные работы, а дополнительные услуги выбираются отдельно.',
            'cleaners_label' => $cleanersLabel,
            'duration_label' => $durationLabel,
            'image_url' => "https://cdn.klinomania.ru/services/{$slug}/hero.jpg",
            'gallery' => [
                "https://cdn.klinomania.ru/services/{$slug}/gallery-1.jpg",
                "https://cdn.klinomania.ru/services/{$slug}/gallery-2.jpg",
            ],
            'base_price' => $basePrice,
            'price_per_sqm' => $areaPrice,
            'min_area' => 30,
            'max_area' => 250,
            'area_step' => 10,
            'min_price' => $basePrice,
            'currency' => 'RUB',
            'sort_order' => $sortOrder,
            'is_active' => true,
            'options' => [
                ['code' => 'room-1', 'group' => 'room', 'title' => '1-комнатная', 'subtitle' => '30–50 м²', 'is_addon' => false, 'is_default' => true, 'price_modifier' => 0, 'sort_order' => 10, 'is_active' => true],
                ['code' => 'room-2', 'group' => 'room', 'title' => '2-комнатная', 'subtitle' => '50–80 м²', 'is_addon' => false, 'is_default' => false, 'price_modifier' => 600, 'sort_order' => 20, 'is_active' => true],
                ['code' => 'room-3', 'group' => 'room', 'title' => '3-комнатная', 'subtitle' => '80–120 м²', 'is_addon' => false, 'is_default' => false, 'price_modifier' => 1200, 'sort_order' => 30, 'is_active' => true],
                ['code' => 'support', 'group' => 'cleaning', 'title' => 'Поддерживающая', 'subtitle' => 'Регулярная уборка', 'is_addon' => false, 'is_default' => true, 'price_modifier' => 0, 'sort_order' => 10, 'is_active' => true],
                ['code' => 'deep', 'group' => 'cleaning', 'title' => 'Глубокая уборка', 'subtitle' => 'Усиленная проработка зон', 'is_addon' => false, 'is_default' => false, 'price_modifier' => 2500, 'sort_order' => 20, 'is_active' => true],
                ['code' => 'fridge-inside', 'group' => 'extra', 'title' => 'Холодильник внутри', 'subtitle' => 'Освобождённый и размороженный', 'is_addon' => true, 'is_default' => false, 'price_modifier' => 800, 'sort_order' => 10, 'is_active' => true],
                ['code' => 'oven-inside', 'group' => 'extra', 'title' => 'Духовка внутри', 'subtitle' => 'Без сильного нагара', 'is_addon' => true, 'is_default' => false, 'price_modifier' => 900, 'sort_order' => 20, 'is_active' => true],
            ],
        ];
    }
}
