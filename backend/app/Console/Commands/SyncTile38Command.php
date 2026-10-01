<?php

namespace App\Console\Commands;

use App\Services\Tile38Service;
use Illuminate\Console\Command;

class SyncTile38Command extends Command
{
    protected $signature = 'tile38:sync';

    protected $description = 'Sync active geofences and pharmacies from MySQL into Tile38';

    public function handle(Tile38Service $tile38): int
    {
        if (! $tile38->isEnabled()) {
            $this->warn('Tile38 is disabled. Set TILE38_ENABLED=true in .env');

            return self::FAILURE;
        }

        if (! $tile38->isAvailable()) {
            $this->error('Tile38 is not reachable at '.config('tile38.host').':'.config('tile38.port'));
            $this->line('Start tile38-server.exe, then run this command again.');

            return self::FAILURE;
        }

        $result = $tile38->syncAll();

        $this->info('Tile38 sync complete.');
        $this->line("  Geofences: {$result['geofences']}");
        $this->line("  Pharmacies: {$result['pharmacies']}");

        return self::SUCCESS;
    }
}
