<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Database\Seeders\RolePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Smoke test: every generated resource's list and create page must render for
 * an admin. This is what catches a mistyped column, relationship or option
 * closure in the form/table definitions.
 */
class AdminResourcePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin->fresh());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function resourceSlugs(): array
    {
        return collect([
            'users',
            'cars',
            'brands',
            'car-models',
            'services',
            'items',
            'service-centers',
            'car-logs',
            'fill-ups',
            'documents',
            'reminders',
            'fuel-prices',
            'roles',
            'permissions',
        ])->mapWithKeys(fn (string $slug): array => [$slug => [$slug]])->all();
    }

    #[DataProvider('resourceSlugs')]
    public function test_the_list_page_renders(string $slug): void
    {
        $this->get("/admin/{$slug}")->assertSuccessful();
    }

    #[DataProvider('resourceSlugs')]
    public function test_the_create_page_renders(string $slug): void
    {
        $this->get("/admin/{$slug}/create")->assertSuccessful();
    }
}
