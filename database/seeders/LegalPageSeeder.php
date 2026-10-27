<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * CONFIGURACIÓN: estructura de las páginas legales como BORRADORES. El
 * texto legal real debe redactarlo y revisarlo un profesional; estas
 * páginas nunca se publican automáticamente. Idempotente.
 */
class LegalPageSeeder extends Seeder
{
    private const NOTE = '<p><strong>Contenido pendiente de revisión legal.</strong> Esta página es solo una estructura de trabajo y no debe publicarse hasta que el texto definitivo sea revisado por un profesional.</p>';

    public function run(): void
    {
        $pages = [
            'aviso-de-privacidad' => [
                'title' => 'Aviso de privacidad',
                'summary' => 'Cómo tratamos y protegemos tus datos personales.',
                'sections' => ['Responsable del tratamiento', 'Datos que recabamos', 'Finalidades', 'Transferencias', 'Derechos ARCO y cómo ejercerlos', 'Cambios a este aviso'],
            ],
            'terminos-y-condiciones' => [
                'title' => 'Términos y condiciones',
                'summary' => 'Condiciones de uso del sitio y de nuestros servicios.',
                'sections' => ['Uso del sitio', 'Asesorías y visitas técnicas', 'Cotizaciones y contratos', 'Pagos', 'Garantía', 'Contacto'],
            ],
            'politica-de-cancelacion-y-reprogramacion' => [
                'title' => 'Política de cancelación y reprogramación',
                'summary' => 'Qué pasa si necesitas cancelar o mover una cita o visita.',
                'sections' => ['Asesorías', 'Visitas técnicas', 'Reembolsos', 'Instalaciones'],
            ],
        ];

        foreach ($pages as $slug => $page) {
            $body = self::NOTE;
            foreach ($page['sections'] as $heading) {
                $body .= '<h2>'.e($heading).'</h2><p>Pendiente de redacción.</p>';
            }

            Page::query()->firstOrCreate(['slug' => $slug], [
                'title' => $page['title'],
                'summary' => $page['summary'],
                'body' => $body,
                'version' => 1,
                'is_published' => false,
                'show_in_footer' => true,
                'published_at' => null,
            ]);
        }
    }
}
