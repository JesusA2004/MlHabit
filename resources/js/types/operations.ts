/**
 * Tipos del módulo de operaciones (espejo de App\Http\Presenters\Operations\OpsPresenter).
 * Los importes llegan como cadena decimal ("1234.50"): el frontend solo los muestra.
 */
import type { Tone } from '@/types/ui';

export type EnumOption<V extends string = string> = {
    value: V;
    label: string;
    tone: Tone;
};
export type SelectOption<V extends string | number = number> = {
    value: V;
    label: string;
    hint?: string;
};

export type UserRef = { id: number; name: string } | null;

export type ProjectRef = {
    id: number;
    uuid: string;
    folio: string;
    name: string;
    status: EnumOption;
    customer: string | null;
};

export type MediaItem = {
    id: number;
    uuid: string;
    name: string;
    type: 'image' | 'video' | 'document' | 'other';
    mime: string;
    size: number;
    url: string | null;
    thumbnail: string | null;
    createdAt: string | null;
};

export type SupplierRef = {
    id: number;
    name: string;
    type: EnumOption;
    status: EnumOption;
    rating: string | null;
    leadTimeDays: number | null;
    email: string | null;
    phone: string | null;
};

export type RateCardItemRow = {
    id: number;
    code: string;
    description: string;
    unit: string;
    unitPrice: string;
};

export type RateCardRow = {
    id: number;
    name: string;
    version: number;
    status: EnumOption;
    supplier: SupplierRef | null;
    mobilizationFee: string;
    minimumCharge: string;
    effectiveFrom: string | null;
    activatedAt: string | null;
    notes: string | null;
    itemsCount: number | null;
    items: RateCardItemRow[] | null;
    updatedAt: string | null;
};

export type FabricationRow = {
    id: number;
    folio: string;
    status: EnumOption;
    project: ProjectRef;
    supplier: SupplierRef;
    designVersionId: number;
    cost: string | null;
    dueOn: string | null;
    overdue: boolean;
    issuedAt: string | null;
    readyAt: string | null;
    deliveredAt: string | null;
    notes: string | null;
    createdAt: string | null;
    nextStatuses: EnumOption[];
};

export type InstallationRow = {
    id: number;
    folio: string;
    status: EnumOption;
    project: ProjectRef;
    supplier: SupplierRef | null;
    leadInstaller: UserRef;
    scheduledStart: string | null;
    scheduledEnd: string | null;
    total: string | null;
    itemsCount: number | null;
    doneCount: number | null;
    pendingExtras: number | null;
    clientConformity: EnumOption | null;
    evidenceSubmittedAt: string | null;
};

export type CheckRow = {
    criterion: string;
    label: string;
    result: EnumOption;
    notes: string | null;
};

export type Inspection = {
    id: number;
    stage: EnumOption;
    result: EnumOption;
    observations: string | null;
    clientContacted: boolean;
    inspector: UserRef;
    inspectedAt: string | null;
    checks: CheckRow[];
};

export type InstallationItem = {
    id: number;
    code: string;
    description: string;
    unit: string;
    quantity: string;
    unitPrice: string | null;
    lineTotal: string | null;
    isExtra: boolean;
    extraApproved: boolean;
    pendingExtra: boolean;
    countsTowardsTotal: boolean;
    status: EnumOption;
    evidence: MediaItem[];
    installerCheck: Inspection | null;
    qualityCheck: Inspection | null;
};

export type PayoutRow = {
    id: number;
    folio: string;
    status: EnumOption;
    amount: string;
    supplier: { id: number; name: string } | null;
    source: {
        kind: 'installation' | 'fabrication';
        id: number | null;
        folio: string | null;
    };
    requestedBy: UserRef;
    authorizedBy: UserRef;
    authorizedAt: string | null;
    paidBy: UserRef;
    paidAt: string | null;
    reference: string | null;
    rejectionReason: string | null;
    createdAt: string | null;
};

/** 'scheduled' | 'in_progress' | 'client_review' | 'quality_review' | 'approved' */
export type InstallationStep =
    | 'scheduled'
    | 'in_progress'
    | 'client_review'
    | 'quality_review'
    | 'approved';

export type InstallationDetail = {
    id: number;
    folio: string;
    status: EnumOption;
    step: InstallationStep;
    project: ProjectRef;
    address: string | null;
    addressReferences: string | null;
    supplier: SupplierRef | { id: number; name: string };
    leadInstaller: UserRef;
    rateCard: {
        id: number;
        name: string;
        version: number;
        status: EnumOption;
    } | null;
    scheduledStart: string | null;
    scheduledEnd: string | null;
    startedAt: string | null;
    evidenceSubmittedAt: string | null;
    completedAt: string | null;
    mobilizationFee: string | null;
    minimumCharge: string | null;
    subtotal: string | null;
    total: string | null;
    minimumApplied: boolean;
    clientConformity: EnumOption | null;
    clientComment: string | null;
    clientSignedAt: string | null;
    clientPhotos: MediaItem[];
    notes: string | null;
    items: InstallationItem[];
    qualityReview: Inspection | null;
    payout: PayoutRow | null;
};

export type IncidentRow = {
    id: number;
    folio: string;
    title: string;
    status: EnumOption;
    category: EnumOption;
    severity: EnumOption;
    origin: EnumOption;
    project: ProjectRef;
    assignee: UserRef;
    dueOn: string | null;
    overdue: boolean;
    visibleToClient: boolean;
    createdAt: string | null;
};

export type IncidentDetail = IncidentRow & {
    description: string;
    responsibleParty: EnumOption | null;
    responsibleSupplier: { id: number; name: string } | null;
    actionPlan: string | null;
    cost: string | null;
    resolution: string | null;
    resolvedAt: string | null;
    reporter: UserRef;
    installationOrder: { id: number; folio: string } | null;
    fabricationOrder: { id: number; folio: string } | null;
    evidence: MediaItem[];
    nextStatuses: EnumOption[];
};

export type WarrantyClaimRow = {
    id: number;
    folio: string;
    status: EnumOption;
    description: string;
    resolution: string | null;
    resolvedAt: string | null;
    createdAt: string | null;
    reporter: UserRef;
    incident: { id: number; folio: string } | null;
    evidence: MediaItem[];
};

export type WarrantyRow = {
    id: number;
    folio: string;
    type: EnumOption;
    status: EnumOption;
    project: ProjectRef;
    supplier: { id: number; name: string } | null;
    startsOn: string;
    endsOn: string;
    inForce: boolean;
    openClaims: number | null;
};

export type WarrantyDetail = WarrantyRow & {
    scope: string;
    exclusions: string | null;
    claims: WarrantyClaimRow[];
};

/** Fila del checklist que se envía al backend (11 criterios). */
export type ChecklistEntry = {
    criterion: string;
    result: 'pass' | 'fail' | 'na' | '';
    notes: string;
};
