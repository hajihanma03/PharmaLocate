<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Setting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    /** @var array<string, mixed> */
    private const DEFAULTS = [
        'low_stock_threshold' => '10',
        'notification_low_stock' => '1',
        'notification_inquiries' => '1',
        'backup_schedule_enabled' => '0',
        'backup_schedule_time' => '02:00',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $record = static::query()->find($key);

        if ($record !== null) {
            return $record->value;
        }

        return $default ?? (static::DEFAULTS[$key] ?? null);
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value],
        );
    }

    /** @return array<string, mixed> */
    public static function allKeyed(): array
    {
        $stored = static::query()->pluck('value', 'key')->all();

        return array_merge(static::DEFAULTS, $stored);
    }

    public static function lowStockThreshold(): int
    {
        return max(1, (int) static::get('low_stock_threshold', 10));
    }

    public static function statusForQuantity(int $qty, ?int $threshold = null): string
    {
        $threshold ??= static::lowStockThreshold();

        return match (true) {
            $qty <= 0 => 'out_of_stock',
            $qty < $threshold => 'low',
            default => 'available',
        };
    }

    /**
     * Rewrite stored stock labels so they match the current low-stock threshold.
     */
    public static function syncInventoryStatuses(): void
    {
        $threshold = static::lowStockThreshold();
        $statusSql = "CASE WHEN stock_quantity <= 0 THEN 'out_of_stock' WHEN stock_quantity < {$threshold} THEN 'low' ELSE 'available' END";

        DB::table('pharmacy_medicine')
            ->whereRaw("availability_status <> {$statusSql}")
            ->update([
                'availability_status' => DB::raw($statusSql),
            ]);
    }
}
