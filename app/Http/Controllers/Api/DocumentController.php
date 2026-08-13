<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    use AuthorizesOwnership;

    public function index(Request $request)
    {
        return response()->json(
            $request->user()->documents()->with('property')->latest()->get()
        );
    }

    /**
     * Spec section 32 (coffre-fort numérique). Stored on the private "local" disk and
     * served through the authenticated download() action below — never a public URL,
     * since these are meant to be a vault, not publicly linkable files.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'property_id' => ['nullable', 'exists:properties,id'],
            'category' => ['required', 'in:contrats,quittances,documents_locataires,documents_immobiliers,factures,autres'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        if (! empty($data['property_id'])) {
            $property = \App\Models\Property::findOrFail($data['property_id']);
            $this->authorizeOwner($request, $property);
        }

        $file = $data['file'];
        $path = $file->store('documents/' . $request->user()->id, 'local');

        $document = $request->user()->documents()->create([
            'property_id' => $data['property_id'] ?? null,
            'category' => $data['category'],
            'name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json($document, 201);
    }

    public function update(Request $request, Document $document)
    {
        $this->authorizeOwner($request, $document);

        $data = $request->validate([
            'category' => ['required', 'in:contrats,quittances,documents_locataires,documents_immobiliers,factures,autres'],
        ]);

        $document->update($data);

        return response()->json($document->fresh());
    }

    public function download(Request $request, Document $document)
    {
        $this->authorizeOwner($request, $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->name);
    }

    public function destroy(Request $request, Document $document)
    {
        $this->authorizeOwner($request, $document);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return response()->json(null, 204);
    }
}
