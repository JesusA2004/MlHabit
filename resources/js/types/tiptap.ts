/**
 * Documento ProseMirror/Tiptap serializado a JSON. Se tipa como
 * `Record<string, any>` a propósito: es la forma más simple de satisfacer
 * las restricciones de `FormDataConvertible` de Inertia (que no aceptan
 * `unknown`) sin perder la verdad de que el contenido es un árbol JSON
 * anidado arbitrario definido por el esquema de nodos de Tiptap.
 */
export type TiptapContent = Record<string, any>;
