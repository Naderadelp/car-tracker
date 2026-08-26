<?php

namespace Tests\Feature\Issue;

use App\Models\Car;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gap B5 — FR-018 through FR-021.
 */
class IssueCrudTest extends TestCase
{
    use RefreshDatabase;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        $this->car = Car::factory()->create();
        Sanctum::actingAs(User::find($this->car->user_id));
    }

    private function record(array $overrides = []): int
    {
        return $this->postJson("/api/cars/{$this->car->id}/issues", array_merge([
            'occurred_at' => now()->toDateString(),
            'title'       => 'Brakes squealing',
            'severity'    => 'high',
            'summary'     => 'Loud squeal under braking at low speed.',
        ], $overrides))->assertCreated()->json('data.id');
    }

    public function test_a_driver_can_record_a_fault(): void
    {
        $this->postJson("/api/cars/{$this->car->id}/issues", [
            'occurred_at' => now()->toDateString(),
            'title'       => 'Brakes squealing',
            'severity'    => 'high',
            'summary'     => 'Loud squeal under braking.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Brakes squealing')
            ->assertJsonPath('data.severity', 'high')
            ->assertJsonPath('data.resolved', false)
            ->assertJsonPath('data.has_photo', false);
    }

    /**
     * A fault is often recorded from the roadside with nothing but a title and
     * a severity — demanding a description would push the driver to skip the
     * record entirely.
     */
    public function test_a_fault_can_be_recorded_with_no_description(): void
    {
        $this->postJson("/api/cars/{$this->car->id}/issues", [
            'occurred_at' => now()->toDateString(),
            'title'       => 'Rattle from the boot',
            'severity'    => 'low',
        ])->assertCreated();
    }

    public function test_the_fault_log_lists_a_cars_faults(): void
    {
        $this->record();
        $this->record(['title' => 'Warning light', 'severity' => 'medium']);

        $this->getJson("/api/cars/{$this->car->id}/issues")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_a_driver_can_resolve_a_fault(): void
    {
        $id = $this->record();

        $this->putJson("/api/cars/{$this->car->id}/issues/{$id}", [
            'resolved' => true,
            'solution' => 'Replaced front pads.',
        ])
            ->assertOk()
            ->assertJsonPath('data.resolved', true)
            ->assertJsonPath('data.solution', 'Replaced front pads.');

        $this->assertNotNull(Issue::find($id)->resolved_at);
    }

    public function test_a_resolved_fault_can_be_reopened(): void
    {
        $id = $this->record();

        $this->putJson("/api/cars/{$this->car->id}/issues/{$id}", ['resolved' => true])->assertOk();
        $this->putJson("/api/cars/{$this->car->id}/issues/{$id}", ['resolved' => false])
            ->assertOk()
            ->assertJsonPath('data.resolved', false);

        $this->assertNull(Issue::find($id)->resolved_at);
    }

    public function test_a_driver_can_delete_a_fault(): void
    {
        $id = $this->record();

        $this->deleteJson("/api/cars/{$this->car->id}/issues/{$id}")->assertOk();

        $this->assertDatabaseMissing('issues', ['id' => $id]);
    }

    public function test_an_unknown_severity_is_rejected(): void
    {
        $this->postJson("/api/cars/{$this->car->id}/issues", [
            'occurred_at' => now()->toDateString(),
            'title'       => 'Something',
            'severity'    => 'catastrophic',
        ])->assertStatus(422);
    }

    public function test_the_log_can_be_filtered_by_severity(): void
    {
        $this->record(['severity' => 'high']);
        $this->record(['severity' => 'low', 'title' => 'Rattle']);

        $data = $this->getJson("/api/cars/{$this->car->id}/issues?filter[severity]=high")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('high', $data[0]['severity']);
    }

    public function test_a_photo_can_be_attached_and_retrieved(): void
    {
        Storage::fake('local');

        $id = $this->record();

        $this->put(
            "/api/cars/{$this->car->id}/issues/{$id}",
            ['photo' => UploadedFile::fake()->image('fault.jpg')],
            ['Accept' => 'application/json']
        )->assertOk()->assertJsonPath('data.has_photo', true);

        $this->get("/api/cars/{$this->car->id}/issues/{$id}/photo")->assertOk();
    }

    public function test_requesting_a_photo_that_does_not_exist_is_a_404(): void
    {
        $id = $this->record();

        $this->getJson("/api/cars/{$this->car->id}/issues/{$id}/photo")->assertNotFound();
    }

    public function test_an_oversized_photo_is_rejected(): void
    {
        $id = $this->record();

        $this->put(
            "/api/cars/{$this->car->id}/issues/{$id}",
            ['photo' => UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg')],
            ['Accept' => 'application/json']
        )->assertStatus(422);
    }
}
