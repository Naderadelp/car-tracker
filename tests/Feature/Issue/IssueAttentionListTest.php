<?php

namespace Tests\Feature\Issue;

use App\Models\Car;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * FR-021 — serious unresolved faults are promoted onto the notifications
 * screen alongside overdue services.
 */
class IssueAttentionListTest extends TestCase
{
    use RefreshDatabase;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        $this->car = Car::factory()->create();
        Sanctum::actingAs(User::find($this->car->user_id));
    }

    private function fault(string $severity, ?string $resolvedAt = null): Issue
    {
        return Issue::create([
            'car_id'      => $this->car->id,
            'user_id'     => $this->car->user_id,
            'occurred_at' => now()->toDateString(),
            'title'       => ucfirst($severity).' fault',
            'severity'    => $severity,
            'resolved_at' => $resolvedAt,
        ]);
    }

    private function attention(): array
    {
        return $this->getJson('/api/home')->assertOk()->json('data.issues_needing_attention');
    }

    public function test_a_serious_unresolved_fault_needs_attention(): void
    {
        $this->fault('high');

        $this->assertCount(1, $this->attention());
    }

    public function test_a_less_serious_fault_does_not_need_attention(): void
    {
        $this->fault('low');
        $this->fault('medium');

        $this->assertCount(0, $this->attention());
    }

    public function test_a_resolved_serious_fault_leaves_the_attention_list(): void
    {
        $issue = $this->fault('high');

        $this->assertCount(1, $this->attention());

        $this->putJson("/api/cars/{$this->car->id}/issues/{$issue->id}", ['resolved' => true])
            ->assertOk();

        $this->assertCount(0, $this->attention());
    }

    public function test_the_attention_list_sits_alongside_upcoming_services(): void
    {
        $this->fault('high');

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['next_services', 'issues_needing_attention'],
            ]);
    }

    public function test_another_drivers_serious_fault_never_appears(): void
    {
        $otherCar = Car::factory()->create();

        Issue::create([
            'car_id'      => $otherCar->id,
            'user_id'     => $otherCar->user_id,
            'occurred_at' => now()->toDateString(),
            'title'       => 'Not mine',
            'severity'    => 'high',
        ]);

        $this->assertCount(0, $this->attention());
    }
}
