<?php

declare(strict_types=1);

namespace Arya\Controllers;

use Arya\Core\Controller;
use Arya\Models\Dashboard;
use Arya\Models\Omnichannel;

final class ReportController extends Controller
{
    public function index(): void
    {
        $this->requireMenuAccess('reports');
        Omnichannel::bootstrap();

        $this->view('reports/index', [
            'title'    => 'Informes',
            'active'   => 'reports',
            'kpis'     => Dashboard::reportKpis(),
            'chart'    => Dashboard::chartConversations(),
            'channels' => Dashboard::reportChannels(),
        ]);
    }

    public function export(): void
    {
        $this->requireMenuAccess('reports');
        Omnichannel::bootstrap();

        $filename = 'arya_informe_' . date('Y-m-d_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($out, ['Canal', 'Cliente', 'Contacto', 'Vista previa', 'Hora', 'No leídos', 'Estado', 'Tipo'], ';');

        foreach (Omnichannel::conversations('all') as $row) {
            fputcsv($out, [
                $row['channel'],
                $row['client'],
                $row['phone'],
                $row['preview'],
                $row['time'],
                $row['unread'],
                $row['status'],
                $row['thread_kind'] ?? 'message',
            ], ';');
        }

        fclose($out);
        exit;
    }
}
