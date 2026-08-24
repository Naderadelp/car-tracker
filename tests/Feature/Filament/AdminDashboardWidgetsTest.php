<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\CarsPerBrandChart;
use App\Filament\Widgets\DueRemindersTable;
use App\Filament\Widgets\FleetGrowthChart;
use App\Filament\Widgets\FleetOverviewStats;
use App\Filament\Widgets\FuelGradeSplitChart;
use App\Filament\Widgets\FuelSpendChart;
use App\Filament\Widgets\LapsingDocumentsTable;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarModel;
use App\Models\Document;
use App\Models\FillUp;
use App\Models\Permission;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\RolePermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the dashboard widgets.
 *
 * Two things need pinning down. First, the widgets are lazy — a plain
 * `GET /admin` renders placeholders, so hitting the route proves nothing about
 * whether a chart can actually build its data. Livewire::test() forces the real
 * render.
 *
 * Second, the aggregates group rows by date through a database function, and
 * the driver here (sqlite) is not the driver in production (Postgres). Running
 * every chart over real rows is what stops App\Filament\Support\TimeSeries from
 * silently regressing to a Postgres-only implementation.
 */
class AdminDashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every dashboard widget, with the permission it is gated on.
     *
     * @return array<class-string, string>
     */
    private const WIDGETS = [
        FleetOverviewStats::class => 'index-car',
        FuelSpendChart::class => 'index-fill-up',
        FleetGrowthChart::class => 'index-user',
        FuelGradeSplitChart::class => 'index-fill-up',
        CarsPerBrandChart::class => 'index-car',
        LapsingDocumentsTable::class => 'index-document',
        DueRemindersTable::class => 'index-reminder',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionsSeeder::class);

        Filament::setCurrentPanel('admin');
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user->fresh();
    }

    /**
     * A user whose role carries only the permissions named.
     *
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'limited-'.uniqid(), 'guard_name' => 'api']);
        $role->givePermissionTo(Permission::whereIn('name', $permissions)->get());

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }

    /**
     * Enough rows, spread across enough months, for every bucket to have
     * something to find and for at least one to stay empty.
     */
    private function seedFleet(): void
    {
        $owner = User::factory()->create(['created_at' => now()->subMonths(3)]);
        $brand = Brand::factory()->create(['name' => 'Toyota']);
        $otherBrand = Brand::factory()->create(['name' => 'Nissan']);
        $carModel = CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2022]);

        $car = Car::create([
            'user_id' => $owner->id,
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'current_km' => 42_000,
            'tank_size' => 50,
            'created_at' => now()->subMonths(3),
        ]);

        Car::create([
            'user_id' => $owner->id,
            'brand_id' => $otherBrand->id,
            'current_km' => 1_000,
            'created_at' => now()->subMonth(),
        ]);

        // Fill-ups across three months and all three fuel grades, plus one
        // inside the last seven days so the short filters are not all zero.
        foreach ([['92', 0], ['95', 1], ['electric', 2], ['92', 0]] as $index => [$grade, $monthsAgo]) {
            FillUp::create([
                'car_id' => $car->id,
                'liters' => 40 + $index,
                'odometer' => 40_000 + ($index * 500),
                'cost_egp' => 800 + ($index * 10),
                'fill_date' => now()->subMonths($monthsAgo)->subDays($monthsAgo === 0 ? 1 : 0)->toDateString(),
                'fuel_type' => $grade,
            ]);
        }

        Trip::create([
            'car_id' => $car->id,
            'start_lat' => 30.0,
            'start_lng' => 31.0,
            'end_lat' => 30.1,
            'end_lng' => 31.1,
            'total_distance_km' => 18.5,
        ]);

        // One already expired, one lapsing inside the horizon, one far off.
        foreach ([-5, 10, 400] as $offset) {
            Document::create([
                'user_id' => $owner->id,
                'car_id' => $car->id,
                'type' => 'vehicle_license',
                'expiry_date' => now()->addDays($offset)->toDateString(),
            ]);
        }

        Reminder::create([
            'car_id' => $car->id,
            'title' => 'Oil change',
            'remind_on' => now()->subDays(2)->toDateString(),
            'remind_at_km' => 45_000,
        ]);

        Reminder::create([
            'car_id' => $car->id,
            'title' => 'Already handled',
            'remind_on' => now()->subDays(3)->toDateString(),
            'notified_at' => now()->subDay(),
        ]);
    }

    public function test_every_widget_renders_for_an_admin(): void
    {
        $this->seedFleet();
        $this->actingAs($this->admin());

        foreach (array_keys(self::WIDGETS) as $widget) {
            Livewire::test($widget)->assertOk();
        }
    }

    public function test_every_widget_renders_when_there_is_no_data_at_all(): void
    {
        // An empty database is the state a fresh deployment is in, and an
        // aggregate that divides by last month's total is exactly the kind of
        // code that only breaks there.
        $this->actingAs($this->admin());

        foreach (array_keys(self::WIDGETS) as $widget) {
            Livewire::test($widget)->assertOk();
        }
    }

    public function test_every_chart_filter_produces_a_gapless_series(): void
    {
        $this->seedFleet();
        $this->actingAs($this->admin());

        $expectedLabelCounts = [
            FuelSpendChart::class => ['7d' => 7, '30d' => 30, '12m' => 12],
            FleetGrowthChart::class => ['30d' => 30, '12m' => 12, '24m' => 24],
        ];

        foreach ($expectedLabelCounts as $widget => $filters) {
            foreach ($filters as $filter => $expectedLabels) {
                $component = Livewire::test($widget)->set('filter', $filter)->assertOk();

                $data = $this->chartData($component->instance());

                $this->assertCount(
                    $expectedLabels,
                    $data['labels'],
                    "[{$widget}] filter [{$filter}] should span {$expectedLabels} buckets.",
                );

                foreach ($data['datasets'] as $dataset) {
                    // A month with no fill-ups is a zero, not a missing point.
                    $this->assertCount(
                        $expectedLabels,
                        $dataset['data'],
                        "[{$widget}] filter [{$filter}] dataset [{$dataset['label']}] has a gap.",
                    );
                }
            }
        }
    }

    public function test_the_fuel_grade_chart_totals_every_fill_up(): void
    {
        $this->seedFleet();
        $this->actingAs($this->admin());

        $data = $this->chartData(Livewire::test(FuelGradeSplitChart::class)->assertOk()->instance());

        $this->assertSame(
            FillUp::count(),
            array_sum($data['datasets'][0]['data']),
            'Every fill-up should land in exactly one grade slice.',
        );

        // The grades keep a fixed order and fixed colours so a window with no
        // 95 in it does not recolour the other slices.
        $this->assertSame(['92 octane', '95 octane', 'Electric'], $data['labels']);
    }

    public function test_the_brand_chart_excludes_soft_deleted_cars(): void
    {
        $this->seedFleet();
        $this->actingAs($this->admin());

        $before = array_sum($this->chartData(
            Livewire::test(CarsPerBrandChart::class)->instance()
        )['datasets'][0]['data']);

        Car::query()->firstOrFail()->delete();

        $after = array_sum($this->chartData(
            Livewire::test(CarsPerBrandChart::class)->instance()
        )['datasets'][0]['data']);

        $this->assertSame($before - 1, $after);
    }

    public function test_the_lapsing_documents_widget_lists_only_what_needs_chasing(): void
    {
        $this->seedFleet();
        $this->actingAs($this->admin());

        Livewire::test(LapsingDocumentsTable::class)
            ->assertOk()
            // Expired five days ago, and lapsing in ten.
            ->assertSee('5 days ago')
            ->assertSee('10 days left')
            // Over a year out, so not this widget's problem.
            ->assertDontSee('400 days left');
    }

    public function test_the_reminders_widget_ignores_reminders_that_already_went_out(): void
    {
        $this->seedFleet();
        $this->actingAs($this->admin());

        Livewire::test(DueRemindersTable::class)
            ->assertOk()
            ->assertSee('Oil change')
            ->assertDontSee('Already handled');
    }

    public function test_a_widget_is_hidden_without_its_permission(): void
    {
        $this->actingAs($this->userWithPermissions(['index-car']));

        $this->assertTrue(FleetOverviewStats::canView());
        $this->assertTrue(CarsPerBrandChart::canView());

        $this->assertFalse(FuelSpendChart::canView());
        $this->assertFalse(FuelGradeSplitChart::canView());
        $this->assertFalse(FleetGrowthChart::canView());
        $this->assertFalse(LapsingDocumentsTable::canView());
        $this->assertFalse(DueRemindersTable::canView());
    }

    public function test_no_widget_is_visible_to_a_user_with_no_permissions(): void
    {
        $this->actingAs($this->userWithPermissions([]));

        foreach (array_keys(self::WIDGETS) as $widget) {
            $this->assertFalse($widget::canView(), "[{$widget}] should be hidden.");
        }
    }

    public function test_no_widget_is_visible_to_a_guest(): void
    {
        foreach (array_keys(self::WIDGETS) as $widget) {
            $this->assertFalse($widget::canView(), "[{$widget}] should be hidden from guests.");
        }
    }

    public function test_the_stats_widget_drops_readings_the_user_may_not_see(): void
    {
        $this->seedFleet();

        // index-car alone: the fleet reading, and nothing that would leak the
        // size of the driver base or what it spends.
        $this->actingAs($this->userWithPermissions(['index-car']));

        $labels = $this->statLabels();

        $this->assertSame(['Cars registered'], $labels);

        // Widen it, and the readings that permission unlocks appear.
        $this->actingAs($this->userWithPermissions(['index-car', 'index-user', 'index-fill-up']));

        $this->assertSame(
            ['Drivers', 'Cars registered', 'Fill-ups this month', 'Litres pumped', 'Fuel spend'],
            $this->statLabels(),
        );
    }

    public function test_the_growth_chart_drops_the_car_series_without_index_car(): void
    {
        $this->seedFleet();
        $this->actingAs($this->userWithPermissions(['index-user']));

        $data = $this->chartData(Livewire::test(FleetGrowthChart::class)->assertOk()->instance());

        $this->assertSame(['Drivers'], array_column($data['datasets'], 'label'));
    }

    /**
     * The stat labels the current user actually gets.
     *
     * @return list<string>
     */
    private function statLabels(): array
    {
        $widget = Livewire::test(FleetOverviewStats::class)->assertOk()->instance();

        $method = new \ReflectionMethod($widget, 'getCachedStats');
        $method->setAccessible(true);

        return array_map(
            fn ($stat): string => (string) $stat->getLabel(),
            $method->invoke($widget),
        );
    }

    /**
     * A rendered chart widget's Chart.js payload.
     *
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function chartData(object $widget): array
    {
        $method = new \ReflectionMethod($widget, 'getCachedData');
        $method->setAccessible(true);

        return $method->invoke($widget);
    }
}
