import { Node } from '@tiptap/core';
import type { CommandProps } from '@tiptap/core';

/**
 * Bloque "EL TIP": un párrafo destacado dentro del cuerpo editorial.
 * Se guarda como un nodo ProseMirror propio (no HTML libre), consistente
 * con el requisito de contenido estructurado y saneado.
 */
export const TipBlock = Node.create({
    name: 'tipBlock',
    group: 'block',
    content: 'paragraph+',
    defining: true,

    parseHTML() {
        return [{ tag: 'div[data-type="tip-block"]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'div',
            { ...HTMLAttributes, 'data-type': 'tip-block', class: 'tip-block' },
            0,
        ];
    },

    addCommands() {
        return {
            insertTipBlock:
                () =>
                ({ chain }: CommandProps) =>
                    chain()
                        .insertContent({
                            type: this.name,
                            content: [
                                {
                                    type: 'paragraph',
                                    content: [
                                        {
                                            type: 'text',
                                            text: 'Escribe aquí el tip…',
                                        },
                                    ],
                                },
                            ],
                        })
                        .run(),
        };
    },
});

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        tipBlock: {
            insertTipBlock: () => ReturnType;
        };
    }
}
