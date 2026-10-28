import type { Tone } from '@/components/ds';
import type { Appointment, PaymentItem } from '@/types/projects';

type EnumValue = { value: string; label: string; tone: Tone };

/** Cita del portal (PortalFeed::appointments). */
export type PortalAppointment = Appointment & {
    folio: string | null;
    request_folio: string | null;
    is_video: boolean;
    pay_url: string | null;
    notes: string | null;
};

/** Pago del portal (PortalFeed::payments). */
export type PortalPayment = PaymentItem & {
    subject: {
        kind: 'visit' | 'consultation' | 'project' | 'other';
        label: string;
        detail: string | null;
        date: string | null;
    };
    pay_url: string | null;
    awaiting: boolean;
};

export type PaymentTotals = { paid: string; pending: string; count: number };

/** Solicitud del portal (PortalFeed::requests). */
export type PortalRequest = {
    uuid: string;
    folio: string;
    created_at: string | null;
    status: EnumValue;
    stage: number;
    closed: boolean;
    categories: string[];
    place: string | null;
    comments: string | null;
    timeframe: string | null;
    budget: string | null;
    consultations: {
        uuid: string;
        starts_at: string | null;
        status: EnumValue | null;
        channel: string;
        with: string | null;
    }[];
    visit: { starts_at: string | null; status: EnumValue | null } | null;
    project: {
        uuid: string;
        folio: string;
        name: string;
        status: string;
    } | null;
    next: { label: string; href: string; external: boolean } | null;
};

export type RequestStage = { key: string; title: string };

export type ActivityItem = {
    key: string;
    kind: 'project' | 'payment' | 'request' | 'consultation' | 'visit';
    title: string;
    description: string | null;
    date: string | null;
    amount?: string;
};
