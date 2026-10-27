<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\ActivityLogger;
use App\Services\PostService;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    protected $signature = 'mrinsight:publish-scheduled';

    protected $description = 'Publica automáticamente las publicaciones programadas cuya fecha ya llegó';

    public function handle(PostService $posts, ActivityLogger $activity): int
    {
        $due = Post::query()
            ->where('status', PostStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $post) {
            $posts->publish($post);
            $activity->log('publicacion.publicada_automaticamente', $post, ['title' => $post->title]);
            $this->info("Publicado: {$post->title}");
        }

        if ($due->isEmpty()) {
            $this->info('No hay publicaciones programadas pendientes.');
        }

        return self::SUCCESS;
    }
}
