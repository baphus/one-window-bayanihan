<?php

namespace App\Http\Controllers;

use App\Models\GeneratedDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        // Generate presigned URL (15-minute expiry)
        $url = Storage::disk('supabase')->temporaryUrl(
            $document->path,
            now()->addMinutes(15),
        );

        return redirect($url);
    }
}
