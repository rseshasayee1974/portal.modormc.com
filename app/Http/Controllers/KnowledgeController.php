<?php

namespace App\Http\Controllers;

use App\Models\RagDocument;
use App\Services\EmbeddingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class KnowledgeController extends Controller
{
    /**
     * List all indexed knowledge base documents.
     */
    public function index(Request $request)
    {
        $entityId = $this->getEntityId();

        $documents = RagDocument::forEntity($entityId)
            ->orderByDesc('created_at')
            ->select('id', 'title', 'source_type', 'source_id', 'is_active', 'token_count', 'created_at')
            ->get();

        $sourceTypes = RagDocument::forEntity($entityId)
            ->distinct()
            ->pluck('source_type');

        return Inertia::render('Knowledge/Index', [
            'documents'   => $documents,
            'sourceTypes' => $sourceTypes,
        ]);
    }

    /**
     * Store a new knowledge document and generate its embedding.
     */
    public function store(Request $request, EmbeddingService $embeddingService)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'source_type' => 'required|string|max:100',
            'content'     => 'required|string|min:20',
        ]);

        $content = trim($request->content);
        $hash    = hash('sha256', $content);
        $entityId = $this->getEntityId();

        // Prevent duplicate content
        if (RagDocument::where('content_hash', $hash)->exists()) {
            return back()->with('warning', 'This document content has already been indexed.');
        }

        // Generate embedding
        $embedding = $embeddingService->embed($content);

        RagDocument::create([
            'entity_id'    => $entityId,
            'source_type'  => $request->source_type,
            'source_id'    => $request->source_id,
            'title'        => $request->title,
            'content'      => $content,
            'embedding'    => empty($embedding) ? null : json_encode($embedding),
            'content_hash' => $hash,
            'token_count'  => $embeddingService->estimateTokens($content),
            'is_active'    => true,
        ]);

        return back()->with('success', 'Document indexed successfully into the knowledge base.');
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleActive(RagDocument $document)
    {
        $document->update(['is_active' => !$document->is_active]);

        return back()->with('success', 'Document status updated.');
    }

    /**
     * Re-generate the embedding for a document.
     */
    public function reEmbed(RagDocument $document, EmbeddingService $embeddingService)
    {
        $embedding = $embeddingService->embed($document->content);

        if (empty($embedding)) {
            return back()->with('error', 'Failed to generate embedding. Check OpenAI API key.');
        }

        $document->update([
            'embedding'   => json_encode($embedding),
            'token_count' => $embeddingService->estimateTokens($document->content),
        ]);

        return back()->with('success', 'Embedding regenerated successfully.');
    }

    /**
     * Delete a knowledge document.
     */
    public function destroy(RagDocument $document)
    {
        $document->delete();

        return back()->with('success', 'Document removed from knowledge base.');
    }

    /**
     * Resolve the entity ID for the authenticated user.
     */
    private function getEntityId(): ?int
    {
        $user       = Auth::user();
        $entityUser = \App\Models\EntityUser::where('user_id', $user->id)->first();

        return $entityUser?->entity_id;
    }
}
