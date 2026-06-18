<?php

declare(strict_types=1);

require_once __DIR__ . '/SMSGateway.php';

class PaymentCompletionService
{
    public function __construct(
        private PDO $pdo,
        private RegistrationRepository $repo,
        private ScheduleService $schedule
    ) {
    }

    public function markPaidAndNotify(string $tranId, string $valId, bool $validateWithApi = false): bool
    {
        $registration = $this->repo->findByTranId($tranId);
        if (!$registration) {
            return false;
        }

        $type = (string)$registration['registration_type'];
        if (($registration['payment_status'] ?? '') === 'paid') {
            return false;
        }

        if ($validateWithApi) {
            $ssl = new SSLCommerz();
            $verified = $ssl->validatePayment(
                $valId,
                $tranId,
                (float)$registration['amount'],
                (string)$registration['currency']
            );
            if (!$verified) {
                $this->repo->updatePaymentStatus($type, $tranId, 'failed');
                return false;
            }
        }

        $updated = $this->repo->updatePaymentStatus($type, $tranId, 'paid', $valId);
        if (!$updated) {
            return false;
        }

        if ($this->repo->shouldSendSms($registration)) {
            $teeTitle = $this->schedule->resolveScheduleTitle($type, $registration);
            SMSGateway::send($registration['contact'], $registration['full_name'], $teeTitle);
            $this->repo->markSmsSent($type, $tranId);
        }

        return true;
    }
}
