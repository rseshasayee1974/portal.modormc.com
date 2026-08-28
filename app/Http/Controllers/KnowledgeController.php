<?php

namespace App\Http\Controllers;

use App\Models\DocumentChunk;
use App\Models\RagDocument;
use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class KnowledgeController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'knowledge';
    /**
     * List all indexed knowledge base documents.
     */
    public function index(Request $request)
    {
        $this->authorizeModule('menu');
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
        $this->authorizeModule('create');
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
        $this->authorizeModule('edit');
        $document->update(['is_active' => !$document->is_active]);

        return back()->with('success', 'Document status updated.');
    }

    /**
     * Re-generate the embedding for a document.
     */
    public function reEmbed(RagDocument $document, EmbeddingService $embeddingService)
    {
        $this->authorizeModule('edit');
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
        $this->authorizeModule('delete');
        $document->delete();

        return back()->with('success', 'Document removed from knowledge base.');
    }

    /**
     * Upload a PDF or DOCX file, extract text, chunk it, and index into RAG.
     * Used by POST /api/ai/knowledge/upload
     */
    public function uploadFile(Request $request, EmbeddingService $embeddingService): JsonResponse
    {
        $request->validate([
            'file'  => 'required|file|mimes:pdf,docx,txt,doc|max:20480', // 20 MB max
            'title' => 'nullable|string|max:255',
        ]);

        $file      = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $title     = $request->input('title', $file->getClientOriginalName());
        $entityId  = $this->getEntityId();

        // Extract raw text from the uploaded file
        try {
            $rawText = match ($extension) {
                'pdf'  => $this->extractPdfText($file->getRealPath()),
                'docx' => $this->extractDocxText($file->getRealPath()),
                'txt'  => file_get_contents($file->getRealPath()),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error('KnowledgeController: file extraction failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Could not extract text from file: ' . $e->getMessage()], 422);
        }

        if (empty(trim($rawText ?? ''))) {
            return response()->json(['success' => false, 'error' => 'No readable text found in the uploaded file.'], 422);
        }

        $hash = hash('sha256', $rawText);

        if (RagDocument::where('content_hash', $hash)->exists()) {
            return response()->json(['success' => false, 'error' => 'This document has already been indexed.'], 422);
        }

        // Store the original file
        $storedPath = $file->store('knowledge/uploads', 'local');

        // Create the parent RAG document record (store the first 8000 chars as preview)
        $ragDocument = RagDocument::create([
            'entity_id'    => $entityId,
            'source_type'  => 'file_upload',
            'source_id'    => null,
            'title'        => $title,
            'content'      => mb_substr($rawText, 0, 8000),
            'embedding'    => null,
            'content_hash' => $hash,
            'token_count'  => $embeddingService->estimateTokens($rawText),
            'is_active'    => true,
        ]);

        // Chunk the text (~500 tokens = ~2000 chars per chunk)
        $chunks     = $this->chunkText($rawText, 2000);
        $chunkCount = 0;

        foreach ($chunks as $index => $chunkText) {
            $chunkText = trim($chunkText);
            if (empty($chunkText)) continue;

            $chunkEmbedding = $embeddingService->embed($chunkText);

            DocumentChunk::create([
                'rag_document_id' => $ragDocument->id,
                'entity_id'       => $entityId,
                'chunk_index'     => $index,
                'content'         => $chunkText,
                'embedding'       => empty($chunkEmbedding) ? null : json_encode($chunkEmbedding),
                'content_hash'    => hash('sha256', $chunkText),
                'token_count'     => $embeddingService->estimateTokens($chunkText),
                'is_active'       => true,
            ]);

            $chunkCount++;
        }

        return response()->json([
            'success'     => true,
            'message'     => "File indexed successfully into {$chunkCount} chunk(s).",
            'document_id' => $ragDocument->id,
            'chunks'      => $chunkCount,
        ]);
    }

    /**
     * Extract text from a PDF file using smalot/pdfparser.
     */
    private function extractPdfText(string $filePath): string
    {
        $parser   = new \Smalot\PdfParser\Parser();
        $pdf      = $parser->parseFile($filePath);
        return $pdf->getText();
    }

    /**
     * Extract text from a DOCX file using phpoffice/phpword.
     */
    private function extractDocxText(string $filePath): string
    {
        if (!class_exists('\PhpOffice\PhpWord\IOFactory')) {
            throw new \RuntimeException('DOCX text extraction requires the phpoffice/phpword package. Please run: composer require phpoffice/phpword');
        }

        $phpWord  = \PhpOffice\PhpWord\IOFactory::load($filePath);
        $sections = $phpWord->getSections();
        $text     = '';

        foreach ($sections as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                } elseif ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                    foreach ($element->getRows() as $row) {
                        foreach ($row->getCells() as $cell) {
                            foreach ($cell->getElements() as $cellEl) {
                                if (method_exists($cellEl, 'getText')) {
                                    $text .= $cellEl->getText() . ' ';
                                }
                            }
                        }
                        $text .= "\n";
                    }
                }
            }
        }

        return $text;
    }

    /**
     * Split text into chunks of approximately $maxChars characters,
     * trying to break at sentence or paragraph boundaries.
     */
    private function chunkText(string $text, int $maxChars = 2000): array
    {
        if (mb_strlen($text) <= $maxChars) {
            return [$text];
        }

        $chunks    = [];
        $paragraphs = preg_split('/\n{2,}/', $text);
        $current    = '';

        foreach ($paragraphs as $paragraph) {
            if (mb_strlen($current . "\n\n" . $paragraph) > $maxChars) {
                if ($current !== '') {
                    $chunks[] = trim($current);
                }
                // If the paragraph itself is too large, split by sentences
                if (mb_strlen($paragraph) > $maxChars) {
                    $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph);
                    $sentChunk = '';
                    foreach ($sentences as $sentence) {
                        if (mb_strlen($sentChunk . ' ' . $sentence) > $maxChars) {
                            if ($sentChunk !== '') $chunks[] = trim($sentChunk);
                            $sentChunk = $sentence;
                        } else {
                            $sentChunk .= ($sentChunk ? ' ' : '') . $sentence;
                        }
                    }
                    $current = $sentChunk;
                } else {
                    $current = $paragraph;
                }
            } else {
                $current .= ($current ? "\n\n" : '') . $paragraph;
            }
        }

        if ($current !== '') {
            $chunks[] = trim($current);
        }

        return array_filter($chunks);
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
