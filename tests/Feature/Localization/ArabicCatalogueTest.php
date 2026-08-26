<?php

namespace Tests\Feature\Localization;

use App\Models\Brand;
use App\Models\Item;
use App\Models\ServiceCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gap F6 — the app ships a full RTL Arabic build and the payloads were Latin
 * only, so the Arabic build silently degraded to English catalogue names.
 *
 * Both variants ship together rather than being resolved from Accept-Language,
 * because the client caches both and switches locale without a refetch.
 */
class ArabicCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);
    }

    public function test_an_item_returns_both_language_variants(): void
    {
        Item::create(['name' => 'Oil filter', 'name_ar' => 'فلتر زيت', 'price' => 350]);

        $data = $this->getJson('/api/items')->assertOk()->json('data');

        $this->assertSame('Oil filter', $data[0]['name']);
        $this->assertSame('فلتر زيت',   $data[0]['name_ar']);
    }

    /** FR-030 — never a blank where no Arabic variant was recorded. */
    public function test_an_item_with_no_arabic_variant_falls_back_to_the_latin_name(): void
    {
        Item::create(['name' => 'Oil filter', 'price' => 350]);

        $data = $this->getJson('/api/items')->assertOk()->json('data');

        $this->assertSame('Oil filter', $data[0]['name_ar']);
    }

    public function test_an_arabic_name_can_be_recorded_through_the_api(): void
    {
        $this->postJson('/api/items', [
            'name'    => 'Brake pads',
            'name_ar' => 'تيل فرامل',
            'price'   => 900,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name_ar', 'تيل فرامل');
    }

    /**
     * `items.name` is unique but `name_ar` deliberately is not — two catalogue
     * entries may legitimately share an Arabic name.
     */
    public function test_two_items_may_share_an_arabic_name(): void
    {
        $this->postJson('/api/items', ['name' => 'Front pads', 'name_ar' => 'تيل فرامل', 'price' => 900])
            ->assertCreated();

        $this->postJson('/api/items', ['name' => 'Rear pads', 'name_ar' => 'تيل فرامل', 'price' => 800])
            ->assertCreated();
    }

    public function test_a_service_centre_returns_arabic_name_and_address(): void
    {
        $brand = Brand::factory()->create();

        ServiceCenter::create([
            'brand_id'   => $brand->id,
            'name'       => 'El Nasr Auto',
            'name_ar'    => 'النصر أوتو',
            'address'    => '12 Gameat El Dowal, Mohandessin',
            'address_ar' => '١٢ جامعة الدول، المهندسين',
            'open_at'    => '09:00',
            'close_at'   => '18:00',
            'mobile'     => '01000000000',
            'lat'        => 30.0561,
            'lng'        => 31.2394,
        ]);

        $data = $this->getJson("/api/brands/{$brand->id}/service-centers?lat=30.05&lng=31.23")->assertOk()->json('data');

        $this->assertSame('النصر أوتو',              $data[0]['name_ar']);
        $this->assertSame('١٢ جامعة الدول، المهندسين', $data[0]['address_ar']);
    }

    public function test_a_service_centre_with_no_arabic_falls_back_to_latin(): void
    {
        $brand = Brand::factory()->create();

        ServiceCenter::create([
            'brand_id' => $brand->id,
            'name'     => 'El Nasr Auto',
            'address'  => '12 Gameat El Dowal, Mohandessin',
            'open_at'  => '09:00',
            'close_at' => '18:00',
            'mobile'   => '01000000000',
            'lat'      => 30.0561,
            'lng'      => 31.2394,
        ]);

        $data = $this->getJson("/api/brands/{$brand->id}/service-centers?lat=30.05&lng=31.23")->assertOk()->json('data');

        $this->assertSame('El Nasr Auto', $data[0]['name_ar']);
        $this->assertSame('12 Gameat El Dowal, Mohandessin', $data[0]['address_ar']);
    }
}
