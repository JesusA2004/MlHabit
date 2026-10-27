<?php

namespace App\Cms\Sections;

use App\Cms\Field;

/**
 * Formas de pago. Nunca promete crédito: la preferencia de pagar en
 * parcialidades es distinta de la capacidad real, que siempre se evalúa.
 * El validador rechaza frases que sugieren aprobación garantizada.
 */
final class PaymentOptionsSection extends SectionSchema
{
    /** Frases que no se permiten en ningún texto de esta sección. */
    public const FORBIDDEN = '/(cr[eé]dito\s+(pre)?aprobado|aprobaci[oó]n\s+(garantizada|inmediata|segura)|sin\s+revisar\s+bur[oó]|sin\s+bur[oó]|aprobado\s+al\s+100)/iu';

    public function label(): string
    {
        return 'Formas de pago';
    }

    public function description(): string
    {
        return 'Opciones para planear el pago del proyecto. Los pagos a plazos siempre se presentan como sujetos a evaluación.';
    }

    public function fields(): array
    {
        return [
            Field::text('eyebrow', 'Antetítulo', 'Formas de pago', max: 80),
            Field::text('title', 'Título', 'Opciones para planear tu proyecto', required: true, max: 120),
            Field::textarea('body', 'Texto de apoyo', 'Platícanos cómo prefieres pagar. Si te interesa hacerlo en parcialidades, lo revisamos contigo durante la asesoría.', max: 400),
            Field::list('items', 'Opciones', [
                Field::text('title', 'Opción', required: true, max: 80),
                Field::textarea('body', 'Descripción', max: 300),
            ], [
                ['title' => 'Anticipo y saldo', 'body' => 'Tu proyecto se formaliza con un anticipo. El porcentaje y las fechas de pago quedan por escrito en tu contrato.'],
                ['title' => 'Pago con tarjeta', 'body' => 'Puedes pagar en línea con tarjeta de forma segura. Otras formas de pago se acuerdan con tu asesor.'],
                ['title' => 'Pagos en parcialidades', 'body' => 'Si prefieres pagar en parcialidades, revisamos tu caso. Tu preferencia no implica una aprobación: cada solicitud se evalúa de forma individual.'],
            ], min: 1, max: 6),
            Field::textarea('disclaimer', 'Aviso obligatorio', 'Las opciones de pago a plazos están sujetas a evaluación y no representan una aprobación de crédito.', required: true, max: 400, help: 'Se muestra siempre debajo de las opciones.'),
            Field::text('cta_label', 'Texto del botón', 'Consulta las opciones con un asesor', max: 60),
            Field::link('cta_href', 'Enlace del botón', '/solicitar'),
        ];
    }

    protected function extraRules(): array
    {
        $rule = 'not_regex:'.self::FORBIDDEN;

        return [
            'title' => [$rule],
            'body' => [$rule],
            'items.*.title' => [$rule],
            'items.*.body' => [$rule],
            'disclaimer' => [$rule],
            'cta_label' => [$rule],
        ];
    }

    protected function messages(): array
    {
        return [
            'not_regex' => 'No prometas aprobaciones de crédito: las opciones de pago a plazos siempre están sujetas a evaluación.',
        ];
    }
}
