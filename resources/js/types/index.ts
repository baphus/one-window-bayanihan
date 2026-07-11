export type ReferralStatus = 'PENDING' | 'PROCESSING' | 'FOR_COMPLIANCE' | 'COMPLETED' | 'REJECTED';

export type ClientType = 'Overseas Filipino Worker' | 'Next of Kin';

export type ReferralActorRole = 'Agency Focal' | 'Case Manager' | 'System';

export type MockUserRole = 'System Admin' | 'Case Manager' | 'Agency';

export type MockAuthUser = {
    email: string;
    password: string;
    role: MockUserRole;
    name: string;
};

export type ReferralActor = {
    id: string;
    name: string;
    role: ReferralActorRole;
};

export type SharedReferralCase = {
    id: string;
    caseNo: string;
    clientName: string;
    clientType: ClientType;
    service: string;
    milestone: string;
    status: ReferralStatus;
    createdAt: string;
    updatedAt: string;
};

export type SpecialCategory = 'Senior Citizen' | 'PWD' | 'Solo Parent';

export type AddressParts = {
    regionCode: string;
    regionName: string;
    provinceCode: string;
    provinceName: string;
    municipalityCode: string;
    municipalityName: string;
    barangayCode: string;
    barangayName: string;
    streetAddress: string;
};

export type ClientPersona = {
    ofwName: string;
    ofwBirth: string;
    gender: string;
    ofwEmail: string;
    ofwContact: string;
    ofwAddress: AddressParts;
    kinName: string;
    kinContact: string;
    kinEmail: string;
    kinAddress: AddressParts;
    lastCountry: string;
    lastJob: string;
    arrivalDate: string;
};

export type ExistingClientProfile = {
    firstName: string;
    lastName: string;
    middleInitial: string;
    suffix: string;
    dateOfBirth: string;
    sex: string;
    contactNumber: string;
    email: string;
    address: AddressParts;
    employmentHistory: Array<{
        employer: string;
        position: string;
        country: string;
        startDate: string;
        endDate: string;
    }>;
};

export type CaseManagerAgency = {
    id: string;
    short: string;
    name: string;
    logoUrl: string;
    contact: string;
    email: string;
    locationQuery: string;
    services: string[];
};

export type StakeholderServiceDetail = {
    id: string;
    title: string;
    description: string;
    requiredDocuments: string[];
    processingDays: number;
};

export type CaseManagerCase = SharedReferralCase & {
    agencyId: string;
    agencyShort: string;
    agencyName: string;
    ofwUserEmail?: string;
    caseNarrative?: string;
    ofwProfile?: {
        fullName: string;
        birthDate: string;
        gender: string;
        email: string;
        contact: string;
        address: AddressParts;
        specialCategories: string[];
    };
    nextOfKinProfile?: {
        fullName: string;
        relationship?: string;
        relationshipOther?: string;
        contact: string;
        email: string;
        address: AddressParts;
        specialCategories?: string[];
    };
    nextOfKinProfiles?: Array<{
        fullName: string;
        relationship?: string;
        relationshipOther?: string;
        contact: string;
        email: string;
        address: AddressParts;
        specialCategories?: string[];
    }>;
    workHistory?: {
        lastCountry: string;
        lastJob: string;
        arrivalDate: string;
    };
};

export type CaseManagerReferralNote = {
    id: string;
    content: string;
    createdAt: string;
    createdBy: string;
};

export type CaseManagerReferral = {
    id: string;
    caseId: string;
    caseNo: string;
    clientName: string;
    service: string;
    agencyId: string;
    agencyName: string;
    status: ReferralStatus;
    createdAt: string;
    updatedAt: string;
    remarks: string;
    documents: Array<{
        id: string;
        name: string;
        uploadedBy: string;
        uploadedAt: string;
    }>;
    noteHistory?: CaseManagerReferralNote[];
};

export type StepState = 'complete' | 'active' | 'pending';

export type MilestoneItem = {
    title: string;
    titleTone: string;
    time: string;
    detail: string;
    dotTone: string;
};

export type MilestoneInfoRow = {
    label: string;
    value: string;
};

export type AgencyStep = {
    label: string;
    state: StepState;
    icon?: string;
};

export type TrackingComplianceRequirement = {
    id: string;
    service_name: string;
    requirement_name: string;
    status: string;
    completed_at: string | null;
};

export type TrackingAgencyCardData = {
    referralId: string;
    name: string;
    note: string;
    status: ReferralStatus;
    milestoneCount: number;
    steps: AgencyStep[];
    latestMilestoneLabel?: string;
    milestonesUrl: string;
    compliance_requirements: TrackingComplianceRequirement[];
};

export type CaseEventType =
    | 'case_opened'
    | 'referral_sent'
    | 'referral_status_changed'
    | 'milestone_added'
    | 'compliance_fulfilled'
    | 'case_closed'
    | 'case_reopened';

export type CaseEventItem = {
    date: string;
    type: CaseEventType;
    agency: string | null;
    referralId: string | null;
    title: string;
    description: string;
};

export type CaseOverviewData = {
    narrative: string;
    ofw: {
        fullName: string;
        dateOfBirth: string;
        gender: string;
        homeAddress: string;
        homeAddressParts: AddressParts;
        specialCategories: string[];
    };
    nextOfKin: {
        fullName: string;
        relationship: string;
        contactNumber: string;
        emailAddress: string;
        homeAddress: string;
        homeAddressParts: AddressParts;
        specialCategories: string[];
    };
    workHistory: {
        lastCountry: string;
        lastPosition: string;
        arrivalDate: string;
    };
};

export type TrackingAgencyKey = 'owwa' | 'dmw' | 'tesda';

export type AgencyMilestonePageData = {
    breadcrumbLabel: string;
    title: string;
    subtitle: string;
    statusLabel: string;
    statusContainerTone: string;
    statusDotTone: string;
    statusTextTone: string;
    locationName: string;
    locationSubtitle: string;
    locationContact: string;
    milestones: MilestoneItem[];
    infoRows: MilestoneInfoRow[];
    caseId: string;
    trackingId: string;
    referralStatus: string;
    referralServices: string;
};

export type TrackCasePageData = {
    trackingId: string;
    trackedCase: SharedReferralCase;
    caseOverview: CaseOverviewData;
    milestoneTimeline: CaseEventItem[];
    completionPercentage: number;
    rejectedCount: number;
    trackingAgencies: TrackingAgencyCardData[];
};

export type OversightActivityType =
    | 'ASSIGNED'
    | 'ACCEPTED'
    | 'MILESTONE_UPDATED'
    | 'COMPLETED'
    | 'REJECTED'
    | 'CASE_CREATED'
    | 'REFERRAL_SENT'
    | 'RECORD_CREATED'
    | 'RECORD_UPDATED'
    | 'STATUS_CHANGED';

export type OversightActivityLog = {
    id: string;
    action: OversightActivityType;
    details: string;
    timestamp: string;
    actor: string;
    actorRole: string;
    caseNo: string;
    clientName: string;
    agencyName: string;
};

export type AgencyFocalAccount = {
    id: string;
    name: string;
    email: string;
    contact: string;
    status: 'ACTIVE' | 'INACTIVE';
    agencyId: string;
};

export type SystemAdminEntity =
    | 'cases'
    | 'clients'
    | 'agencies'
    | 'services'
    | 'referrals'
    | 'users';

export type SystemAdminCrudRow = {
    id: string;
    type: SystemAdminEntity;
    status: 'ACTIVE' | 'ARCHIVED';
    [key: string]: unknown;
};
