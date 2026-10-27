<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Collection;

/**
 * Convierte modelos Post en los arreglos planos que consumen las plantillas
 * Inertia del sitio público (y la previsualización autorizada del admin),
 * para no repetir el mismo mapeo en cada controlador.
 */
class PostPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'publishedAt' => $post->published_at?->toIso8601String(),
            'category' => $post->category ? [
                'name' => $post->category->name,
                'slug' => $post->category->slug,
                'color' => $post->category->color,
            ] : null,
            'coverUrl' => $post->cover?->url(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'publishedAt' => $post->published_at?->toIso8601String(),
            'author' => $post->author?->name,
            'category' => $post->category ? [
                'name' => $post->category->name,
                'slug' => $post->category->slug,
                'color' => $post->category->color,
            ] : null,
            'coverUrl' => $post->cover?->url(),
            'gallery' => $post->galleryMedia
                ->map(fn ($m) => ['id' => $m->id, 'url' => $m->url(), 'alt' => $m->alt_text])
                ->values(),
        ];
    }

    /**
     * Calcula el tiempo de lectura estimado (en minutos, mínimo 1) recorriendo
     * el cuerpo Tiptap y contando palabras a ~220 palabras por minuto.
     */
    public function readingMinutes(Post $post): int
    {
        $words = preg_split('/\s+/', trim($this->plainText($post->body ?? []))) ?: [];
        $count = count(array_filter($words, fn (string $word) => $word !== ''));

        return max(1, (int) round($count / 220));
    }

    /**
     * Concatena de forma recursiva el texto plano de un nodo Tiptap.
     *
     * @param  array<string, mixed>  $node
     */
    private function plainText(array $node): string
    {
        $text = '';

        if (isset($node['text']) && is_string($node['text'])) {
            $text .= ' '.$node['text'];
        }

        foreach ((array) ($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                $text .= ' '.$this->plainText($child);
            }
        }

        return $text;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function related(Post $post, int $limit = 3): Collection
    {
        return $post->relatedPosts()
            ->take($limit)
            ->map(fn (Post $p) => $this->relatedItem($p));
    }

    /**
     * @return array<string, mixed>
     */
    private function relatedItem(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'coverUrl' => $post->cover?->url(),
            'category' => $post->category ? [
                'name' => $post->category->name,
                'slug' => $post->category->slug,
                'color' => $post->category->color,
            ] : null,
        ];
    }
}
