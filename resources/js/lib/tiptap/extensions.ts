import type { Extensions } from '@tiptap/core';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Underline from '@tiptap/extension-underline';
import StarterKit from '@tiptap/starter-kit';
import { QuestionBlock } from '@/lib/tiptap/QuestionBlock';
import { TipBlock } from '@/lib/tiptap/TipBlock';
import { VideoBlock } from '@/lib/tiptap/VideoBlock';

export function createPostExtensions(editable: boolean): Extensions {
    const extensions: Extensions = [
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
            strike: false,
            codeBlock: false,
            code: false,
        }),
        Underline,
        Link.configure({ openOnClick: false, autolink: true }),
        Image.configure({ inline: false, allowBase64: false }),
        TipBlock,
        QuestionBlock,
        VideoBlock,
    ];

    if (editable) {
        extensions.push(
            Placeholder.configure({
                placeholder: 'Escribe el contenido de tu tip…',
            }),
        );
    }

    return extensions;
}
