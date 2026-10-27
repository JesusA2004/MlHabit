<?php

namespace App\Services;

use App\Enums\PostMediaRole;
use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PostService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $authorId): Post
    {
        $post = Post::query()->create([
            'category_id' => $data['category_id'],
            'author_id' => $authorId,
            'cover_media_id' => $data['cover_media_id'] ?? null,
            'title' => $data['title'],
            'slug' => $data['slug'] ?? $this->uniqueSlug($data['title']),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'] ?? [],
            'status' => PostStatus::Draft,
            'is_featured' => $data['is_featured'] ?? false,
            'editorial_order' => $data['editorial_order'] ?? 0,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'tags' => $data['tags'] ?? null,
            'related_post_ids' => $data['related_post_ids'] ?? null,
        ]);

        $this->syncMedia($post, $data);

        return $post;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Post $post, array $data): Post
    {
        $post->update([
            'category_id' => $data['category_id'],
            'cover_media_id' => $data['cover_media_id'] ?? null,
            'title' => $data['title'],
            'slug' => $data['slug'] ?? $post->slug,
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'] ?? $post->body,
            'is_featured' => $data['is_featured'] ?? $post->is_featured,
            'editorial_order' => $data['editorial_order'] ?? $post->editorial_order,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'tags' => $data['tags'] ?? null,
            'related_post_ids' => array_key_exists('related_post_ids', $data) ? $data['related_post_ids'] : $post->related_post_ids,
        ]);

        $this->syncMedia($post, $data);

        return $post;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncMedia(Post $post, array $data): void
    {
        $sync = [];

        foreach ((array) ($data['gallery_media_ids'] ?? []) as $order => $mediaId) {
            $sync[(int) $mediaId] = ['role' => PostMediaRole::Gallery->value, 'order' => $order];
        }

        if (! empty($data['video_media_id'])) {
            $sync[(int) $data['video_media_id']] = ['role' => PostMediaRole::Video->value, 'order' => 0];
        }

        $post->media()->sync($sync);
    }

    public function publish(Post $post): void
    {
        $post->update([
            'status' => PostStatus::Published,
            'published_at' => $post->published_at ?? Carbon::now(),
            'scheduled_at' => null,
        ]);
    }

    public function schedule(Post $post, Carbon $at): void
    {
        $post->update([
            'status' => PostStatus::Scheduled,
            'scheduled_at' => $at,
            'published_at' => null,
        ]);
    }

    public function unpublish(Post $post): void
    {
        $post->update(['status' => PostStatus::Draft]);
    }

    public function archive(Post $post): void
    {
        $post->update(['status' => PostStatus::Archived]);
    }

    public function toggleFeatured(Post $post): bool
    {
        $post->update(['is_featured' => ! $post->is_featured]);

        return $post->is_featured;
    }

    public function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'tip';
        $slug = $base;
        $i = 1;

        while (Post::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
