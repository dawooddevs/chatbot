<?php
declare(strict_types=1);

namespace App;

final class KnowledgeBase
{
    private const EMBED_BATCH = 32;

    public static function saveDocument(int $siteId, array $data, ?int $documentId = null): int
    {
        $title = trim((string)($data['title'] ?? '')) ?: 'Untitled';
        $content = Chunker::normalize((string)($data['content'] ?? ''));

        if ($documentId) {
            Database::run(
                'UPDATE documents SET title = ?, source_type = ?, source_url = ?, content = ?,
                    status = ?, error = NULL, updated_at = NOW()
                 WHERE id = ? AND site_id = ?',
                [
                    $title,
                    $data['source_type'] ?? 'text',
                    $data['source_url'] ?? null,
                    $content,
                    'pending',
                    $documentId,
                    $siteId,
                ]
            );
            return $documentId;
        }

        return Database::insert(
            'INSERT INTO documents (site_id, title, source_type, source_url, content, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [$siteId, $title, $data['source_type'] ?? 'text', $data['source_url'] ?? null, $content, 'pending']
        );
    }

    public static function deleteDocument(int $siteId, int $documentId): void
    {
        Database::run('DELETE FROM chunks WHERE document_id = ? AND site_id = ?', [$documentId, $siteId]);
        Database::run('DELETE FROM documents WHERE id = ? AND site_id = ?', [$documentId, $siteId]);
    }

    /**
     * Chunks a document and stores an embedding per chunk.
     * Falls back to keyword-only search (no embeddings) when OpenAI is unreachable.
     */
    public static function indexDocument(int $documentId, array $site): void
    {
        $document = Database::first('SELECT * FROM documents WHERE id = ? LIMIT 1', [$documentId]);
        if (!$document) {
            throw new \RuntimeException('Document not found.');
        }

        $chunks = Chunker::split((string)$document['content']);
        Database::run('DELETE FROM chunks WHERE document_id = ?', [$documentId]);

        if ($chunks === []) {
            self::markDocument($documentId, 'empty', 0, 'The document has no usable text.');
            return;
        }

        try {
            $client = new OpenAi();
            $model = (string)($site['ai']['embedding_model'] ?? 'text-embedding-3-small');
            $position = 0;
            foreach (array_chunk($chunks, self::EMBED_BATCH) as $batch) {
                $vectors = $client->embed($batch, $model);
                foreach ($batch as $i => $text) {
                    Database::run(
                        'INSERT INTO chunks (document_id, site_id, position, content, embedding, created_at)
                         VALUES (?, ?, ?, ?, ?, NOW())',
                        [$documentId, (int)$document['site_id'], $position++, $text, Vector::pack($vectors[$i])]
                    );
                }
            }
            self::markDocument($documentId, 'ready', count($chunks), null);
        } catch (\Throwable $e) {
            // Keep the text searchable by keyword even if embedding failed.
            Database::run('DELETE FROM chunks WHERE document_id = ?', [$documentId]);
            $position = 0;
            foreach ($chunks as $text) {
                Database::run(
                    'INSERT INTO chunks (document_id, site_id, position, content, embedding, created_at)
                     VALUES (?, ?, ?, ?, NULL, NOW())',
                    [$documentId, (int)$document['site_id'], $position++, $text]
                );
            }
            self::markDocument($documentId, 'keyword_only', count($chunks), $e->getMessage());
            throw $e;
        }
    }

    private static function markDocument(int $id, string $status, int $chunkCount, ?string $error): void
    {
        Database::run(
            'UPDATE documents SET status = ?, chunk_count = ?, error = ?, updated_at = NOW() WHERE id = ?',
            [$status, $chunkCount, $error, $id]
        );
    }

    /**
     * @return array<int, array{content:string, title:string, score:float}>
     */
    public static function search(array $site, string $question, int $topK = 5, float $minScore = 0.15): array
    {
        $siteId = (int)$site['id'];
        $rows = Database::all(
            'SELECT c.id, c.content, c.embedding, d.title
               FROM chunks c JOIN documents d ON d.id = c.document_id
              WHERE c.site_id = ?
              LIMIT 6000',
            [$siteId]
        );
        if ($rows === []) {
            return [];
        }

        $queryVector = null;
        $hasEmbeddings = false;
        foreach ($rows as $row) {
            if (!empty($row['embedding'])) {
                $hasEmbeddings = true;
                break;
            }
        }
        if ($hasEmbeddings) {
            try {
                $client = new OpenAi();
                $queryVector = $client->embed([$question], (string)($site['ai']['embedding_model'] ?? 'text-embedding-3-small'))[0] ?? null;
            } catch (\Throwable) {
                $queryVector = null;
            }
        }

        $scored = [];
        foreach ($rows as $row) {
            $score = $queryVector !== null && !empty($row['embedding'])
                ? Vector::cosine($queryVector, Vector::unpack((string)$row['embedding']))
                : self::keywordScore($question, (string)$row['content']);

            if ($score >= $minScore) {
                $scored[] = [
                    'content' => (string)$row['content'],
                    'title' => (string)$row['title'],
                    'score' => round($score, 4),
                ];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, max(1, $topK));
    }

    /** Simple overlap score used when embeddings are unavailable. */
    private static function keywordScore(string $question, string $content): float
    {
        $terms = array_unique(array_filter(
            preg_split('/\W+/u', mb_strtolower($question)) ?: [],
            static fn (string $t): bool => mb_strlen($t) > 2
        ));
        if ($terms === []) {
            return 0.0;
        }
        $haystack = mb_strtolower($content);
        $hits = 0;
        foreach ($terms as $term) {
            if (str_contains($haystack, $term)) {
                $hits++;
            }
        }
        return $hits / count($terms);
    }

    public static function stats(int $siteId): array
    {
        return [
            'documents' => (int)Database::value('SELECT COUNT(*) FROM documents WHERE site_id = ?', [$siteId]),
            'chunks' => (int)Database::value('SELECT COUNT(*) FROM chunks WHERE site_id = ?', [$siteId]),
            'embedded' => (int)Database::value('SELECT COUNT(*) FROM chunks WHERE site_id = ? AND embedding IS NOT NULL', [$siteId]),
        ];
    }
}
