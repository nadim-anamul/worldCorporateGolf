<?php

declare(strict_types=1);

class RegistrationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByTranId(string $tranId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT *, \'golfer\' AS registration_type FROM registrations WHERE tran_id = ? LIMIT 1');
        $stmt->execute([$tranId]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }

        $stmt = $this->pdo->prepare(
            'SELECT *, \'non_golfer\' AS registration_type FROM registrations_non_golfer WHERE tran_id = ? LIMIT 1'
        );
        $stmt->execute([$tranId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUniqueId(string $uniqueId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT *, \'golfer\' AS registration_type FROM registrations WHERE unique_id = ? LIMIT 1');
        $stmt->execute([$uniqueId]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }

        $stmt = $this->pdo->prepare(
            'SELECT *, \'non_golfer\' AS registration_type FROM registrations_non_golfer WHERE unique_id = ? LIMIT 1'
        );
        $stmt->execute([$uniqueId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listByTournament(int $tournamentId): array
    {
        $gStmt = $this->pdo->prepare(
            "SELECT id, tournament_id, unique_id, tran_id, full_name, designation, organization, nationality, gender,
                    profile_photo, name_on_polo, golf_set_brand, contact, email, mailing_address,
                    handicap, tshirt_size, home_club, schedule_group, player_category, reference_name,
                    reference_mission, reference_contact, payment_status, amount, currency, val_id,
                    ssl_session_key, submitted_at, paid_at, sms_sent_at,
                    'golfer' AS registration_type, '' AS putting_contest_interest
             FROM registrations WHERE tournament_id = ?"
        );
        $gStmt->execute([$tournamentId]);
        $g = $gStmt->fetchAll();

        $ngStmt = $this->pdo->prepare(
            "SELECT id, tournament_id, unique_id, tran_id, full_name, designation, organization, nationality, gender,
                    profile_photo, name_on_polo, '' AS golf_set_brand, contact, email, mailing_address,
                    '' AS handicap, tshirt_size, '' AS home_club, arrival_window AS schedule_group,
                    player_category, reference_name, reference_mission, reference_contact, payment_status,
                    amount, currency, val_id, ssl_session_key, submitted_at, paid_at, sms_sent_at,
                    'non_golfer' AS registration_type, putting_contest_interest
             FROM registrations_non_golfer WHERE tournament_id = ?"
        );
        $ngStmt->execute([$tournamentId]);
        $ng = $ngStmt->fetchAll();

        $all = array_merge($g, $ng);
        usort($all, static fn($a, $b) => strcmp((string)($b['submitted_at'] ?? ''), (string)($a['submitted_at'] ?? '')));
        return $all;
    }

    public function targetTable(string $regType): string
    {
        return $regType === 'non_golfer' ? 'registrations_non_golfer' : 'registrations';
    }

    public function deleteAbandonedByEmail(string $regType, string $email, int $tournamentId): void
    {
        $table = $this->targetTable($regType);
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$table} WHERE email = ? AND tournament_id = ? AND payment_status IN ('pending','failed','cancelled')"
        );
        $stmt->execute([$email, $tournamentId]);
    }

    public function hasPaidEmail(string $regType, string $email, int $tournamentId): bool
    {
        $table = $this->targetTable($regType);
        $stmt = $this->pdo->prepare(
            "SELECT id FROM {$table} WHERE email = ? AND tournament_id = ? AND payment_status = 'paid' LIMIT 1"
        );
        $stmt->execute([$email, $tournamentId]);
        return (bool)$stmt->fetch();
    }

    public function createPending(string $regType, array $data): void
    {
        if ($regType === 'golfer') {
            $stmt = $this->pdo->prepare(
                'INSERT INTO registrations
                   (tournament_id, unique_id, tran_id, full_name, designation, organization, nationality, gender,
                    profile_photo, name_on_polo, golf_set_brand, contact, email, mailing_address, handicap,
                    tshirt_size, home_club, schedule_group, player_category, reference_name, reference_mission,
                    reference_contact, payment_status, amount, currency, submitted_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $data['tournament_id'], $data['unique_id'], $data['tran_id'], $data['full_name'],
                $data['designation'], $data['organization'], $data['nationality'], null,
                $data['profile_photo'], $data['name_on_polo'], $data['golf_set_brand'], $data['contact'],
                $data['email'], $data['mailing_address'], $data['handicap'], $data['tshirt_size'], null,
                $data['schedule_group'], $data['player_category'], $data['reference_name'] ?: null,
                $data['reference_mission'] ?: null, $data['reference_contact'] ?: null, 'pending',
                $data['amount'], $data['currency'],
            ]);
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO registrations_non_golfer
               (tournament_id, unique_id, tran_id, full_name, designation, organization, nationality, gender,
                profile_photo, name_on_polo, contact, email, mailing_address, tshirt_size, arrival_window,
                putting_contest_interest, player_category, reference_name, reference_mission, reference_contact,
                payment_status, amount, currency, submitted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['tournament_id'], $data['unique_id'], $data['tran_id'], $data['full_name'],
            $data['designation'], $data['organization'], $data['nationality'], null,
            $data['profile_photo'], $data['name_on_polo'], $data['contact'], $data['email'],
            $data['mailing_address'], $data['tshirt_size'], $data['arrival_window'],
            $data['putting_contest_interest'], $data['player_category'], $data['reference_name'] ?: null,
            $data['reference_mission'] ?: null, $data['reference_contact'] ?: null, 'pending',
            $data['amount'], $data['currency'],
        ]);
    }

    public function updatePaymentStatus(string $regType, string $tranId, string $status, ?string $valId = null): bool
    {
        $table = $this->targetTable($regType);
        if ($status === 'paid') {
            $stmt = $this->pdo->prepare(
                "UPDATE {$table} SET payment_status = 'paid', val_id = ?, paid_at = NOW() WHERE tran_id = ? AND payment_status != 'paid'"
            );
            $stmt->execute([$valId, $tranId]);
            return $stmt->rowCount() > 0;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE {$table} SET payment_status = ? WHERE tran_id = ? AND payment_status = 'pending'"
        );
        $stmt->execute([$status, $tranId]);
        return $stmt->rowCount() > 0;
    }

    public function markSmsSent(string $regType, string $tranId): void
    {
        $table = $this->targetTable($regType);
        $this->pdo->prepare("UPDATE {$table} SET sms_sent_at = NOW() WHERE tran_id = ?")->execute([$tranId]);
    }

    public function shouldSendSms(array $registration): bool
    {
        return empty($registration['sms_sent_at']);
    }

    public function deleteByUniqueId(string $regType, string $uniqueId): bool
    {
        $table = $this->targetTable($regType);
        $stmt = $this->pdo->prepare("SELECT profile_photo FROM {$table} WHERE unique_id = ? LIMIT 1");
        $stmt->execute([$uniqueId]);
        $row = $stmt->fetch();

        $del = $this->pdo->prepare("DELETE FROM {$table} WHERE unique_id = ?");
        $del->execute([$uniqueId]);
        $deleted = $del->rowCount() > 0;

        if ($deleted && !empty($row['profile_photo'])) {
            $path = dirname(__DIR__) . '/' . ltrim((string)$row['profile_photo'], '/');
            if (is_file($path)) {
                @unlink($path);
            }
        }
        return $deleted;
    }
}
