<?php
declare(strict_types=1);

namespace App;

/**
 * Per-site questions and answers. They drive the chips in the widget and are
 * mirrored into a single knowledge-base document so the model can answer from
 * them like any other source.
 */
final class Faq
{
    public const MAX_CHIPS = 4;
    private const DOCUMENT_TITLE = 'Frequently asked questions';

    /** @return array<int, array> */
    public static function forSite(int $siteId): array
    {
        return Database::all(
            'SELECT * FROM faqs WHERE site_id = ? ORDER BY position, id',
            [$siteId]
        );
    }

    /** @return string[] questions shown as chips in the chat */
    public static function chips(int $siteId): array
    {
        $rows = Database::all(
            'SELECT question FROM faqs WHERE site_id = ? AND show_as_chip = 1 ORDER BY position, id LIMIT ' . self::MAX_CHIPS,
            [$siteId]
        );
        return array_column($rows, 'question');
    }

    public static function find(int $id, int $siteId): ?array
    {
        return Database::first('SELECT * FROM faqs WHERE id = ? AND site_id = ? LIMIT 1', [$id, $siteId]);
    }

    public static function create(int $siteId, string $question, string $answer, bool $chip = true): int
    {
        $position = (int)Database::value('SELECT COALESCE(MAX(position), 0) + 1 FROM faqs WHERE site_id = ?', [$siteId]);

        return Database::insert(
            'INSERT INTO faqs (site_id, question, answer, position, show_as_chip, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
            [$siteId, mb_substr($question, 0, 255), $answer, $position, $chip ? 1 : 0]
        );
    }

    public static function update(int $id, int $siteId, string $question, string $answer, bool $chip): void
    {
        Database::run(
            'UPDATE faqs SET question = ?, answer = ?, show_as_chip = ?, updated_at = NOW() WHERE id = ? AND site_id = ?',
            [mb_substr($question, 0, 255), $answer, $chip ? 1 : 0, $id, $siteId]
        );
    }

    public static function delete(int $id, int $siteId): void
    {
        Database::run('DELETE FROM faqs WHERE id = ? AND site_id = ?', [$id, $siteId]);
    }

    /** @param int[] $ids in their new order */
    public static function reorder(int $siteId, array $ids): void
    {
        $position = 1;
        foreach ($ids as $id) {
            Database::run('UPDATE faqs SET position = ? WHERE id = ? AND site_id = ?', [$position++, (int)$id, $siteId]);
        }
    }

    /**
     * Keeps one knowledge-base document per site in step with the FAQ list, so
     * retrieval and embeddings work exactly as they do for other documents.
     */
    public static function syncDocument(array $site): void
    {
        $siteId = (int)$site['id'];
        $faqs = self::forSite($siteId);
        $existing = Database::first(
            "SELECT id FROM documents WHERE site_id = ? AND source_type = 'faq' LIMIT 1",
            [$siteId]
        );

        if ($faqs === []) {
            if ($existing) {
                KnowledgeBase::deleteDocument($siteId, (int)$existing['id']);
            }
            return;
        }

        $body = '';
        foreach ($faqs as $faq) {
            $body .= 'Q: ' . $faq['question'] . "\nA: " . $faq['answer'] . "\n\n";
        }

        $documentId = KnowledgeBase::saveDocument($siteId, [
            'title' => self::DOCUMENT_TITLE,
            'content' => $body,
            'source_type' => 'faq',
        ], $existing ? (int)$existing['id'] : null);

        try {
            KnowledgeBase::indexDocument($documentId, $site);
        } catch (\Throwable $e) {
            // The document is still searchable by keyword; the list shows why.
            error_log('[chatbot] faq indexing: ' . $e->getMessage());
        }
    }
}
