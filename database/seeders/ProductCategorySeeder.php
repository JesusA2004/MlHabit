<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * CONFIGURACIÓN (estructura): categorías del catálogo SIN publicar, con
 * una sola línea neutral. Sin productos, imágenes ni datos inventados: el
 * equipo completa y publica cada categoría desde Administración → Catálogo.
 * Idempotente: no toca categorías existentes.
 */
class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'cocinas-integrales' => ['Cocinas integrales', 'Cocinas diseñadas a la medida de tu espacio.'],
            'closets' => ['Clósets', 'Clósets y vestidores a la medida.'],
            'recamaras' => ['Recámaras', 'Muebles a la medida para tu recámara.'],
            'centros-de-entretenimiento' => ['Centros de entretenimiento', 'Centros de entretenimiento a la medida de tu sala.'],
            'zapateras' => ['Zapateras', 'Zapateras a la medida para ordenar tu calzado.'],
            'muebles-a-la-medida' => ['Muebles a la medida', 'Otros muebles diseñados para tu espacio.'],
        ];

        $sort = 10;
        foreach ($categories as $slug => [$name, $tagline]) {
            ProductCategory::withTrashed()->firstOrCreate(['slug' => $slug], [
                'name' => $name,
                'tagline' => $tagline,
                'sort' => $sort,
                'is_published' => false,
                'is_featured' => false,
            ]);
            $sort += 10;
        }
    }
}
