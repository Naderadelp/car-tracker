<?php

namespace App\Http\Controllers;

use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Requests\Document\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Car;
use App\Models\Document;
use App\Repositories\Contracts\DocumentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends BaseController
{
    public function __construct(
        protected DocumentRepository $documentRepository,
    ) {}

    public function index(Request $request, Car $car): JsonResponse
    {
        $this->authorize('viewAny', [Document::class, $car]);

        $documents = $this->documentRepository
            ->where('car_id', $car->id)
            ->spatie()
            ->paginate($this->perPage());

        return $this->paginated($documents, DocumentResource::class);
    }

    public function store(StoreDocumentRequest $request, Car $car): JsonResponse
    {
        try {
            DB::beginTransaction();

            $document = $this->documentRepository->create([
                'user_id'     => auth()->id(),
                'car_id'  => $car->id,
                'type'        => $request->type,
                'expiry_date' => $request->expiry_date,
            ]);

            // FR-006: a document is now metadata-first. The scan is optional at
            // creation and can be attached later through update() (FR-008).
            if ($request->hasFile('document_file')) {
                $document->addMediaFromRequest('document_file')
                         ->toMediaCollection('vehicle_documents');
            }

            DB::commit();

            return $this->success(new DocumentResource($document), 201, 'Document saved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }
    }

    public function update(UpdateDocumentRequest $request, Car $car, Document $document): JsonResponse
    {
        abort_if($document->car_id !== $car->id, 404);

        try {
            DB::beginTransaction();

            $this->documentRepository->update(
                $request->only(['type', 'expiry_date']),
                $document->id
            );

            if ($request->hasFile('document_file')) {
                $document->refresh();
                $document->clearMediaCollection('vehicle_documents');
                $document->addMediaFromRequest('document_file')
                         ->toMediaCollection('vehicle_documents');
            }

            DB::commit();

            return $this->success(new DocumentResource($document->refresh()), 200, 'Document updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }
    }

    public function show(Request $request, Car $car, Document $document): StreamedResponse
    {
        $this->authorize('secureDownload', $document);

        abort_if($document->car_id !== $car->id, 404, 'No media file found for this document.');

        $media = $document->getFirstMedia('vehicle_documents');

        abort_if($media === null, 404, 'No media file found for this document.');

        return response()->streamDownload(
            fn () => print(file_get_contents($media->getPath())),
            $media->file_name,
            ['Content-Type' => $media->mime_type]
        );
    }

    public function destroy(Request $request, Car $car, Document $document): JsonResponse
    {
        $this->authorize('delete', $document);

        abort_if($document->car_id !== $car->id, 404);

        $this->documentRepository->delete($document->id);

        return $this->success([], 200, 'Document deleted successfully.');
    }
}
