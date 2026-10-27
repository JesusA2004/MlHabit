import type { Tone } from '@/types/ui';

/** Tipos de las props del módulo projects (ver App\Http\Presenters\Projects\ProjectPresenter). */

export type EnumValue = { value: string; label: string; tone: Tone };

export type ProjectStatusValue = EnumValue & {
    client_description: string;
    journey_step: number;
};

export type Person = { id: number; name: string };

export type AddressInfo = {
    id: number;
    label: string | null;
    one_line: string;
    references: string | null;
    maps_url: string;
};

export type ProjectSummary = {
    uuid: string;
    folio: string;
    name: string;
    status: ProjectStatusValue;
    customer: { uuid: string; name: string } | null;
    category: string | null;
    advisor: Person | null;
    designer: Person | null;
    target_install_date: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type JourneyStep = { key: string; title: string; description: string };

export type ProjectEventItem = {
    id: number;
    type: string;
    title: string;
    body: string | null;
    visibility: 'client' | 'internal';
    to_status: string | null;
    actor: string | null;
    occurred_at: string | null;
};

export type FileItem = {
    id: number;
    role: string;
    caption: string | null;
    uuid: string | null;
    name: string;
    mime: string | null;
    size: number | null;
    is_image: boolean;
    url: string | null;
    created_at: string | null;
};

export type MeasurementItemRow = {
    id?: number;
    area: string;
    kind: string;
    kind_label?: string;
    label: string | null;
    value_mm: number | null;
    notes: string | null;
};

export type MeasurementData = {
    id: number;
    version: number;
    status: EnumValue;
    space_type: string | null;
    access_notes: string | null;
    existing_installations: string | null;
    general_notes: string | null;
    technician: Person | null;
    recorded_at: string | null;
    submitted_at: string | null;
    supersedes_id: number | null;
    items: MeasurementItemRow[];
    photos: FileItem[];
    sketches: FileItem[];
};

export type DesignData = {
    id: number;
    number: number;
    title: string;
    notes: string | null;
    status: EnumValue & { client_label: string };
    measurement_id: number | null;
    sent_at: string | null;
    client_approved_layout: boolean;
    client_approved_finishes: boolean;
    client_decided_at: string | null;
    client_comment: string | null;
    technical_approved_at: string | null;
    technical_approved_by: string | null;
    technical_notes: string | null;
    created_by: string | null;
    created_at: string | null;
    files: FileItem[];
};

export type QuoteSummary = {
    uuid: string;
    folio: string;
    version: number;
    title: string;
    status: EnumValue & { client_label: string };
    total: string;
    currency: string;
    valid_until: string | null;
    sent_at: string | null;
    accepted_at: string | null;
    rejected_at: string | null;
    rejection_reason: string | null;
    created_at: string | null;
    design_number: number | null;
};

export type QuoteLine = {
    id?: number;
    code: string | null;
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    discount_amount: string;
    line_total?: string;
};

export type QuoteData = QuoteSummary & {
    design_version_id: number | null;
    subtotal: string;
    discount_total: string;
    tax_rate: string;
    tax_percent: string;
    tax_total: string;
    prices_include_tax: boolean;
    inclusions: string | null;
    exclusions: string | null;
    notes: string | null;
    terms: string | null;
    discount_authorized_by: string | null;
    accepted_by: string | null;
    items: QuoteLine[];
};

export type PaymentItem = {
    uuid: string;
    folio: string;
    concept: EnumValue | null;
    method: EnumValue | null;
    status: EnumValue | null;
    description: string | null;
    amount: string;
    refunded_amount: string;
    card_brand: string | null;
    card_last4: string | null;
    reference: string | null;
    paid_at: string | null;
    created_at: string | null;
    receipt_url: string | null;
};

export type ContractData = {
    uuid: string;
    folio: string;
    status: EnumValue;
    contract_amount: string;
    deposit_percent: string;
    deposit_amount: string;
    visit_credit_amount: string;
    deposit_due_amount: string;
    currency: string;
    signed_at: string | null;
    deposit_paid_at: string | null;
    quote_uuid: string | null;
    quote_folio: string | null;
    remaining?: string | null;
    paid?: string | null;
    payments?: PaymentItem[];
    pay_url?: string | null;
    project?: { folio: string; name: string };
};

export type PendingAction = {
    key: string;
    title: string;
    description: string;
    href: string;
    cta: string;
    tone: Tone;
    amount: string | null;
};

export type Appointment = {
    key: string;
    kind: 'consultation' | 'visit';
    title: string;
    starts_at: string | null;
    ends_at: string | null;
    status: EnumValue | null;
    with: string | null;
    channel: string | null;
    meeting_url: string | null;
    location: string | null;
    active: boolean;
    reschedule_url: string | null;
};

export type ProjectOption = {
    uuid: string;
    folio: string;
    name: string;
    status: string;
};

/** Milímetros → texto "3,200 mm (320 cm)". */
export function formatMm(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    const cm = value / 10;

    return `${value.toLocaleString('es-MX')} mm (${cm.toLocaleString('es-MX', { maximumFractionDigits: 1 })} cm)`;
}
