<?php

namespace App\Models;

use App\Enums\HttpMethod;
use App\Enums\MonitoringLifecycleStatus;
use App\Enums\MonitoringType;
use App\Enums\ServerInstance;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * Class Monitoring
 *
 * Represents a monitoring instance with type, target, keyword or port,
 * associated with a user and having many results.
 *
 * @property string $id
 * @property string $user_id
 * @property string $name
 * @property MonitoringType $type
 * @property string $target
 * @property int|null $port
 * @property string|null $keyword
 * @property MonitoringLifecycleStatus $status
 * @property int|null $timeout
 * @property HttpMethod|null $http_method
 * @property array|null $http_headers
 * @property string|null $http_body
 * @property string|null $auth_username
 * @property string|null $auth_password
 * @property bool $public_label_enabled
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, MonitoringResult> $results
 * @property-read MonitoringResult|null $latestResult
 *
 * @method static Builder|static active()
 * @method static Builder|static paused()
 */
class Monitoring extends Model
{
    use HasFactory;
    use HasUlids;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'monitorings';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'target',
        'port',
        'keyword',
        'status',
        'timeout',
        'http_method',
        'http_headers',
        'http_body',
        'auth_username',
        'auth_password',
        'public_label_enabled',
        'preferred_location',
    ];

    /**
     * Determine if the monitoring is active.
     */
    public function isActive(): bool
    {
        return $this->status === MonitoringLifecycleStatus::ACTIVE;
    }

    /**
     * Determine if the monitoring is paused.
     */
    public function isPaused(): bool
    {
        return $this->status === MonitoringLifecycleStatus::PAUSED;
    }

    /**
     * Apply the global scope to ensure all queries are restricted to the authenticated user.
     */
    #[Override]
    protected static function booted(): void
    {
        static::addGlobalScope('forCurrentLocation', function (Builder $builder) {
            $builder->where('preferred_location', config('webguard.location'));
        });
    }

    /**
     * Scope a query to only include active monitorings.
     */
    #[Scope]
    protected function active(Builder $builder): Builder
    {
        return $builder->where('status', MonitoringLifecycleStatus::ACTIVE);
    }

    /**
     * Scope a query to only include paused monitorings.
     */
    #[Scope]
    protected function paused(Builder $builder): Builder
    {
        return $builder->where('status', MonitoringLifecycleStatus::PAUSED);
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MonitoringType::class,
            'status' => MonitoringLifecycleStatus::class,
            'timeout' => 'integer',
            'http_method' => HttpMethod::class,
            'http_headers' => 'array',
            'public_label_enabled' => 'boolean',
            'preferred_location' => ServerInstance::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
