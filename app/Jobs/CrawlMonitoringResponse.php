<?php

namespace App\Jobs;

use App\Enums\HttpMethod;
use App\Enums\MonitoringStatus;
use App\Enums\MonitoringType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use stdClass;
use Throwable;

/**
 * Crawls a monitoring target to check its response status.
 *
 * This job handles various monitoring types (HTTP, Ping, Keyword, Port)
 * and records the response status and time. It also manages incidents
 * based on the monitoring status.
 */
class CrawlMonitoringResponse implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Indicates if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = true;

    /**
     * The status of the monitoring check (UP, DOWN, UNKNOWN).
     */
    private ?MonitoringStatus $monitoringStatus = null;

    /**
     * The response time of the monitoring check in milliseconds.
     */
    private ?float $responseTime = null;

    /**
     * An optional message providing more details about the monitoring result.
     */
    private ?string $message = null;

    /**
     * Create a new job instance.
     */
    public function __construct(public stdClass $monitoring)
    {
        $this->monitoring->type = MonitoringType::from($this->monitoring->type);
        if (isset($this->monitoring->http_method)) {
            $this->monitoring->http_method = HttpMethod::from($this->monitoring->http_method);
        }
    }

    /**
     * Execute the job.
     *
     * This method performs the actual monitoring check based on the monitoring type,
     * records the results, and manages incidents.
     */
    public function handle(): void
    {
        $this->monitoringStatus = MonitoringStatus::UNKNOWN;
        $this->responseTime = null;
        $this->message = null;

        try {
            $handler = match ($this->monitoring->type) {
                MonitoringType::HTTP => fn() => $this->handleHttp(),
                MonitoringType::PING => fn() => $this->handlePing(),
                MonitoringType::KEYWORD => fn() => $this->handleKeyword(),
                MonitoringType::PORT => fn() => $this->handlePort(),
            };

            $handler();
        } catch (Throwable $throwable) {
            $this->monitoringStatus = MonitoringStatus::UNKNOWN;

            $this->message = sprintf('An unexpected error occurred: %s', $throwable->getMessage());
        }

        $this->sendResponse();
    }

    /**
     * Handles HTTP monitoring checks.
     *
     * Performs an HTTP request to the target and determines the monitoring status
     * based on the response.
     */
    private function handleHttp(): void
    {
        $start = microtime(true);
        $response = $this->performHttpRequest();

        if ($response && $response->successful()) {
            $this->monitoringStatus = MonitoringStatus::UP;
            $this->message = sprintf('Successfully received a %s response.', $response->status());
            $this->responseTime = round((microtime(true) - $start) * 1000, 2);
        } else {
            $this->monitoringStatus = MonitoringStatus::DOWN;
            $this->message = sprintf('Failed to receive a successful response. Status code: %s', $response?->status() ?? 'N/A');
        }
    }

    /**
     * Handles Ping monitoring checks.
     *
     * Attempts to connect to common ports (80, 443, 21) on the target
     * to determine its reachability.
     */
    private function handlePing(): void
    {
        $port = $this->monitoring->port ?? 80;

        $start = microtime(true);

        $fp = @stream_socket_client(
            sprintf('tcp://%s:%s', $this->monitoring->target, $port),
            $errCode,
            $errStr,
            5
        );

        $this->responseTime = round((microtime(true) - $start) * 1000, 2);
        if ($fp) {
            fclose($fp);
            $this->monitoringStatus = MonitoringStatus::UP;
            $this->message = sprintf('Successfully connected to port %s.', $port);

            return;
        }

        $this->monitoringStatus = MonitoringStatus::DOWN;
        $this->message = $errStr ? sprintf('Failed to connect to port %s: %s', $port, $errStr) : sprintf('Failed to connect to port %s.', $port);
    }

    /**
     * Handles Keyword monitoring checks.
     *
     * Performs an HTTP request and checks if a specific keyword is present
     * in the response body.
     */
    private function handleKeyword(): void
    {
        $start = microtime(true);
        $response = $this->performHttpRequest();

        if ($response && str_contains($response->body(), $this->monitoring->keyword)) {
            $this->monitoringStatus = MonitoringStatus::UP;
            $this->message = sprintf('The keyword "%s" was found on the page.', $this->monitoring->keyword);
            $this->responseTime = round((microtime(true) - $start) * 1000, 2);
        } else {
            $this->monitoringStatus = MonitoringStatus::DOWN;
            $this->message = sprintf('The keyword "%s" was not found on the page.', $this->monitoring->keyword);
        }
    }

    /**
     * Handles Port monitoring checks.
     *
     * Attempts to connect to a specific port on the target to determine
     * its accessibility.
     */
    private function handlePort(): void
    {
        $start = microtime(true);

        $fp = @stream_socket_client(
            sprintf('tcp://%s:%s', $this->monitoring->target, $this->monitoring->port),
            $errCode,
            $errStr,
            5
        );

        if ($fp) {
            fclose($fp);
            $this->monitoringStatus = MonitoringStatus::UP;
            $this->message = sprintf('Successfully connected to port %s.', $this->monitoring->port);
            $this->responseTime = round((microtime(true) - $start) * 1000, 2);
        } else {
            $this->monitoringStatus = MonitoringStatus::DOWN;
            $this->message = $errStr ? sprintf('Failed to connect to port %s: %s', $this->monitoring->port, $errStr) : sprintf('Failed to connect to port %s.', $this->monitoring->port);
        }
    }

    /**
     * Performs an HTTP request based on the monitoring configuration.
     *
     * Handles timeouts, retries, basic authentication, and custom headers.
     *
     * @return Response|null The HTTP response object, or null if an error occurred.
     */
    private function performHttpRequest(): ?Response
    {
        try {
            $request = Http::timeout($this->monitoring->timeout)
                ->retry(2, 500)
                ->withoutVerifying();

            if ($this->monitoring->auth_username && $this->monitoring->auth_password) {
                $request = $request->withBasicAuth($this->monitoring->auth_username, $this->monitoring->auth_password);
            }

            if ($this->monitoring->http_headers && is_string($this->monitoring->http_headers)) {
                $headers = json_decode($this->monitoring->http_headers, true);
                if (is_array($headers)) {
                    $request = $request->withHeaders($headers);
                }
            } elseif (is_array($this->monitoring->http_headers)) {
                $request = $request->withHeaders($this->monitoring->http_headers);
            }

            $httpMethod = $this->monitoring->http_method ?? HttpMethod::GET;

            $method = mb_strtolower((string) $httpMethod->value);
            $body = json_decode($this->monitoring->http_body ?? '[]', true) ?? [];

            if (in_array($method, ['get', 'delete'])) {
                return $request->$method($this->monitoring->target);
            }

            return $request->$method($this->monitoring->target, $body);
        } catch (Throwable $throwable) {
            Log::error(sprintf('HTTP request failed for %s: ', $this->monitoring->target) . $throwable->getMessage());

            return null;
        }
    }

    private function sendResponse(): void
    {
        Http::withHeaders([
            'X-API-KEY' => config('webguard.webguard_core_api_key'),
        ])->post(config('webguard.webguard_core_api_url') . '/api/v1/internal/monitoring-responses', [
            'monitoring_id' => $this->monitoring->id,
            'status' => $this->monitoringStatus,
            'response_time' => $this->responseTime,
        ]);
    }
}
