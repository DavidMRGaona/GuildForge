import type { Tag } from '@/types/models';

export type ProposalStatus = 'draft' | 'pending_review' | 'approved' | 'rejected';
export type ProposalKind = 'event' | 'article';

/** What the author may still do with a proposal (decided by the server, the same rule as the service). */
export interface ProposalPermissions {
    edit: boolean;
    submit: boolean;
    withdraw: boolean;
    delete: boolean;
}

/** A proposal as its author sees it: the review state and what the form holds. */
export interface Proposal {
    id: string;
    type: ProposalKind;
    status: ProposalStatus;
    title: string;
    /** The event's description or the article's content (HTML). */
    body: string | null;
    excerpt: string | null;
    location: string | null;
    /** Wall times in the display zone, as the form shows them. */
    startDate: string | null;
    startTime: string | null;
    endDate: string | null;
    endTime: string | null;
    startsAt: string | null;
    imagePublicId: string | null;
    tagIds: string[];
    note: string | null;
    /** Why the last review returned or rejected it. */
    reason: string | null;
    submittedAt: string | null;
    reviewedAt: string | null;
    updatedAt: string | null;
    /** Address of the published page, once approved. */
    publicPath: string | null;
    can: ProposalPermissions;
}

export interface ProposalLimits {
    pendingUsed: number;
    pendingMax: number;
    draftsUsed: number;
    draftsMax: number;
    submissionsLeft: number;
    submissionsPerHour: number;
}

/** The «Mis propuestas» tab data of the profile page. */
export interface ProfileProposals {
    /** Whether the tab shows: the person may propose or already has proposals. */
    available: boolean;
    canPropose: { event: boolean; article: boolean };
    items: Proposal[];
    limits: ProposalLimits;
}

export interface ProposalFormProps {
    /** Null when creating. */
    proposal: Proposal | null;
    tags: Tag[];
}
