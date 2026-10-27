<?php

namespace Database\Seeders;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Media;
use App\Models\PageView;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeder de CONTENIDO DEMO (solo desarrollo) para poder probar visualmente la
 * página de un Tip (post) en el navegador. Es idempotente: al re-ejecutarse
 * borra su propio contenido anterior (autor demo + archivos en
 * mr-insight/demo/) y lo vuelve a crear desde cero.
 *
 * No toca el flujo de creación de administrador (mrinsight:create-admin) ni el
 * contenido editorial real: solo crea un usuario autor identificado por email.
 */
class DemoContentSeeder extends Seeder
{
    private const AUTHOR_EMAIL = 'demo@mrinsight.com.mx';

    private const MEDIA_PREFIX = 'mr-insight/demo/';

    private const DISK = 'media_local';

    public function run(): void
    {
        $this->cleanupPrevious();

        $author = $this->author();
        $categories = $this->categories();
        $covers = $this->makeCovers();      // 6 imágenes 1600x1067
        $gallery = $this->makeGallery();    // 4 imágenes 1200x1200  => 10 en total

        $posts = $this->createPosts($author, $categories, $covers, $gallery);

        $this->linkRelated($posts);

        $views = $this->seedPageViews($posts);

        $this->command?->info('DemoContentSeeder: '.Post::query()->published()->count().' posts publicados, '.Media::query()->count().' media, '.$views.' visitas demo.');
    }

    private function cleanupPrevious(): void
    {
        // Se reinicia la analítica demo para no acumular visitas entre corridas.
        PageView::query()->delete();

        $author = User::query()->where('email', self::AUTHOR_EMAIL)->first();

        if ($author !== null) {
            // forceDelete para no acumular soft-deletes entre corridas.
            Post::query()->where('author_id', $author->id)->forceDelete();
        }

        $previous = Media::query()->where('path', 'like', self::MEDIA_PREFIX.'%')->get();
        foreach ($previous as $media) {
            // Limpia también las filas de pivote por si quedaron huérfanas.
            DB::table('post_media')->where('media_id', $media->id)->delete();
            $media->forceDelete();
        }

        $disk = Storage::disk(self::DISK);
        if ($disk->exists(rtrim(self::MEDIA_PREFIX, '/'))) {
            $disk->deleteDirectory(rtrim(self::MEDIA_PREFIX, '/'));
        }
    }

    private function author(): User
    {
        return User::query()->firstOrCreate(
            ['email' => self::AUTHOR_EMAIL],
            [
                'name' => 'Zuriel Ávila',
                'password' => 'password',
                'role' => UserRole::Editor,
                'email_verified_at' => Carbon::now(),
            ],
        );
    }

    /**
     * Reutiliza las categorías existentes (hay CategorySeeder). Solo crea las
     * base si la tabla está vacía.
     *
     * @return array<int, Category>
     */
    private function categories(): array
    {
        if (Category::query()->count() === 0) {
            $this->call(CategorySeeder::class);
        }

        return Category::query()->orderBy('order')->get()->all();
    }

    /**
     * @return array<int, Media>
     */
    private function makeCovers(): array
    {
        $palette = [
            ['#1f2a44', '#c7aa7d'],
            ['#2d2a26', '#aba9a3'],
            ['#102a2a', '#7dc7a3'],
            ['#2a1020', '#c77d9e'],
            ['#1a1a2e', '#7d9ec7'],
            ['#2a2410', '#c7b67d'],
        ];

        $covers = [];
        foreach ($palette as $i => $colors) {
            $n = $i + 1;
            $covers[] = $this->makeImage(
                relativePath: self::MEDIA_PREFIX.'cover-'.$n.'.jpg',
                width: 1600,
                height: 1067,
                from: $colors[0],
                to: $colors[1],
                label: 'MR. INSIGHT · Cover '.$n,
                title: 'Portada demo '.$n,
                alt: 'Imagen de portada de ejemplo '.$n,
            );
        }

        return $covers;
    }

    /**
     * @return array<int, Media>
     */
    private function makeGallery(): array
    {
        $palette = [
            ['#3a3f58', '#e0d4bd'],
            ['#403a36', '#d4d2cc'],
            ['#1f3a3a', '#bde0cf'],
            ['#3a1f30', '#e0bdce'],
        ];

        $gallery = [];
        foreach ($palette as $i => $colors) {
            $n = $i + 1;
            $gallery[] = $this->makeImage(
                relativePath: self::MEDIA_PREFIX.'gallery-'.$n.'.jpg',
                width: 1200,
                height: 1200,
                from: $colors[0],
                to: $colors[1],
                label: 'Galería '.$n,
                title: 'Galería demo '.$n,
                alt: 'Imagen de galería de ejemplo '.$n,
            );
        }

        return $gallery;
    }

    /**
     * Genera un JPEG real en disco (GD) con degradado vertical + etiqueta y
     * crea su fila Media correspondiente en estado "ready".
     */
    private function makeImage(
        string $relativePath,
        int $width,
        int $height,
        string $from,
        string $to,
        string $label,
        string $title,
        string $alt,
    ): Media {
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory(rtrim(self::MEDIA_PREFIX, '/'));
        $absolute = $disk->path($relativePath);

        $image = imagecreatetruecolor($width, $height);
        [$fr, $fg, $fb] = $this->hex($from);
        [$tr, $tg, $tb] = $this->hex($to);

        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / max(1, $height - 1);
            $r = (int) round($fr + ($tr - $fr) * $ratio);
            $g = (int) round($fg + ($tg - $fg) * $ratio);
            $b = (int) round($fb + ($tb - $fb) * $ratio);
            $color = imagecolorallocate($image, $r, $g, $b);
            imagefilledrectangle($image, 0, $y, $width, $y, $color);
        }

        // Etiqueta centrada con la fuente interna de GD (sin depender de TTF).
        $textColor = imagecolorallocate($image, 255, 255, 255);
        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 60);
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($label);
        $textHeight = imagefontheight($font);
        $x = (int) (($width - $textWidth) / 2);
        $y = (int) (($height - $textHeight) / 2);
        imagestring($image, $font, $x + 2, $y + 2, $label, $shadow);
        imagestring($image, $font, $x, $y, $label, $textColor);

        imagejpeg($image, $absolute, 85);
        imagedestroy($image);

        $size = (int) $disk->size($relativePath);
        $filename = basename($relativePath);

        return Media::query()->create([
            'disk' => self::DISK,
            'path' => $relativePath,
            'filename' => $filename,
            'original_filename' => $filename,
            'type' => MediaType::Image,
            'mime_type' => 'image/jpeg',
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'title' => $title,
            'alt_text' => $alt,
            'status' => MediaStatus::Ready,
            'metadata' => ['demo' => true],
        ]);
    }

    /**
     * @param  array<int, Category>  $categories
     * @param  array<int, Media>  $covers
     * @param  array<int, Media>  $gallery
     * @return array<int, Post>
     */
    private function createPosts(User $author, array $categories, array $covers, array $gallery): array
    {
        $definitions = [
            [
                'title' => 'El poder de empezar antes de estar listo',
                'excerpt' => 'Casi nadie empieza sintiéndose preparado. La claridad llega al avanzar, no antes. Aquí está por qué la acción imperfecta vence a la planificación eterna.',
                'featured' => true,
            ],
            [
                'title' => 'Hábitos que construyen una década',
                'excerpt' => 'No cambiamos la vida con grandes gestos, sino con decisiones pequeñas repetidas mil veces. Esto es lo que de verdad compone con los años.',
                'featured' => false,
            ],
            [
                'title' => 'Lo que aprendí perdiendo mi primer negocio',
                'excerpt' => 'Cerrar aquella empresa dolió más que cualquier examen reprobado. También me enseñó más que cualquier curso que haya pagado después.',
                'featured' => false,
            ],
            [
                'title' => 'La disciplina no es motivación',
                'excerpt' => 'Esperar a tener ganas es la forma más elegante de no hacer nada. La disciplina empieza justo donde la motivación se apaga.',
                'featured' => false,
            ],
            [
                'title' => 'Silencio: el lujo que nadie te vende',
                'excerpt' => 'Vivimos saturados de ruido, notificaciones y opiniones ajenas. Recuperar el silencio puede ser la decisión más productiva del año.',
                'featured' => false,
            ],
            [
                'title' => 'Dinero, tiempo y la trampa de la productividad',
                'excerpt' => 'Optimizar cada minuto no sirve de nada si corres en la dirección equivocada. Hablemos de eficacia antes que de eficiencia.',
                'featured' => false,
            ],
        ];

        $posts = [];
        foreach ($definitions as $i => $def) {
            $category = $categories[$i % count($categories)];
            $cover = $covers[$i];

            /** @var Post $post */
            $post = Post::factory()->published()->create([
                'category_id' => $category->id,
                'author_id' => $author->id,
                'cover_media_id' => $cover->id,
                'title' => $def['title'],
                'slug' => Str::slug($def['title']),
                'excerpt' => $def['excerpt'],
                'body' => $this->body($def['title'], $def['excerpt']),
                'is_featured' => $def['featured'],
                'editorial_order' => $i + 1,
                'published_at' => Carbon::now()->subDays(count($definitions) - $i),
                'seo_title' => $def['title'].' · MR. INSIGHT',
                'seo_description' => $def['excerpt'],
                'tags' => ['demo', 'mr-insight', Str::slug($category->name)],
            ]);

            // Galería: 3-4 imágenes rotando sobre las 4 disponibles.
            $count = 3 + ($i % 2); // 3 o 4
            $attach = [];
            for ($g = 0; $g < $count; $g++) {
                $media = $gallery[($i + $g) % count($gallery)];
                $attach[$media->id] = ['role' => 'gallery', 'order' => $g + 1];
            }
            $post->media()->attach($attach);

            $posts[] = $post;
        }

        return $posts;
    }

    /**
     * @param  array<int, Post>  $posts
     */
    private function linkRelated(array $posts): void
    {
        $ids = array_map(static fn (Post $p) => $p->id, $posts);

        foreach ($posts as $index => $post) {
            $related = [];
            for ($k = 1; $k <= 3; $k++) {
                $related[] = $ids[($index + $k) % count($ids)];
            }
            $post->update(['related_post_ids' => $related]);
        }
    }

    /**
     * Cuerpo Tiptap/ProseMirror multi-bloque: varios párrafos, un h2, una cita
     * y una lista con viñetas.
     *
     * @return array<string, mixed>
     */
    private function body(string $title, string $excerpt): array
    {
        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => $excerpt],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Durante años creí que necesitaba el momento perfecto, el plan completo y la certeza absoluta antes de mover un dedo. Spoiler: ese momento nunca llega. Lo que llega es la vida, con sus plazos, sus dudas y sus oportunidades que no esperan.'],
                    ],
                ],
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 2],
                    'content' => [
                        ['type' => 'text', 'text' => 'Lo que casi nadie te cuenta'],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'La claridad no es un requisito para empezar: es una consecuencia de haber empezado. Cada paso te da información que ningún análisis previo podía darte. Por eso la gente que avanza parece tener más suerte: en realidad tiene más iteraciones.'],
                    ],
                ],
                [
                    'type' => 'blockquote',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'No esperes a sentirte listo. Nadie lo está. Solo hay quienes empiezan y quienes siguen esperando.'],
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Si tuviera que resumir lo aprendido en tres ideas que repito casi a diario, serían estas:'],
                    ],
                ],
                [
                    'type' => 'bulletList',
                    'content' => [
                        [
                            'type' => 'listItem',
                            'content' => [[
                                'type' => 'paragraph',
                                'content' => [['type' => 'text', 'text' => 'Empieza pequeño, pero empieza hoy: la fecha ideal no existe.']],
                            ]],
                        ],
                        [
                            'type' => 'listItem',
                            'content' => [[
                                'type' => 'paragraph',
                                'content' => [['type' => 'text', 'text' => 'Mide el avance en decisiones tomadas, no en horas ocupadas.']],
                            ]],
                        ],
                        [
                            'type' => 'listItem',
                            'content' => [[
                                'type' => 'paragraph',
                                'content' => [['type' => 'text', 'text' => 'Protege tu energía: es el único recurso que no puedes recuperar.']],
                            ]],
                        ],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Al final, «'.$title.'» no va de técnicas ni de atajos. Va de dejar de negociar contigo mismo y empezar a actuar como la persona que dices querer ser.'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Genera visitas DEMO anónimas de los últimos 45 días para que el panel
     * de estadísticas tenga datos realistas con los que probarse: tendencia
     * ascendente, pico de fin de semana y reparto desigual entre tips y
     * páginas. No usa datos reales de visitantes.
     *
     * @param  array<int, Post>  $posts
     */
    private function seedPageViews(array $posts): int
    {
        $published = array_values(array_filter(
            $posts,
            fn (Post $post) => $post->status === PostStatus::Published,
        ));
        if ($published === []) {
            return 0;
        }

        // Pesos para repartir las visitas: ~55% a tips, el resto a páginas.
        $pages = [
            ['/', 'home', 'Inicio', 22],
            ['/tips', null, 'Tips', 10],
            ['/mi-historia', 'mi-historia', 'Mi historia', 7],
            ['/mi-proceso', 'mi-proceso', 'Mi proceso', 4],
            ['/redes', 'redes', 'Redes', 2],
        ];

        $referrers = [null, null, null, 'google.com', 'google.com', 'instagram.com', 'facebook.com', 'linkedin.com', 't.co'];
        $rows = [];
        $today = Carbon::now()->startOfDay();

        for ($d = 44; $d >= 0; $d--) {
            $date = $today->copy()->subDays($d);
            // Tendencia creciente + mayor tráfico en fin de semana.
            $base = (int) round(18 + (44 - $d) * 0.8);
            $weekend = in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true) ? 1.5 : 1.0;
            $count = max(3, (int) round($base * $weekend * (0.7 + mt_rand(0, 60) / 100)));

            for ($i = 0; $i < $count; $i++) {
                $visitor = hash('sha256', 'demo-'.$date->toDateString().'-'.mt_rand(1, (int) max(5, $count * 0.8)));

                if (mt_rand(1, 100) <= 55) {
                    // Visita a un tip (los primeros posts pesan más).
                    $idx = min(count($published) - 1, (int) floor(abs($this->skewed()) * count($published)));
                    $post = $published[$idx];
                    $rows[] = [
                        'path' => '/tips/'.$post->slug,
                        'post_id' => $post->id,
                        'studio_page_key' => 'tip',
                        'label' => $post->title,
                        'referrer_host' => $referrers[array_rand($referrers)],
                        'visitor_hash' => $visitor,
                        'is_unique' => $i === 0 || mt_rand(1, 100) <= 70,
                        'viewed_on' => $date->toDateString(),
                        'created_at' => $date->copy()->addMinutes(mt_rand(0, 1439)),
                    ];
                } else {
                    $page = $this->weighted($pages);
                    $rows[] = [
                        'path' => $page[0],
                        'post_id' => null,
                        'studio_page_key' => $page[1],
                        'label' => $page[2],
                        'referrer_host' => $referrers[array_rand($referrers)],
                        'visitor_hash' => $visitor,
                        'is_unique' => mt_rand(1, 100) <= 65,
                        'viewed_on' => $date->toDateString(),
                        'created_at' => $date->copy()->addMinutes(mt_rand(0, 1439)),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('page_views')->insert($chunk);
        }

        return count($rows);
    }

    /** Valor en [0,1) sesgado hacia 0 (favorece los primeros elementos). */
    private function skewed(): float
    {
        $r = mt_rand(0, 1000) / 1000;

        return $r * $r;
    }

    /**
     * Elige una página según su peso (último elemento de cada tupla).
     *
     * @param  list<array{0:string,1:?string,2:string,3:int}>  $pages
     * @return array{0:string,1:?string,2:string,3:int}
     */
    private function weighted(array $pages): array
    {
        $total = array_sum(array_column($pages, 3));
        $pick = mt_rand(1, max(1, $total));
        $acc = 0;
        foreach ($pages as $page) {
            $acc += $page[3];
            if ($pick <= $acc) {
                return $page;
            }
        }

        return $pages[0];
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function hex(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
