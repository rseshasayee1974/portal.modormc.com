<?php

namespace App\Console\Commands;

use App\Models\RagDocument;
use App\Services\EmbeddingService;
use Illuminate\Console\Command;

class RagIndexCommand extends Command
{
    protected $signature = 'rag:index
                            {title           : Document title}
                            {source_type     : Category tag, e.g. faq, sop, product, policy}
                            {--text=         : Inline text content to index}
                            {--file=         : Absolute path to a .txt or .md file to index}
                            {--entity=       : Optional entity_id to scope the document}
                            {--chunk=2000    : Character chunk size for large files}';

    protected $description = 'Index a document into the RAG knowledge base and generate its embedding';

    public function handle(EmbeddingService $embeddingService): int
    {
        $title      = $this->argument('title');
        $sourceType = $this->argument('source_type');
        $content    = null;

        // Read content from --text or --file
        if ($this->option('text')) {
            $content = $this->option('text');
        } elseif ($this->option('file')) {
            $path = $this->option('file');
            if (!file_exists($path)) {
                $this->error("File not found: {$path}");
                return self::FAILURE;
            }
            $content = file_get_contents($path);
        }

        if (empty(trim($content ?? ''))) {
            $this->error('Provide content via --text="..." or --file=path/to/file.txt');
            return self::FAILURE;
        }

        $entityId  = $this->option('entity') ? (int) $this->option('entity') : null;
        $chunkSize = (int) $this->option('chunk');

        // Split large content into chunks
        $chunks = $this->splitIntoChunks($content, $chunkSize);
        $total  = count($chunks);

        $this->info("Indexing \"{$title}\" ({$total} chunk" . ($total > 1 ? 's' : '') . ")...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $indexed  = 0;
        $skipped  = 0;

        foreach ($chunks as $i => $chunk) {
            $chunkTitle = $total > 1 ? "{$title} (Part " . ($i + 1) . "/{$total})" : $title;
            $hash       = hash('sha256', $chunk);

            if (RagDocument::where('content_hash', $hash)->exists()) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $embedding = $embeddingService->embed($chunk);

            RagDocument::create([
                'entity_id'    => $entityId,
                'source_type'  => $sourceType,
                'title'        => $chunkTitle,
                'content'      => $chunk,
                'embedding'    => empty($embedding) ? null : json_encode($embedding),
                'content_hash' => $hash,
                'token_count'  => $embeddingService->estimateTokens($chunk),
                'is_active'    => true,
            ]);

            $indexed++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("✅ Done. Indexed: {$indexed}, Skipped (duplicates): {$skipped}");

        return self::SUCCESS;
    }

    /**
     * Split text into overlapping chunks for better semantic coverage.
     */
    private function splitIntoChunks(string $text, int $chunkSize): array
    {
        $text = trim($text);
        if (mb_strlen($text) <= $chunkSize) {
            return [$text];
        }

        $chunks  = [];
        $overlap = (int) ($chunkSize * 0.15); // 15% overlap between chunks
        $offset  = 0;
        $len     = mb_strlen($text);

        while ($offset < $len) {
            $chunk = mb_substr($text, $offset, $chunkSize);

            // Try to split at a natural paragraph/sentence boundary
            if ($offset + $chunkSize < $len) {
                $lastNewline = mb_strrpos($chunk, "\n");
                $lastPeriod  = mb_strrpos($chunk, '. ');
                $boundary    = max($lastNewline ?: 0, $lastPeriod ?: 0);
                if ($boundary > $chunkSize * 0.5) {
                    $chunk = mb_substr($chunk, 0, $boundary + 1);
                }
            }

            $chunks[] = trim($chunk);
            $offset  += mb_strlen($chunk) - $overlap;
        }

        return array_filter($chunks);
    }
}
