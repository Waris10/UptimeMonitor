<?php

namespace App\Models;

use App\Enums\MonitorStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Monitor extends Model
{
    protected $fillable = [
        'url',
        'check_interval',
        'threshold',
    ];

    protected function casts(): array
    {
        return [
            'check_interval' => 'integer',
            'threshold' => 'integer',
            'consecutive_failures' => 'integer',
            'status' => MonitorStatusEnum::class,
            'last_checked_at' => 'datetime',
        ];
    }

    public function checks(): HasMany
    {
        return $this->hasMany(Check::class);
    }

    /**
     * Monitors that are due for a check:
     * never checked, OR last_checked_at + check_interval minutes <= NOW().
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('last_checked_at')
                ->orWhereRaw('last_checked_at <= DATE_SUB(NOW(), INTERVAL check_interval MINUTE)');
        });
    }

    /**
     * Attach uptime aggregates (last 24h) as subquery columns,
     * so uptime_percentage can be computed without an N+1.
     *
     * Adds two virtual attributes to each row:
     *   - uptime_total_24h: total checks recorded in the last 24h
     *   - uptime_ups_24h:   successful checks in the last 24h
     */
    public function scopeWithUptime(Builder $query): Builder
    {
        $hours = config('monitoring.check.uptime_window_hours');
        $window = now()->subHours($hours);

        return $query->addSelect([
            'uptime_total_24h' => Check::query()
                ->selectRaw('COUNT(*)')
                ->whereColumn('monitor_id', 'monitors.id')
                ->where('checked_at', '>=', $window),
            'uptime_ups_24h' => Check::query()
                ->selectRaw('SUM(CASE WHEN is_up = 1 THEN 1 ELSE 0 END)')
                ->whereColumn('monitor_id', 'monitors.id')
                ->where('checked_at', '>=', $window),
        ]);
    }

    /**
     * Uptime percentage over the last 24 hours.
     *
     * Reads from the aggregates loaded by scopeWithUptime().
     * Returns null when the scope wasn't applied OR there are no checks in the window.
     */
    public function getUptimePercentageAttribute(): ?float
    {
        $total = (int) ($this->attributes['uptime_total_24h'] ?? 0);

        if ($total === 0) {
            return null;
        }

        $ups = (int) ($this->attributes['uptime_ups_24h'] ?? 0);

        return round(($ups / $total) * 100, 2);
    }
}
