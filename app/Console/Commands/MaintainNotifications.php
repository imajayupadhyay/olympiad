<?php

namespace App\Console\Commands;

use App\Services\Notifications\BirdWhatsAppGateway;
use App\Services\Notifications\NotificationBroadcastService;
use App\Services\Notifications\WhatsAppNotificationChannel;
use Illuminate\Console\Command;

/**
 * Keeps admin broadcasts moving (workflow.md §13.2, scheduled every 5 minutes):
 * releases deliveries stuck in `sending` (worker died), re-queues broadcasts with
 * pending deliveries and no recent progress, and pulls delivered/read/failed status
 * from Bird for recent WhatsApp messages.
 */
class MaintainNotifications extends Command
{
    protected $signature = 'notifications:maintain {--sync-limit=300 : WhatsApp messages to status-check per run}';

    protected $description = 'Resume stalled notification broadcasts and sync WhatsApp delivery status from Bird';

    public function handle(NotificationBroadcastService $broadcasts, WhatsAppNotificationChannel $whatsapp, BirdWhatsAppGateway $bird): int
    {
        $limit = $bird->isConfigured() ? max(0, (int) $this->option('sync-limit')) : 0;
        $result = $broadcasts->maintain($whatsapp, $limit);

        $this->info("Released {$result['released']} stale claim(s), resumed {$result['resumed']} broadcast(s), synced {$result['synced']} WhatsApp status(es).");

        return self::SUCCESS;
    }
}
