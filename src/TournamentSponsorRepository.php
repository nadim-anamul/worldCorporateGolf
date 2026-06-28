<?php

declare(strict_types=1);

class TournamentSponsorRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTournament(int $tournamentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tournament_id, name, website_url, logo_path, display_order, is_active
             FROM tournament_sponsors
             WHERE tournament_id = ?
             ORDER BY display_order DESC, id ASC'
        );
        $stmt->execute([$tournamentId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveForTournament(int $tournamentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, website_url, logo_path, display_order
             FROM tournament_sponsors
             WHERE tournament_id = ? AND is_active = 1
             ORDER BY display_order DESC, id ASC'
        );
        $stmt->execute([$tournamentId]);

        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tournament_id, name, website_url, logo_path, display_order, is_active
             FROM tournament_sponsors
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(
        int $tournamentId,
        string $name,
        string $websiteUrl,
        string $logoPath,
        int $displayOrder
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tournament_sponsors (tournament_id, name, website_url, logo_path, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([$tournamentId, $name, $websiteUrl, $logoPath, $displayOrder]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(
        int $id,
        string $name,
        string $websiteUrl,
        string $logoPath,
        int $displayOrder
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE tournament_sponsors
             SET name = ?, website_url = ?, logo_path = ?, display_order = ?
             WHERE id = ?'
        );
        $stmt->execute([$name, $websiteUrl, $logoPath, $displayOrder, $id]);
    }

    public function toggleActive(int $id): void
    {
        $this->pdo->prepare(
            'UPDATE tournament_sponsors SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?'
        )->execute([$id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM tournament_sponsors WHERE id = ?')->execute([$id]);
    }

    public static function normalizeWebsiteUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    public static function logoPublicUrl(string $logoPath): string
    {
        $logoPath = ltrim(str_replace('\\', '/', $logoPath), '/');
        if (str_contains($logoPath, '..')) {
            return '';
        }

        return APP_BASE_URL . '/' . $logoPath;
    }

    /**
     * @param list<array<string, mixed>> $sponsors
     * @return list<array<string, mixed>>
     */
    public static function expandForMarquee(array $sponsors, int $minCount = 3): array
    {
        if ($sponsors === []) {
            return [];
        }

        $expanded = $sponsors;
        while (count($expanded) < $minCount) {
            $expanded = array_merge($expanded, $sponsors);
        }

        return array_merge($expanded, $expanded);
    }
}
