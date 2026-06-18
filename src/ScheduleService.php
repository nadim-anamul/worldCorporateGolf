<?php

declare(strict_types=1);

class ScheduleService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getGolferTeeOptions(int $tournamentId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, title, reporting_time, group_photo_time, tee_off_time, slot_number
             FROM tee_time_options
             WHERE tournament_id = ? AND is_active = 1
             ORDER BY display_order DESC, id ASC"
        );
        $stmt->execute([$tournamentId]);
        $options = $stmt->fetchAll();
        $counts = $this->paidCountsByKey('registrations', 'schedule_group', $tournamentId);

        return $this->mapSlotOptions($options, $counts, 'tee_off_time');
    }

    public function getNonGolferWindowOptions(int $tournamentId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, title, window_time, group_photo_time, slot_number
             FROM arrival_window_options_non_golfer
             WHERE tournament_id = ? AND is_active = 1
             ORDER BY display_order DESC, id ASC"
        );
        $stmt->execute([$tournamentId]);
        $options = $stmt->fetchAll();
        $counts = $this->paidCountsByKey('registrations_non_golfer', 'arrival_window', $tournamentId);

        $mapped = [];
        foreach ($options as $opt) {
            $id = (string)$opt['id'];
            $used = $counts[$id] ?? 0;
            $mapped[] = [
                'id'          => $id,
                'title'       => (string)$opt['title'],
                'reporting'   => (string)$opt['window_time'],
                'group_photo' => (string)$opt['group_photo_time'],
                'tee_off'     => (string)$opt['window_time'],
                'slots_left'  => max(0, (int)$opt['slot_number'] - $used),
            ];
        }
        return $mapped;
    }

    public function buildScheduleLabels(): array
    {
        $labels = [];

        $rows = $this->pdo->query(
            "SELECT id, tournament_id, title, tee_off_time FROM tee_time_options ORDER BY display_order DESC, id DESC"
        )->fetchAll();
        foreach ($rows as $row) {
            $tourId = (int)$row['tournament_id'];
            $optId = (string)$row['id'];
            $labels["golfer_{$tourId}_{$optId}"] = trim((string)$row['title']) . ' · ' . trim((string)$row['tee_off_time']);
        }

        $winRows = $this->pdo->query(
            "SELECT id, tournament_id, title, window_time FROM arrival_window_options_non_golfer ORDER BY display_order DESC, id ASC"
        )->fetchAll();
        foreach ($winRows as $w) {
            $tourId = (int)$w['tournament_id'];
            $optId = (string)$w['id'];
            $labels["non_golfer_{$tourId}_{$optId}"] = trim((string)$w['title']) . ' · ' . trim((string)$w['window_time']);
        }

        return $labels;
    }

    public function resolveScheduleTitle(string $regType, array $registration): string
    {
        if ($regType === 'golfer') {
            $stmt = $this->pdo->prepare('SELECT title FROM tee_time_options WHERE id = ? LIMIT 1');
            $stmt->execute([(string)($registration['schedule_group'] ?? '')]);
            $row = $stmt->fetch();
            return $row ? (string)$row['title'] : 'TBA';
        }

        $windowId = (string)($registration['arrival_window'] ?? '');
        $stmt = $this->pdo->prepare('SELECT title FROM arrival_window_options_non_golfer WHERE id = ? LIMIT 1');
        $stmt->execute([$windowId]);
        $row = $stmt->fetch();
        return $row ? (string)$row['title'] : 'TBA';
    }

    public function resolveScheduleDetails(string $regType, array $registration): ?array
    {
        if ($regType === 'golfer') {
            $stmt = $this->pdo->prepare(
                'SELECT title, reporting_time, tee_off_time FROM tee_time_options WHERE id = ? LIMIT 1'
            );
            $stmt->execute([(string)($registration['schedule_group'] ?? '')]);
            return $stmt->fetch() ?: null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT title, window_time, group_photo_time FROM arrival_window_options_non_golfer WHERE id = ? LIMIT 1'
        );
        $stmt->execute([(string)($registration['arrival_window'] ?? '')]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return [
            'title'          => $row['title'],
            'reporting_time' => $row['window_time'],
            'tee_off_time'   => $row['window_time'],
            'group_photo_time' => $row['group_photo_time'],
        ];
    }

    /**
     * Locks slot row, validates capacity, runs callback, then commits.
     *
     * @throws RuntimeException when slot is invalid or full
     */
    public function withSlotReservation(string $regType, string $slotId, int $tournamentId, callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $this->lockAndAssertSlot($regType, $slotId, $tournamentId);
            $result = $callback();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function lockAndAssertSlot(string $regType, string $slotId, int $tournamentId): void
    {
            if ($regType === 'golfer') {
                $optStmt = $this->pdo->prepare(
                    "SELECT slot_number, title FROM tee_time_options
                     WHERE id = ? AND tournament_id = ? AND is_active = 1
                     LIMIT 1 FOR UPDATE"
                );
                $optStmt->execute([(int)$slotId, $tournamentId]);
                $opt = $optStmt->fetch();
                if (!$opt) {
                    throw new RuntimeException('Invalid tee time selection. Please reload and try again.');
                }

                $capStmt = $this->pdo->prepare(
                    "SELECT COUNT(*) AS cnt FROM registrations
                     WHERE schedule_group = ? AND tournament_id = ? AND payment_status = 'paid'"
                );
                $capStmt->execute([$slotId, $tournamentId]);
            } else {
                $optStmt = $this->pdo->prepare(
                    "SELECT slot_number, title FROM arrival_window_options_non_golfer
                     WHERE id = ? AND tournament_id = ? AND is_active = 1
                     LIMIT 1 FOR UPDATE"
                );
                $optStmt->execute([$slotId, $tournamentId]);
                $opt = $optStmt->fetch();
                if (!$opt) {
                    throw new RuntimeException('Invalid arrival window selection. Please reload and try again.');
                }

                $capStmt = $this->pdo->prepare(
                    "SELECT COUNT(*) AS cnt FROM registrations_non_golfer
                     WHERE arrival_window = ? AND tournament_id = ? AND payment_status = 'paid'"
                );
                $capStmt->execute([$slotId, $tournamentId]);
            }

            $used = (int)$capStmt->fetch()['cnt'];
            if ($used >= (int)$opt['slot_number']) {
                throw new RuntimeException('Selected schedule is full. Please choose a different option.');
            }
    }

    private function paidCountsByKey(string $table, string $column, int $tournamentId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT {$column}, COUNT(*) AS cnt
             FROM {$table}
             WHERE tournament_id = ? AND payment_status = 'paid'
             GROUP BY {$column}"
        );
        $stmt->execute([$tournamentId]);
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(string)$row[$column]] = (int)$row['cnt'];
        }
        return $counts;
    }

    private function mapSlotOptions(array $options, array $counts, string $timeField): array
    {
        $mapped = [];
        foreach ($options as $opt) {
            $id = (string)$opt['id'];
            $used = $counts[$id] ?? 0;
            $mapped[] = [
                'id'          => $id,
                'title'       => (string)$opt['title'],
                'reporting'   => (string)$opt['reporting_time'],
                'group_photo' => (string)$opt['group_photo_time'],
                'tee_off'     => (string)$opt[$timeField],
                'slots_left'  => max(0, (int)$opt['slot_number'] - $used),
            ];
        }
        return $mapped;
    }
}
