<?php

namespace App\Http\Controllers;

use App\Models\GeneratedDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentController extends Controller
{
    public function download(Request $request, string $generatedDocument)
    {
        $document = GeneratedDocument::findOrFail($generatedDocument);

        // Verify ownership
        if ($document->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this document.');
        }

        // Check status
        if ($document->isPending()) {
            return response()->json([
                'status' => 'pending',
                'message' => 'File is still being generated.',
            ], 202);
        }

        if ($document->isFailed()) {
            return response()->json([
                'status' => 'failed',
                'message' => $document->error_message ?? 'File generation failed.',
            ], 410);
        }

        if (! $document->path) {
            return response()->json([
                'status' => 'failed',
                'message' => 'This file is no longer available. Please generate the export again.',
            ], 410);
        }

        // Generate presigned URL (15-minute expiry). A misconfigured or
        // unreachable storage disk raises here; answer with the same shape as
        // the other failure branches rather than a bare 500.
        try {
            $url = Storage::disk('supabase')->temporaryUrl(
                $document->path,
                now()->addMinutes(15),
            );
        } catch (Throwable $e) {
            Log::error('Unable to build a download URL for a generated document.', [
                'generated_document_id' => $document->id,
                'path' => $document->path,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'unavailable',
                'message' => 'The file could not be retrieved from storage. Please try again or contact an administrator.',
            ], 503);
        }

        return redirect($url);
    }
}
