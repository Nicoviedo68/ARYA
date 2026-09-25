<?php

declare(strict_types=1);

namespace Arya\Controllers;

use Arya\Core\Controller;
use Arya\Models\ContentGenerator;

final class GeneratorController extends Controller
{
    public function index(): void
    {
        $this->requireMenuAccess('generator');
        ContentGenerator::bootstrap();

        $this->view('generator/index', [
            'title'   => 'Generador de contenido',
            'active'  => 'generator',
            'result'  => flash('generated'),
            'stats'   => ContentGenerator::stats(),
            'recent'  => ContentGenerator::recent(8),
        ]);
    }

    public function create(): void
    {
        $this->requireMenuAccess('generator');
        $this->validateCsrf();

        $platform = trim((string) ($_POST['platform'] ?? 'instagram'));
        $tone     = trim((string) ($_POST['tone'] ?? 'profesional'));
        $topic    = trim((string) ($_POST['topic'] ?? ''));
        $cta      = trim((string) ($_POST['cta'] ?? ''));

        $generated = $this->buildDemoContent($platform, $tone, $topic, $cta);

        $user = auth();
        ContentGenerator::save([
            'platform'   => $generated['platform'],
            'tone'       => $generated['tone'],
            'topic'      => $generated['topic'],
            'cta'        => $cta,
            'caption'    => $generated['caption'],
            'hashtags'   => $generated['hashtags'],
            'hooks'      => $generated['hooks'],
            'source'     => 'demo',
            'created_by' => isset($user['id']) ? (int) $user['id'] : null,
        ]);

        flash('generated', $generated);
        flash('success', 'Contenido generado y guardado en historial.');
        redirect('generador');
    }

    private function buildDemoContent(string $platform, string $tone, string $topic, string $cta): array
    {
        $topic = $topic !== '' ? $topic : 'nuestras soluciones CRM omnicanal';
        $cta   = $cta !== '' ? $cta : 'Escríbenos y agenda tu demo';

        $caption = match ($tone) {
            'cercano' => "¡Hey! 👋 Hoy te contamos sobre {$topic}. En Arya unimos WhatsApp, Instagram, Facebook y web en un solo lugar para que no se te escape ni una conversación.\n\n{$cta} ✨",
            'urgente' => "⏰ No dejes pasar más oportunidades. Con {$topic} respondes más rápido y cierras más ventas.\n\n{$cta} ahora.",
            default   => "Descubre cómo {$topic} transforman la atención al cliente. Arya centraliza tus canales, automatiza tareas y te entrega insights accionables.\n\n{$cta}.",
        };

        $hashtags = '#AryaCRM #Omnicanalidad #AtenciónAlCliente #MarketingDigital #Automatización';

        return [
            'platform' => $platform,
            'tone'     => $tone,
            'topic'    => $topic,
            'caption'  => $caption,
            'hashtags' => $hashtags,
            'hooks'    => [
                '¿Cuántas conversaciones perdiste hoy por no estar en el canal correcto?',
                'Un solo inbox. Todos tus canales. Cero caos.',
                'De lead a cliente sin cambiar de pantalla.',
            ],
        ];
    }
}
