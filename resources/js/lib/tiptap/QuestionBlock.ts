import { Node } from '@tiptap/core';
import type { CommandProps } from '@tiptap/core';

/**
 * Bloque "Pregunta final": invita al lector a responder antes de cerrar
 * la publicación. Igual que TipBlock, es un nodo propio, no HTML libre.
 */
export const QuestionBlock = Node.create({
    name: 'questionBlock',
    group: 'block',
    content: 'paragraph+',
    defining: true,

    parseHTML() {
        return [{ tag: 'div[data-type="question-block"]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'div',
            {
                ...HTMLAttributes,
                'data-type': 'question-block',
                class: 'question-block',
            },
            0,
        ];
    },

    addCommands() {
        return {
            insertQuestionBlock:
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
                                            text: '¿Y tú, qué harías?',
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
        questionBlock: {
            insertQuestionBlock: () => ReturnType;
        };
    }
}
