import type { InjectionKey } from 'vue';
import type { Editor } from './useEditor';

/** Estado del editor visual compartido por sus paneles. */
export const editorKey: InjectionKey<Editor> = Symbol('sb-editor-store');
