<?php

namespace App\Cms\Layout;

use Illuminate\Support\Str;

/**
 * Catálogo de bloques del editor visual y constructores de lienzos.
 */
final class Blocks
{
    /** Bloques de contenido libre. */
    public const STATIC = ['heading', 'text', 'button', 'image', 'icon', 'badge', 'shape', 'spacer', 'video', 'stat', 'list'];

    /** @return list<string> */
    public static function types(): array
    {
        return [...self::STATIC, ...DynamicBlocks::types()];
    }

    public static function exists(string $type): bool
    {
        return in_array($type, self::STATIC, true) || DynamicBlocks::exists($type);
    }

    /** @return array<string, mixed> */
    public static function defaultProps(string $type): array
    {
        return match ($type) {
            'heading' => ['text' => '', 'level' => 'h2', 'size' => 'lg', 'color' => 'auto'],
            'text' => ['html' => '', 'size' => 'base', 'color' => 'auto'],
            'button' => ['label' => 'Solicita una asesoría', 'href' => '/solicitar', 'variant' => 'primary', 'size' => 'lg', 'icon' => true],
            'image' => ['mediaId' => null, 'alt' => '', 'fit' => 'cover', 'focalX' => 50, 'focalY' => 50, 'zoom' => 1.0, 'showOrigin' => true],
            'icon' => ['name' => 'sparkles', 'size' => 'md', 'color' => 'primary', 'framed' => true],
            'badge' => ['text' => '', 'variant' => 'eyebrow', 'color' => 'auto'],
            'shape' => ['kind' => 'rect', 'color' => 'primary-soft'],
            'spacer' => [],
            'video' => ['mediaId' => null, 'autoplay' => true, 'loop' => true, 'controls' => false],
            'stat' => ['value' => '', 'label' => '', 'color' => 'auto'],
            'list' => ['items' => [], 'marker' => 'check', 'color' => 'auto'],
            default => DynamicBlocks::exists($type) ? DynamicBlocks::schema($type)->defaults() : [],
        };
    }

    /** @return array{align: string, radius: string, shadow: string, opacity: int, rotate: int} */
    public static function defaultStyle(): array
    {
        return ['align' => 'start', 'radius' => 'none', 'shadow' => 'none', 'opacity' => 100, 'rotate' => 0];
    }

    /** @return array<string, mixed> */
    public static function defaultSettings(): array
    {
        return [
            'name' => '',
            'background' => ['kind' => 'none', 'color' => 'background', 'mediaId' => null, 'overlay' => 0, 'overlayStyle' => 'flat', 'gradient' => 'linen-stone'],
            'padding' => 'lg',
            'minHeight' => 'auto',
            'verticalAlign' => 'start',
            'fullBleed' => false,
            'surface' => 'none',
            'anchor' => '',
            'tone' => 'auto',
            'overlayHeader' => false,
            'journey' => '',
        ];
    }

    public static function newId(): string
    {
        return 'b'.Str::lower(Str::random(9));
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $style
     * @param  array<string, mixed>  $motion
     * @param  list<string>  $hideOn
     * @return array<string, mixed>
     */
    public static function make(string $type, int $x, int $y, int $w, int $h, array $props = [], array $style = [], array $motion = [], array $hideOn = [], int $z = 1): array
    {
        return [
            'id' => self::newId(),
            'type' => $type,
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h,
            'z' => $z,
            'props' => [...self::defaultProps($type), ...$props],
            'style' => [...self::defaultStyle(), ...$style],
            'motion' => ['preset' => 'fade-up', 'delay' => 0, ...$motion],
            'hideOn' => $hideOn,
            'locked' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public static function layout(array $settings, array $blocks): array
    {
        $defaults = self::defaultSettings();
        $settings = [...$defaults, ...$settings];
        $settings['background'] = [...$defaults['background'], ...(array) ($settings['background'] ?? [])];

        return ['version' => Grid::LAYOUT_VERSION, 'settings' => $settings, 'blocks' => array_values($blocks)];
    }

    /** Párrafos HTML a partir de texto plano. */
    public static function paragraphs(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return implode('', array_map(
            fn (string $p) => '<p>'.e(trim($p)).'</p>',
            preg_split('/\n{2,}/', $text) ?: [$text],
        ));
    }
}
