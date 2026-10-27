import type {
    AddressInfo,
    ContractData,
    DesignData,
    EnumValue,
    FileItem,
    JourneyStep,
    MeasurementData,
    Person,
    ProjectEventItem,
    ProjectSummary,
    QuoteSummary,
} from '@/types/projects';

export type Option = { id: number; name: string };

export type HubProject = ProjectSummary & {
    operations_owner: Person | null;
    address: AddressInfo | null;
    lead: { uuid: string; folio: string } | null;
    contracted_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    customer: {
        uuid: string;
        name: string;
        first_name: string;
        email: string | null;
        phone: string | null;
        has_portal: boolean;
        portal_active: boolean;
    };
    address_id: number | null;
    product_category_id: number | null;
    advisor_id: number | null;
    designer_id: number | null;
    operations_owner_id: number | null;
};

export type HubCan = {
    manage: boolean;
    designs_manage: boolean;
    approve_technical: boolean;
    quotes_manage: boolean;
    authorize_discount: boolean;
    record_payment: boolean;
    invite: boolean;
    view_operations: boolean;
};

export type OpsCard = Record<string, unknown> & {
    id: number;
    folio: string;
    status: EnumValue | null;
};

export type HubOperations = {
    fabrication: OpsCard[];
    installations: OpsCard[];
    incidents: OpsCard[];
    warranties: OpsCard[];
};

export type HubVisit = {
    uuid: string;
    folio: string;
    starts_at: string | null;
    status: EnumValue | null;
    technician: string | null;
};

export type ProjectHubProps = {
    project: HubProject;
    journey: JourneyStep[];
    transitions: EnumValue[];
    events: ProjectEventItem[];
    visits: HubVisit[];
    measurements: MeasurementData[];
    designs: DesignData[];
    quotes: QuoteSummary[];
    contract: ContractData | null;
    operations: HubOperations;
    files: FileItem[];
    addresses: AddressInfo[];
    options: {
        statuses: EnumValue[];
        advisors: Option[];
        designers: Option[];
        operations: Option[];
        categories: Option[];
    };
    paymentMethods: { value: string; label: string }[];
    can: HubCan;
    summary: {
        pending_client_design: boolean;
        pending_technical: boolean;
        open_quote: boolean;
        deposit_pending: boolean;
    };
};
