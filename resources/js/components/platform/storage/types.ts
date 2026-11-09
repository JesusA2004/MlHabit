export type MigrationDirection = 'to_synology' | 'to_local';

export type MigrationStatus =
    | 'queued'
    | 'running'
    | 'completed'
    | 'completed_with_errors'
    | 'cancelled';

export interface StorageDiskSummary {
    disk: string;
    label: string;
    files: number;
    variants: number;
    bytes: number;
}

export interface StorageSummary {
    disks: StorageDiskSummary[];
    pendingSync: number;
    pendingSyncBytes: number;
    pendingSyncError: string | null;
    remoteDown: boolean;
}

export interface MigrationPlan {
    files: number;
    bytes: number;
}

export interface MigrationFailure {
    id: number;
    mediaId: number;
    filename: string;
    error: string | null;
    attempts: number;
}

export interface StorageMigration {
    id: number;
    uuid: string;
    sourceDisk: string;
    targetDisk: string;
    direction: MigrationDirection;
    keepSource: boolean;
    status: MigrationStatus;
    statusLabel: string;
    isActive: boolean;
    totalFiles: number;
    totalBytes: number;
    processedFiles: number;
    processedBytes: number;
    transferredBytes: number;
    counts: {
        total: number;
        done: number;
        skipped: number;
        failed: number;
        pending: number;
    };
    etaSeconds: number | null;
    waitingForWorker: boolean;
    queuedJobs: number | null;
    keptSources: number;
    sourcesPurgedAt: string | null;
    startedAt: string | null;
    finishedAt: string | null;
    createdAt: string | null;
    failures: MigrationFailure[];
    /** Opcionales: si el backend no los manda, se calculan en el cliente. */
    percent?: number;
    lastError?: string | null;
}

export interface MigrationHistoryEntry {
    id: number;
    direction: MigrationDirection;
    status: MigrationStatus;
    statusLabel: string;
    totalFiles: number;
    totalBytes: number;
    createdAt: string | null;
    finishedAt: string | null;
}
