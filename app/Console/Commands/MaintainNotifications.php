<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationBroadcastService;
use App\Services\Notifications\WhatsAppNotificationChannel;
use Illuminate\Console\Command;

/**
 * Keeps admin broadcasts moving (workflow.md §13.2, scheduled every 5 minutes):
 * releases deliveries stuck in `sending` (worker died), re-queues broadcasts with
 * pending deliveries and no recent progress, and pulls delivered/read/failed status
 * for recent WhatsApp messages from the provider that sent each one (§13.6).
 */
class MaintainNotifications extends Command
{
    protected $signature = 'notifications:maintain {--sync-limit=300 : WhatsApp messages to status-check per run}';

    protected $description = 'Resume stalled notification broadcasts and sync WhatsApp delivery status from the providers';

    public function handle(NotificationBroadcastService $broadcasts, WhatsAppNotificationChannel $whatsapp): int
    {
        // Providers that aren't configured or can't look up status are skipped inside.
        $limit = max(0, (int) $this->option('sync-limit'));
        $result = $broadcasts->maintain($whatsapp, $limit);

        $this->info("Released {$result['released']} stale claim(s), resumed {$result['resumed']} broadcast(s), synced {$result['synced']} WhatsApp status(es).");

        return self::SUCCESS;
    }
}
