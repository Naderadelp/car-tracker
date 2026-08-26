<?php

namespace App\Http\Controllers;

use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Car;
use App\Models\Service;
use App\Repositories\Contracts\ServiceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceController extends BaseController
{
    public function __construct(
        protected ServiceRepository $serviceRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $services = $this->serviceRepository->spatie()->paginate();

        return $this->paginated($services, ServiceResource::class);
    }

    public function show(Request $request, Service $service): JsonResponse
    {
        $this->authorize('view', $service);

        return $this->success(new ServiceResource($service));
    }

    public function store(StoreServiceRequest $request, Car $car): JsonResponse
    {
        try {
            DB::beginTransaction();

            $service = $this->serviceRepository->create([
                ...$request->safe()->except('items'),
                'car_id'  => $car->id,
                'user_id' => auth()->id(),
            ]);

            // Gap F3 — the driver's own checklist lines.
            $this->syncChecklist($service, $request->input('items', []), $car->id);

            DB::commit();

            return $this->success(
                new ServiceResource($service->load('checklist.item')),
                201,
                'Custom service created.',
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }
    }

    public function storeCatalogue(StoreServiceRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $service = $this->serviceRepository->create($request->validated());

            DB::commit();

            return $this->success(
                new ServiceResource($service),
                201,
                'Catalogue service created.',
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        try {
            DB::beginTransaction();

            $updated = $this->serviceRepository->update($request->validated(), $service->id);

            DB::commit();

            return $this->success(new ServiceResource($updated));
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }
    }

    public function destroy(Request $request, Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $this->serviceRepository->delete($service->id);

        return $this->success([], 200, 'Service deleted.');
    }

    /**
     * Replace an interval's checklist with the lines supplied (gap F3).
     *
     * A line either links a catalogue Item by id or carries its own name and
     * price. Driver-added lines are never written to `items`, which is
     * admin-managed and has a globally unique name — one driver's "Oil filter"
     * would otherwise collide with another's.
     *
     * @param  array<int, array{item_id?: int|null, name?: string|null, price?: float|null}>  $lines
     */
    private function syncChecklist(Service $service, array $lines, ?int $carId): void
    {
        if ($lines === []) {
            return;
        }

        $service->checklist()->delete();

        foreach ($lines as $line) {
            $service->checklist()->create([
                'item_id' => $line['item_id'] ?? null,
                'car_id'  => $carId,
                'name'    => $line['name'] ?? null,
                'price'   => $line['price'] ?? null,
            ]);
        }
    }
}
