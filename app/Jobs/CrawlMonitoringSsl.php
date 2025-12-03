<?php

namespace App\Jobs;

use App\Enums\MonitoringType;
use DateTimeInterface;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\SslCertificate\SslCertificate;
use stdClass;

/**
 * Crawls a monitoring target to check its SSL certificate status.
 *
 * This job uses the spatie/ssl-certificate package to fetch and
 * validate SSL certificate details, recording the results.
 */
class CrawlMonitoringSsl implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * Indicates if the SSL certificate is valid.
     */
    private bool $valid = false;

    /**
     * The expiration date of the SSL certificate.
     */
    private ?DateTimeInterface $expiresAt = null;

    /**
     * The issuer of the SSL certificate.
     */
    private ?string $issuer = null;

    /**
     * The issue date of the SSL certificate.
     */
    private ?DateTimeInterface $issuedAt = null;

    /**
     * Create a new job instance.
     *
     * @param  stdClass  $monitoring  The monitoring instance to be checked.
     */
    public function __construct(public stdClass $monitoring)
    {
        $this->monitoring->type = MonitoringType::from($this->monitoring->type);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $certificate = SslCertificate::createForHostName($this->monitoring->target);

            if ($certificate->isValid()) {
                $this->valid = true;
                $this->expiresAt = $certificate->expirationDate();
                $this->issuer = $certificate->getIssuer();
                $this->issuedAt = $certificate->validFromDate();
            }
        } catch (Exception $exception) {
            Log::error('Error checking SSL certificate: '.$exception->getMessage());
        }

        $this->sendSsl();
    }

    private function sendSsl(): void
    {
        Http::withHeaders([
            'X-API-KEY' => config('webguard.webguard_core_api_key'),
        ])->post(config('webguard.webguard_core_api_url').'/api/v1/internal/ssl-results', [
            'monitoring_id' => $this->monitoring->id,
            'is_valid' => $this->valid,
            'expires_at' => $this->expiresAt,
            'issuer' => $this->issuer,
            'issued_at' => $this->issuedAt,
        ]);
    }
}
