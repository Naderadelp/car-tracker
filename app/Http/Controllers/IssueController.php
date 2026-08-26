<?php

namespace App\Http\Controllers;

use App\Http\Requests\Issue\StoreIssueRequest;
use App\Http\Requests\Issue\UpdateIssueRequest;
use App\Http\Resources\IssueResource;
use App\Models\Car;
use App\Models\Issue;
use App\Repositories\Contracts\IssueRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gap B5 — the photo-first fault log.
 *
 * Media handling mirrors DocumentController: a single file on the private
 * `local` disk, served as a StreamedResponse. Principle III's only sanctioned
 * exception to returning JSON.
 */
class IssueController extends BaseController
{
    public function __construct(
        protected IssueRepository $issueRepository,
    ) {}

    public function index(Request $request, Car $car): JsonResponse
    {
        $this->authorize('viewAny', [Issue::class, $car]);

        $issues = $this->issueRepository
            ->where('car_id', $car->id)
            ->spatie()
            ->paginate();

        return $this->paginated($issues, IssueResource::class);
    }

    public function store(StoreIssueRequest $request, Car $car): JsonResponse
    {
        try {
            DB::beginTransaction();

            $issue = $this->issueRepository->create([
                ...$request->safe()->except('photo'),
                'car_id'  => $car->id,
                'user_id' => auth()->id(),
            ]);

            if ($request->hasFile('photo')) {
                $issue->addMediaFromRequest('photo')
                      ->toMediaCollection(Issue::PHOTO_COLLECTION);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }

        return $this->success(new IssueResource($issue->refresh()), 201, 'Issue recorded successfully.');
    }

    public function show(Request $request, Car $car, Issue $issue): JsonResponse
    {
        $this->authorize('view', $issue);

        abort_if($issue->car_id !== $car->id, 404);

        return $this->success(new IssueResource($issue));
    }

    public function update(UpdateIssueRequest $request, Car $car, Issue $issue): JsonResponse
    {
        abort_if($issue->car_id !== $car->id, 404);

        $attributes = $request->safe()->except(['photo', 'resolved']);

        // FR-020: the client ticks a box; resolved_at is the stored truth, so
        // the two can never disagree.
        if ($request->has('resolved')) {
            $attributes['resolved_at'] = $request->boolean('resolved') ? now() : null;
        }

        try {
            DB::beginTransaction();

            $issue = $this->issueRepository->update($attributes, $issue->id);

            if ($request->hasFile('photo')) {
                $issue->clearMediaCollection(Issue::PHOTO_COLLECTION);
                $issue->addMediaFromRequest('photo')
                      ->toMediaCollection(Issue::PHOTO_COLLECTION);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }

        return $this->success(new IssueResource($issue->refresh()), 200, 'Issue updated successfully.');
    }

    public function destroy(Request $request, Car $car, Issue $issue): JsonResponse
    {
        $this->authorize('delete', $issue);

        abort_if($issue->car_id !== $car->id, 404);

        $issue->clearMediaCollection(Issue::PHOTO_COLLECTION);
        $this->issueRepository->delete($issue->id);

        return $this->success([], 200, 'Issue deleted successfully.');
    }

    public function photo(Request $request, Car $car, Issue $issue): StreamedResponse
    {
        $this->authorize('secureDownload', $issue);

        abort_if($issue->car_id !== $car->id, 404, 'No photo found for this issue.');

        $media = $issue->getFirstMedia(Issue::PHOTO_COLLECTION);

        abort_if($media === null, 404, 'No photo found for this issue.');

        return response()->streamDownload(
            fn () => print(file_get_contents($media->getPath())),
            $media->file_name,
            ['Content-Type' => $media->mime_type]
        );
    }
}
