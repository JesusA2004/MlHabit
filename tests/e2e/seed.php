<?php

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Models\StudioPage;
use App\Models\User;
use App\Services\MediaUploadService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;

// Prepara la BD SQLite aislada para pruebas E2E de Studio (no toca MySQL).
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Salvaguarda: este script crea un usuario y datos de prueba. Solo se
// permite sobre una base SQLite desechable, nunca sobre la base real.
if (config('database.default') !== 'sqlite') {
    fwrite(STDERR, "seed.php solo se ejecuta con DB_CONNECTION=sqlite (base desechable).\n");
    exit(1);
}

// Las páginas de Studio se regeneran después de sembrar el contenido de prueba.
StudioPage::query()->delete();

$admin = User::query()->create(['name' => 'Admin E2E', 'email' => 'e2e@mrinsight.test', 'password' => 'secreto-e2e-123', 'role' => 'admin']);
$admin->forceFill(['email_verified_at' => now()])->save();

$uploader = app(MediaUploadService::class);
function img($w, $h, $c1, $c2, $label)
{
    $im = imagecreatetruecolor($w, $h);
    [$r1,$g1,$b1] = sscanf($c1, '#%02x%02x%02x');
    [$r2,$g2,$b2] = sscanf($c2, '#%02x%02x%02x');
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $col = imagecolorallocate($im, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t));
        imageline($im, 0, $y, $w, $y, $col);
    }
    $gold = imagecolorallocate($im, 199, 170, 125);
    imagefilledellipse($im, (int) ($w * 0.62), (int) ($h * 0.38), (int) ($w * 0.38), (int) ($w * 0.38), $gold);
    imagestring($im, 5, 20, $h - 30, $label, imagecolorallocate($im, 244, 241, 235));
    $path = tempnam(sys_get_temp_dir(), 'e2e').'.jpg';
    imagejpeg($im, $path, 85);
    imagedestroy($im);

    return new UploadedFile($path, "$label.jpg", 'image/jpeg', null, true);
}
$media = [];
foreach ([['retrato', 1200, 1500, '#2a2d33', '#0d0e10'], ['paisaje', 1800, 1200, '#3b3326', '#16181b'], ['habitos', 1600, 1000, '#1f2a24', '#0d0e10'], ['negocios', 1600, 1000, '#26242f', '#0d0e10'], ['vida', 1600, 1000, '#33261f', '#0d0e10'], ['galeria-1', 1200, 1200, '#2d2a24', '#111214']] as [$n, $w, $h, $a, $b]) {
    $m = $uploader->upload(img($w, $h, $a, $b, $n), 'e2e-pruebas');
    $m->update(['alt_text' => "Imagen de prueba $n"]);
    $media[$n] = $m;
}

SiteSetting::set('hero', [...SiteSetting::get('hero', []), 'photo_media_id' => $media['retrato']->id]);
foreach (Category::all() as $i => $cat) {
    foreach (range(1, 2) as $k) {
        Post::query()->create([
            'category_id' => $cat->id, 'author_id' => $admin->id,
            'cover_media_id' => $media[['negocios', 'habitos', 'vida'][$i % 3]]->id,
            'title' => "{$cat->name}: idea número $k para avanzar sin tapujos",
            'slug' => "e2e-{$cat->slug}-$k", 'excerpt' => 'Un extracto breve y directo que invita a leer el artículo completo.',
            'body' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Cuerpo del artículo de prueba.']]]]],
            'status' => 'published', 'is_featured' => $k === 1, 'published_at' => now()->subDays($i * 2 + $k),
        ]);
    }
}
Page::query()->where('key', 'mi-historia')->update(['intro' => 'Crecí entre números y libretas. Aquí cuento cómo aprendí a construir hábitos que sostienen un negocio y una vida.', 'cover_media_id' => $media['paisaje']->id]);
$proceso = Page::query()->where('key', 'mi-proceso')->first();
foreach (['Día 1: la decisión', 'Día 30: primeros resultados', 'Día 90: el hábito ya es mío'] as $i => $t) {
    $proceso->entries()->create(['title' => $t, 'entry_date' => now()->subDays(90 - $i * 30), 'is_visible' => true, 'order' => $i, 'cover_media_id' => $media['galeria-1']->id]);
}
SocialLink::query()->create(['platform' => 'instagram', 'username' => '@mrinsight', 'url' => 'https://instagram.com/mrinsight', 'is_visible' => true, 'open_new_tab' => true, 'order' => 1]);
SocialLink::query()->create(['platform' => 'tiktok', 'username' => '@mrinsight', 'url' => 'https://tiktok.com/@mrinsight', 'is_visible' => true, 'open_new_tab' => true, 'order' => 2]);
echo 'OK media: '.implode(',', array_map(fn ($m) => $m->id, $media))."\n";
