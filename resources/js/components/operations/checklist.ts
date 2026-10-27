import type { ChecklistEntry } from '@/types/operations';

/** Lista inicial vacía para los criterios dados. */
export function emptyChecklist(
    criteria: { value: string }[],
): ChecklistEntry[] {
    return criteria.map((c) => ({ criterion: c.value, result: '', notes: '' }));
}

/** Convierte una inspección guardada en entradas editables. */
export function checklistFrom(
    criteria: { value: string }[],
    checks:
        | {
              criterion: string;
              result: { value: string };
              notes: string | null;
          }[]
        | undefined,
): ChecklistEntry[] {
    return criteria.map((c) => {
        const found = checks?.find((k) => k.criterion === c.value);

        return {
            criterion: c.value,
            result: (found?.result.value as ChecklistEntry['result']) ?? '',
            notes: found?.notes ?? '',
        };
    });
}

/** ¿Están contestados todos los criterios y explicadas las fallas? */
export function checklistComplete(entries: ChecklistEntry[]): boolean {
    return entries.every(
        (e) =>
            e.result !== '' && (e.result !== 'fail' || e.notes.trim() !== ''),
    );
}
