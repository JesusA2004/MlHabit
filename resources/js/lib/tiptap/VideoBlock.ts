import { Node } from '@tiptap/core';
import type { CommandProps } from '@tiptap/core';

export interface VideoBlockAttrs {
    mediaId: number | null;
    src: string | null;
    poster: string | null;
}

/**
 * Video embebido desde la biblioteca multimedia propia (no YouTube ni
 * ningún CDN externo). Se reproduce solo con interacción del visitante
 * (sin autoplay con sonido), con controles nativos del navegador.
 */
export const VideoBlock = Node.create({
    name: 'videoBlock',
    group: 'block',
    atom: true,

    addAttributes() {
        return {
            mediaId: { default: null },
            src: { default: null },
            poster: { default: null },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-type="video-block"]' }];
    },

    renderHTML({ HTMLAttributes }) {
        const { src, poster } = HTMLAttributes as VideoBlockAttrs;

        return [
            'div',
            {
                'data-type': 'video-block',
                'data-media-id': HTMLAttributes.mediaId,
                class: 'video-block',
            },
            [
                'video',
                {
                    controls: 'true',
                    preload: 'metadata',
                    src: src ?? undefined,
                    poster: poster ?? undefined,
                    class: 'w-full rounded-lg',
                },
            ],
        ];
    },

    addCommands() {
        return {
            insertVideoBlock:
                (attrs: VideoBlockAttrs) =>
                ({ chain }: CommandProps) =>
                    chain().insertContent({ type: this.name, attrs }).run(),
        };
    },
});

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        videoBlock: {
            insertVideoBlock: (attrs: VideoBlockAttrs) => ReturnType;
        };
    }
}
