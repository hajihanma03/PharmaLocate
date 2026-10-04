<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    /** @var list<string> */
    private const KEYS = [
        'low_stock_threshold',
        'notification_low_stock',
        'notification_inquiries',
        'backup_schedule_enabled',
        'backup_schedule_time',
    ];

    public function index(): JsonResponse
    {
        return response()->json(Setting::allKeyed());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'low_stock_threshold' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'notification_low_stock' => ['nullable', 'boolean'],
            'notification_inquiries' => ['nullable', 'boolean'],
            'backup_schedule_enabled' => ['nullable', 'boolean'],
            'backup_schedule_time' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ]);

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            if ($key === 'backup_schedule_time') {
                $value = substr((string) $value, 0, 5);
            }
            Setting::set($key, $value);
        }

        if (array_key_exists('low_stock_threshold', $data) && $data['low_stock_threshold'] !== null) {
            Setting::syncInventoryStatuses();
        }

        AuditLogger::log($request->user(), 'settings_updated', 'settings', null, implode(', ', array_keys($data)));

        return response()->json(Setting::allKeyed());
    }
}
