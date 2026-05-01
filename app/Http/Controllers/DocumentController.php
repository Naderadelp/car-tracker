<?php

namespace App\Http\Controllers;

use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Requests\Document\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Vehicle;
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

    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('viewAny', [Document::class, $vehicle]);

        $documents = $this->documentRepository
            ->where('vehicle_id', $vehicle->id)
            ->spatie()
            ->paginate();

        return $this->paginated($documents, DocumentResource::class);
    }

    public function store(StoreDocumentRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('create', $vehicle);

        try {
            DB::beginTransaction();

            $document = $this->documentRepository->create([
                'user_id'     => auth()->id(),
                'vehicle_id'  => $vehicle->id,
                'type'        => $request->type,
                'expiry_date' => $request->expiry_date,
            ]);

            $document->addMediaFromRequest('document_file')
                     ->toMediaCollection('vehicle_documents');

            DB::commit();

            return $this->success(new DocumentResource($document), 201, 'Document uploaded successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }
    }

    public function update(UpdateDocumentRequest $request, Vehicle $vehicle, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        abort_if($document->vehicle_id !== $vehicle->id, 404);

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

    public function show(Request $request, Vehicle $vehicle, Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_if($document->vehicle_id !== $vehicle->id, 404, 'No media file found for this document.');

        $media = $document->getFirstMedia('vehicle_documents');

        abort_if($media === null, 404, 'No media file found for this document.');

        return response()->streamDownload(
            fn () => print(file_get_contents($media->getPath())),
            $media->file_name,
            ['Content-Type' => $media->mime_type]
        );
    }

    public function destroy(Request $request, Vehicle $vehicle, Document $document): JsonResponse
    {
        $this->authorize('delete', $document);

        abort_if($document->vehicle_id !== $vehicle->id, 404);

        $this->documentRepository->delete($document->id);

        return $this->success([], 200, 'Document deleted successfully.');
    }
}
