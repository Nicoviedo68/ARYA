<?php

declare(strict_types=1);

namespace Arya\Controllers;

use Arya\Core\Controller;
use Arya\Models\Client;
use Arya\Models\ContentGenerator;
use Arya\Models\Dashboard;
use Arya\Models\Omnichannel;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireMenuAccess('dashboard');

        Omnichannel::bootstrap();
        Client::bootstrap();
        ContentGenerator::bootstrap();
        // Sync ligero con TTL alto: no bloquear cada visita al dashboard
        Client::syncIfNeeded(180);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $lastInbox = (int) ($_SESSION['dash_inbox_sync_at'] ?? 0);
            if ($lastInbox === 0 || (time() - $lastInbox) >= 60) {
                Omnichannel::syncPendingIfAny();
                $_SESSION['dash_inbox_sync_at'] = time();
            }
        } else {
            Omnichannel::syncPendingIfAny();
        }

        $this->view('dashboard/index', [
            'title'         => 'Dashboard',
            'active'        => 'dashboard',
            'stats'         => Dashboard::stats(),
            'channelKpis'   => Dashboard::channelKpis(),
            'conversations' => Dashboard::recentInbox(5),
            'chart'         => Dashboard::chartConversations(),
            'clientsMod'    => Dashboard::clientModule(),
            'tasksMod'      => Dashboard::taskModule(),
            'generatorMod'  => Dashboard::generatorModule(),
            'reportKpis'    => Dashboard::reportKpis(),
        ]);
    }
}
