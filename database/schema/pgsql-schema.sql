--
-- PostgreSQL database dump
--

\restrict 0b0pulyjzPiuHbNUF8C3Hgq9i57lRYoRL5ABDrzXneoZ2HoyaxDuEcVuqZbTjBp

-- Dumped from database version 18.2
-- Dumped by pg_dump version 18.2

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


--
-- Name: block_audit_log_modification(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.block_audit_log_modification() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                IF current_setting('app.allow_audit_mutations', true) = 'true' THEN
                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;
                    RETURN NEW;
                END IF;
                RAISE EXCEPTION 'audit_logs are append-only: cannot modify or delete rows';
            END;
            $$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: activity_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activity_events (
    id uuid NOT NULL,
    occurred_at timestamp(0) with time zone NOT NULL,
    actor_id uuid,
    actor_name character varying(255) NOT NULL,
    actor_role character varying(255),
    module character varying(50) NOT NULL,
    action character varying(50) NOT NULL,
    description text,
    entity_id uuid,
    audit_log_id uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: agencies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agencies (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    short character varying(255),
    slug character varying(255),
    description text,
    contact_info character varying(255),
    map_link text,
    logo_url text,
    location_query text,
    is_active boolean DEFAULT true NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    latitude numeric(10,7),
    longitude numeric(10,7),
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: agency_thread_reads; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agency_thread_reads (
    user_id uuid NOT NULL,
    case_id uuid NOT NULL,
    peer_agency_id uuid NOT NULL,
    last_read_at timestamp(0) without time zone NOT NULL
);

ALTER TABLE ONLY public.agency_thread_reads FORCE ROW LEVEL SECURITY;


--
-- Name: audit_archives; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_archives (
    id uuid NOT NULL,
    period character varying(7) NOT NULL,
    path character varying(255) NOT NULL,
    checksum character varying(64) NOT NULL,
    row_count bigint NOT NULL,
    first_entry_at timestamp(0) without time zone NOT NULL,
    last_entry_at timestamp(0) without time zone NOT NULL,
    finalized_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: audit_chain_checkpoints; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_chain_checkpoints (
    id uuid NOT NULL,
    anchor_hash character varying(64) NOT NULL,
    pruned_through timestamp(0) without time zone NOT NULL,
    bundle_manifest_path character varying(255),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: audit_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_logs (
    id uuid NOT NULL,
    action character varying(50) NOT NULL,
    module character varying(255) NOT NULL,
    entity_id uuid,
    description text,
    old_value jsonb,
    new_value jsonb,
    user_id uuid,
    "timestamp" timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    ip_address character varying(45),
    user_agent text,
    request_id uuid,
    prev_hash character varying(64),
    category character varying(20),
    chain_seq bigint NOT NULL,
    entity_label character varying(255),
    CONSTRAINT audit_logs_action_check CHECK (((action)::text = ANY (ARRAY['CREATE'::text, 'UPDATE'::text, 'DELETE'::text, 'ARCHIVE'::text, 'UNARCHIVE'::text, 'PUBLISH'::text, 'RESTORE'::text, 'PURGE'::text, 'LOGIN'::text, 'LOGOUT'::text, 'LOGIN_FAILED'::text, 'ACCEPT_INVITE'::text, 'REQUEST_PASSWORD_RESET'::text, 'RESET_PASSWORD'::text, 'CHANGE_PASSWORD'::text, 'VERIFY_EMAIL'::text, 'CHANGE_EMAIL'::text, 'ENABLE_MFA'::text, 'DISABLE_MFA'::text, 'REGENERATE_RECOVERY_CODES'::text, 'ADMIN_RESET_PASSWORD'::text, 'ADMIN_CHANGE_EMAIL'::text, 'ADMIN_VERIFY_EMAIL'::text, 'DELETE_ACCOUNT'::text, 'PURGE_USER'::text, 'REVOKE_SESSIONS'::text, 'APPROVE'::text, 'REJECT'::text, 'SEND'::text, 'CLOSE'::text, 'CREATE_CASE_ISSUE'::text, 'DOWNLOAD'::text, 'EXPORT'::text, 'ACCEPT'::text, 'REQUEST_COMPLIANCE'::text, 'RESUME'::text, 'COMPLETE'::text, 'ASSIGN_SERVICE'::text, 'UNASSIGN_SERVICE'::text, 'ADD_REQUIREMENT'::text, 'UPDATE_REQUIREMENT'::text, 'REMOVE_REQUIREMENT'::text, 'ISSUE_CLIENT_REQUEST'::text, 'ISSUE_LINK'::text, 'REISSUE_LINK'::text, 'REVOKE_LINK'::text, 'RESOLVE_CLIENT_REQUEST'::text, 'CANCEL_CLIENT_REQUEST'::text, 'REOPEN_CLIENT_REQUEST'::text, 'INVITE'::text, 'RESEND_INVITE'::text, 'CANCEL_INVITE'::text, 'DEACTIVATE'::text, 'REACTIVATE'::text, 'RESET_MFA'::text, 'TOGGLE_MAINTENANCE'::text, 'UPDATE_SECURITY'::text, 'SEND_REMINDER'::text, 'REGISTER'::text, 'FILE_INTAKE'::text, 'REPLY_CLIENT_REQUEST'::text, 'UPLOAD_REPLY_DOC'::text, 'TRACK_CASE'::text, 'SUBMIT_SURVEY'::text, 'SUBMIT_CONTACT'::text]))),
    CONSTRAINT audit_logs_category_check CHECK (((category IS NULL) OR ((category)::text = ANY (ARRAY['security'::text, 'data'::text, 'admin'::text, 'system'::text]))))
);


--
-- Name: audit_logs_chain_seq_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audit_logs_chain_seq_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_logs_chain_seq_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audit_logs_chain_seq_seq OWNED BY public.audit_logs.chain_seq;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: case_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.case_categories (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    color character varying(7),
    sort_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: case_category_agency; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.case_category_agency (
    case_category_id uuid NOT NULL,
    agcy_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: case_documents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.case_documents (
    id uuid NOT NULL,
    file_name character varying(255) NOT NULL,
    file_path text NOT NULL,
    file_type character varying(50),
    size bigint,
    case_id uuid NOT NULL,
    user_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid,
    category character varying(255),
    referral_id uuid,
    files_deleted_at timestamp(0) without time zone
);


--
-- Name: case_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.case_events (
    id uuid NOT NULL,
    sequence bigint NOT NULL,
    case_id uuid NOT NULL,
    referral_id uuid,
    type character varying(50) NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    meta jsonb,
    actor_type character varying(20) DEFAULT 'system'::character varying NOT NULL,
    occurred_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: COLUMN case_events.actor_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.case_events.actor_type IS 'agency | case_manager | system';


--
-- Name: case_events_sequence_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.case_events ALTER COLUMN sequence ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.case_events_sequence_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: case_notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.case_notifications (
    id uuid NOT NULL,
    case_id uuid NOT NULL,
    client_email character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    title character varying(255) NOT NULL,
    message text NOT NULL,
    data json,
    related_url character varying(255),
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: case_number_counters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.case_number_counters (
    period integer NOT NULL,
    last_number integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: case_statuses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.case_statuses (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    color character varying(7),
    sort_order integer DEFAULT 0 NOT NULL,
    is_system boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cases; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cases (
    id uuid NOT NULL,
    case_number character varying(255) NOT NULL,
    vulnerability_indicator character varying(255),
    nok_vulnerability_indicator character varying(255),
    tracker_number character varying(255) NOT NULL,
    summary text,
    status character varying(50) DEFAULT 'OPEN'::character varying NOT NULL,
    closed_at timestamp(0) without time zone,
    consent_given_at timestamp(0) without time zone,
    user_id uuid,
    escalation_reason character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid,
    deletion_reason text,
    reviewed_by uuid,
    reviewed_at timestamp(0) without time zone,
    ofw_user_id uuid,
    client_type character varying(255),
    intake_date date,
    first_name text,
    middle_name text,
    last_name text,
    suffix text,
    date_of_birth text,
    email text,
    contact_number text,
    sex character varying(255),
    civil_status character varying(255),
    civil_status_other text,
    deployment_mode character varying(255),
    employment_status character varying(255),
    address_region character varying(255),
    address_region_code character varying(20),
    address_province character varying(255),
    address_province_code character varying(20),
    address_city_municipality character varying(255),
    address_city_municipality_code character varying(20),
    address_barangay character varying(255),
    address_barangay_code character varying(20),
    address_street text,
    employment_employer_name text,
    employment_position text,
    employment_country text,
    employment_last_country text,
    employment_last_position text,
    employment_start_date date,
    employment_end_date date,
    employment_date_of_arrival date,
    nok_name text,
    nok_relationship text,
    nok_contact_number text,
    nok_email text,
    staff_summary text,
    consent_evidence text,
    proposed_assessments jsonb,
    proposed_assessment_other text,
    proposed_assessment_by uuid,
    proposed_assessment_at timestamp(0) without time zone,
    confirmed_assessments jsonb,
    confirmed_assessment_other text,
    confirmed_assessment_by uuid,
    confirmed_assessment_at timestamp(0) without time zone,
    consent_text_version character varying(255),
    consent_method character varying(255),
    consent_accepted_by uuid,
    employment_employer_address text,
    last_correction_reason text,
    return_reason character varying(30),
    missing_document_reasons text,
    requested_document_slots jsonb,
    document_request_reason text,
    document_requested_by uuid,
    document_requested_at timestamp(0) with time zone,
    reviewed_document_ids jsonb,
    assigned_case_manager_id uuid,
    CONSTRAINT cases_civil_status_check CHECK (((civil_status)::text = ANY ((ARRAY['SINGLE'::character varying, 'MARRIED'::character varying, 'WIDOWED'::character varying, 'SEPARATED'::character varying, 'ANNULLED'::character varying, 'OTHER'::character varying])::text[]))),
    CONSTRAINT cases_civil_status_other_check CHECK ((((civil_status)::text <> 'OTHER'::text) OR (civil_status_other IS NOT NULL))),
    CONSTRAINT cases_client_type_check CHECK (((client_type)::text = ANY ((ARRAY['OFW'::character varying, 'NEXT_OF_KIN'::character varying])::text[]))),
    CONSTRAINT cases_confirmed_assessment_other_check CHECK (((NOT (confirmed_assessments @> '["OTHER"]'::jsonb)) OR (confirmed_assessment_other IS NOT NULL))),
    CONSTRAINT cases_confirmed_assessments_check CHECK (((confirmed_assessments IS NULL) OR ((jsonb_typeof(confirmed_assessments) = 'array'::text) AND (confirmed_assessments <@ '["DISTRESSED_OFW", "MEDICALLY_REPATRIATED", "BURIAL_ASSISTANCE_CONCERN", "POSSIBLE_LOCAL_EMPLOYMENT", "PSYCHOSOCIAL_MEDICAL_INTERVENTION", "SKILLS_ENHANCEMENT_TRAINING", "LEGAL_ASSISTANCE", "OTHER"]'::jsonb)))),
    CONSTRAINT cases_deployment_mode_check CHECK (((deployment_mode)::text = ANY ((ARRAY['AGENCY_HIRED'::character varying, 'DIRECT_HIRED'::character varying, 'TOURIST_VISIT'::character varying, 'BUSINESS'::character varying, 'INVITATION'::character varying])::text[]))),
    CONSTRAINT cases_employment_status_check CHECK (((employment_status)::text = ANY ((ARRAY['ON_SITE'::character varying, 'FOR_DEPLOYMENT'::character varying, 'RETURNED'::character varying])::text[]))),
    CONSTRAINT cases_proposed_assessment_other_check CHECK (((NOT (proposed_assessments @> '["OTHER"]'::jsonb)) OR (proposed_assessment_other IS NOT NULL))),
    CONSTRAINT cases_proposed_assessments_check CHECK (((proposed_assessments IS NULL) OR ((jsonb_typeof(proposed_assessments) = 'array'::text) AND (proposed_assessments <@ '["DISTRESSED_OFW", "MEDICALLY_REPATRIATED", "BURIAL_ASSISTANCE_CONCERN", "POSSIBLE_LOCAL_EMPLOYMENT", "PSYCHOSOCIAL_MEDICAL_INTERVENTION", "SKILLS_ENHANCEMENT_TRAINING", "LEGAL_ASSISTANCE", "OTHER"]'::jsonb)))),
    CONSTRAINT cases_return_reason_check CHECK (((return_reason IS NULL) OR ((return_reason)::text = ANY ((ARRAY['DISTRESSED_WORKER'::character varying, 'TERMINATION'::character varying, 'OTHERS'::character varying])::text[])))),
    CONSTRAINT cases_sex_check CHECK (((sex)::text = ANY ((ARRAY['MALE'::character varying, 'FEMALE'::character varying])::text[])))
);


--
-- Name: email_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.email_events (
    id uuid NOT NULL,
    email_log_id uuid,
    provider_message_id character varying(255),
    event_type character varying(255) NOT NULL,
    occurred_at timestamp(0) without time zone,
    svix_id character varying(255) NOT NULL,
    payload jsonb NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: email_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.email_logs (
    id uuid NOT NULL,
    to_email character varying(255) NOT NULL,
    subject character varying(255) NOT NULL,
    mailable_type character varying(255) NOT NULL,
    status character varying(255) NOT NULL,
    job_uuid uuid,
    error_message text,
    sent_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    provider_message_id character varying(255),
    delivered_at timestamp(0) without time zone,
    resent_at timestamp(0) without time zone
);


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: generated_documents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.generated_documents (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    case_id uuid,
    type character varying(255) NOT NULL,
    filename character varying(255) NOT NULL,
    path character varying(255),
    file_size bigint,
    mime_type character varying(255),
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    error_message text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: helpdesk_article_feedback; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.helpdesk_article_feedback (
    id uuid NOT NULL,
    article_slug character varying(160) NOT NULL,
    identity_hash character varying(64) NOT NULL,
    vote character varying(3) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: milestones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.milestones (
    id uuid NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    refr_id uuid NOT NULL,
    user_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid,
    requirements json,
    client_request_id uuid
);


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id uuid NOT NULL,
    type character varying(255) NOT NULL,
    notifiable_id uuid NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    data text NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: referral_attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.referral_attachments (
    id uuid NOT NULL,
    referral_id uuid NOT NULL,
    file_name character varying(255) NOT NULL,
    file_path text NOT NULL,
    file_type character varying(50),
    size bigint,
    user_id uuid,
    replaces_id uuid,
    version_group_id uuid,
    is_archived boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid,
    files_deleted_at timestamp(0) without time zone
);


--
-- Name: referral_client_access_links; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.referral_client_access_links (
    id uuid NOT NULL,
    request_id uuid NOT NULL,
    token_hash character varying(255) NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    revoked_at timestamp(0) without time zone,
    revoked_by uuid,
    issued_by uuid NOT NULL,
    recipient_snapshot text NOT NULL,
    first_used_at timestamp(0) without time zone,
    last_used_at timestamp(0) without time zone,
    use_count integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.referral_client_access_links FORCE ROW LEVEL SECURITY;


--
-- Name: referral_client_attachment_agencies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.referral_client_attachment_agencies (
    attachment_id uuid NOT NULL,
    agency_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: referral_client_message_attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.referral_client_message_attachments (
    id uuid NOT NULL,
    message_id uuid NOT NULL,
    file_name character varying(255) NOT NULL,
    file_path character varying(255) NOT NULL,
    file_type character varying(128),
    size bigint DEFAULT '0'::bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid,
    files_deleted_at timestamp(0) without time zone,
    request_item_id uuid,
    review_status character varying(32) DEFAULT 'ACCEPTED'::character varying NOT NULL,
    visibility character varying(32) DEFAULT 'ALL'::character varying NOT NULL,
    sha256 character varying(64)
);

ALTER TABLE ONLY public.referral_client_message_attachments FORCE ROW LEVEL SECURITY;


--
-- Name: referral_client_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.referral_client_messages (
    id uuid NOT NULL,
    request_id uuid NOT NULL,
    body text NOT NULL,
    sender_kind character varying(24) NOT NULL,
    user_id uuid,
    access_link_id uuid,
    kind character varying(24) DEFAULT 'MESSAGE'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid,
    CONSTRAINT referral_client_messages_kind_check CHECK (((kind)::text = ANY (ARRAY[('MESSAGE'::character varying)::text, ('SYSTEM'::character varying)::text, ('REVISION'::character varying)::text]))),
    CONSTRAINT referral_client_messages_sender_check CHECK (((((sender_kind)::text = 'AGENCY_USER'::text) AND (user_id IS NOT NULL) AND (access_link_id IS NULL)) OR (((sender_kind)::text = 'CLIENT_ACCESS'::text) AND (user_id IS NULL) AND (access_link_id IS NOT NULL)) OR (((sender_kind)::text = 'CLIENT_USER'::text) AND (user_id IS NOT NULL) AND (access_link_id IS NULL)))),
    CONSTRAINT referral_client_messages_sender_kind_check CHECK (((sender_kind)::text = ANY ((ARRAY['AGENCY_USER'::character varying, 'CLIENT_ACCESS'::character varying, 'CLIENT_USER'::character varying])::text[])))
);

ALTER TABLE ONLY public.referral_client_messages FORCE ROW LEVEL SECURITY;


--
-- Name: referral_client_request_item_services; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.referral_client_request_item_services (
    request_item_id uuid NOT NULL,
    service_requirement_id uuid CONSTRAINT referral_client_request_item_se_service_requirement_id_not_null NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: referral_client_request_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.referral_client_request_items (
    id uuid NOT NULL,
    request_id uuid NOT NULL,
    label character varying(255) NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid,
    source_requirement_id uuid,
    description text,
    canonical_key character varying(255)
);

ALTER TABLE ONLY public.referral_client_request_items FORCE ROW LEVEL SECURITY;


--
-- Name: referral_client_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.referral_client_requests (
    id uuid NOT NULL,
    referral_id uuid NOT NULL,
    creator_user_id uuid NOT NULL,
    type character varying(32) NOT NULL,
    title character varying(255) NOT NULL,
    instructions text NOT NULL,
    status character varying(32) DEFAULT 'OPEN'::character varying NOT NULL,
    due_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid,
    CONSTRAINT referral_client_requests_status_check CHECK (((status)::text = ANY (ARRAY[('OPEN'::character varying)::text, ('IN_PROGRESS'::character varying)::text, ('CLIENT_RESPONDED'::character varying)::text, ('COMPLETED'::character varying)::text, ('CANCELLED'::character varying)::text]))),
    CONSTRAINT referral_client_requests_type_check CHECK (((type)::text = ANY (ARRAY[('DOCUMENT_REQUEST'::character varying)::text, ('QUESTION'::character varying)::text, ('INFORMATION_UPDATE'::character varying)::text])))
);

ALTER TABLE ONLY public.referral_client_requests FORCE ROW LEVEL SECURITY;


--
-- Name: referral_comments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.referral_comments (
    id uuid NOT NULL,
    refr_id uuid NOT NULL,
    parent_id uuid,
    content text NOT NULL,
    visibility character varying(50) NOT NULL,
    is_edited boolean DEFAULT false NOT NULL,
    user_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid
);


--
-- Name: referral_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.referral_messages (
    id uuid NOT NULL,
    referral_id uuid NOT NULL,
    sender_user_id uuid NOT NULL,
    body text NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid
);

ALTER TABLE ONLY public.referral_messages FORCE ROW LEVEL SECURITY;


--
-- Name: referral_service_requirements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.referral_service_requirements (
    id uuid NOT NULL,
    referral_id uuid NOT NULL,
    service_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    is_required boolean DEFAULT true NOT NULL,
    sort_order integer,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_by uuid,
    deleted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: referral_services; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.referral_services (
    referral_id uuid NOT NULL,
    service_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: referrals; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.referrals (
    id uuid NOT NULL,
    required_services text NOT NULL,
    notes text,
    status character varying(50) DEFAULT 'PENDING'::character varying NOT NULL,
    decision character varying(20),
    decision_comment text,
    case_id uuid NOT NULL,
    agcy_id uuid NOT NULL,
    first_action_at timestamp(0) without time zone,
    referral_assigned_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid,
    rejection_reason character varying(30),
    category_id uuid,
    concern text,
    override_reason text,
    CONSTRAINT referrals_decision_check CHECK (((decision IS NULL) OR ((decision)::text = ANY (ARRAY[('ACCEPT'::character varying)::text, ('REJECT'::character varying)::text])))),
    CONSTRAINT referrals_rejection_reason_check CHECK (((rejection_reason IS NULL) OR ((rejection_reason)::text = ANY (ARRAY[('INCOMPLETE_REQUIREMENTS'::character varying)::text, ('OUTSIDE_MANDATE'::character varying)::text, ('DUPLICATE_REFERRAL'::character varying)::text, ('CLIENT_WITHDREW'::character varying)::text, ('NO_SERVICE_CAPACITY'::character varying)::text, ('OTHER'::character varying)::text]))))
);


--
-- Name: service_requirements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.service_requirements (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    is_required boolean NOT NULL,
    service_id uuid NOT NULL,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: services; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.services (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    agcy_id uuid,
    processing_days integer,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT services_processing_days_check CHECK (((processing_days IS NULL) OR ((processing_days >= 0) AND (processing_days <= 365))))
);


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id uuid,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: survey_forms; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.survey_forms (
    id uuid NOT NULL,
    agency_id uuid NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    is_active boolean DEFAULT false NOT NULL,
    activated_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: survey_invitations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.survey_invitations (
    id uuid NOT NULL,
    survey_form_id uuid,
    case_id uuid NOT NULL,
    agency_id uuid NOT NULL,
    referral_id uuid NOT NULL,
    client_name character varying(255) NOT NULL,
    client_email character varying(255) NOT NULL,
    service_name character varying(255) NOT NULL,
    token character varying(64),
    expires_at timestamp(0) without time zone NOT NULL,
    submitted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    token_hash character varying(64),
    pending_token text,
    queued_at timestamp(0) with time zone
);


--
-- Name: survey_questions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.survey_questions (
    id uuid NOT NULL,
    survey_form_id uuid NOT NULL,
    type character varying(20) NOT NULL,
    label text NOT NULL,
    options jsonb,
    is_required boolean DEFAULT true NOT NULL,
    "order" integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: survey_responses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.survey_responses (
    id uuid NOT NULL,
    survey_invitation_id uuid NOT NULL,
    survey_question_id uuid,
    answer text,
    selected_options jsonb,
    created_at timestamp(0) without time zone
);


--
-- Name: system_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.system_settings (
    key character varying(255) NOT NULL,
    category character varying(255),
    value text,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_invites; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_invites (
    id uuid NOT NULL,
    email character varying(255) NOT NULL,
    role character varying(255) NOT NULL,
    agcy_id uuid,
    token character varying(255),
    expires_at timestamp(0) without time zone NOT NULL,
    created_by uuid NOT NULL,
    consumed_at timestamp(0) without time zone,
    cancelled_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    token_hash character varying(64)
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    role character varying(50) NOT NULL,
    agcy_id uuid,
    is_active boolean DEFAULT true NOT NULL,
    contact_number character varying(255),
    avatar_url text,
    "position" character varying(255),
    department character varying(255),
    office_location character varying(255),
    bio text,
    emergency_contact text,
    timezone character varying(255) DEFAULT 'Asia/Manila'::character varying,
    mfa_secret text,
    mfa_recovery_codes json,
    mfa_enabled_at timestamp(0) without time zone,
    notifications_config json,
    onboarding_completed_at timestamp(0) without time zone,
    onboarding_step character varying(100),
    profile_completed_at timestamp(0) without time zone,
    email_verified_at timestamp(0) without time zone,
    remember_token character varying(100),
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone,
    deleted_by uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    seen_page_guides json,
    checklist_progress json,
    mfa_last_totp_counter bigint,
    mfa_requirement_override character varying(20)
);


--
-- Name: audit_logs chain_seq; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs ALTER COLUMN chain_seq SET DEFAULT nextval('public.audit_logs_chain_seq_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: activity_events activity_events_audit_log_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_events
    ADD CONSTRAINT activity_events_audit_log_id_unique UNIQUE (audit_log_id);


--
-- Name: activity_events activity_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_events
    ADD CONSTRAINT activity_events_pkey PRIMARY KEY (id);


--
-- Name: agencies agencies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agencies
    ADD CONSTRAINT agencies_pkey PRIMARY KEY (id);


--
-- Name: agencies agencies_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agencies
    ADD CONSTRAINT agencies_slug_unique UNIQUE (slug);


--
-- Name: agency_thread_reads agency_thread_reads_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agency_thread_reads
    ADD CONSTRAINT agency_thread_reads_pkey PRIMARY KEY (user_id, case_id, peer_agency_id);


--
-- Name: audit_archives audit_archives_period_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_archives
    ADD CONSTRAINT audit_archives_period_unique UNIQUE (period);


--
-- Name: audit_archives audit_archives_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_archives
    ADD CONSTRAINT audit_archives_pkey PRIMARY KEY (id);


--
-- Name: audit_chain_checkpoints audit_chain_checkpoints_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_chain_checkpoints
    ADD CONSTRAINT audit_chain_checkpoints_pkey PRIMARY KEY (id);


--
-- Name: audit_logs audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: case_categories case_categories_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_categories
    ADD CONSTRAINT case_categories_name_unique UNIQUE (name);


--
-- Name: case_categories case_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_categories
    ADD CONSTRAINT case_categories_pkey PRIMARY KEY (id);


--
-- Name: case_category_agency case_category_agency_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_category_agency
    ADD CONSTRAINT case_category_agency_pkey PRIMARY KEY (case_category_id, agcy_id);


--
-- Name: case_documents case_documents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_documents
    ADD CONSTRAINT case_documents_pkey PRIMARY KEY (id);


--
-- Name: case_events case_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_events
    ADD CONSTRAINT case_events_pkey PRIMARY KEY (id);


--
-- Name: case_events case_events_sequence_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_events
    ADD CONSTRAINT case_events_sequence_unique UNIQUE (sequence);


--
-- Name: case_notifications case_notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_notifications
    ADD CONSTRAINT case_notifications_pkey PRIMARY KEY (id);


--
-- Name: case_number_counters case_number_counters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_number_counters
    ADD CONSTRAINT case_number_counters_pkey PRIMARY KEY (period);


--
-- Name: case_statuses case_statuses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_statuses
    ADD CONSTRAINT case_statuses_pkey PRIMARY KEY (id);


--
-- Name: case_statuses case_statuses_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_statuses
    ADD CONSTRAINT case_statuses_slug_unique UNIQUE (slug);


--
-- Name: cases cases_case_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cases
    ADD CONSTRAINT cases_case_number_unique UNIQUE (case_number);


--
-- Name: cases cases_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cases
    ADD CONSTRAINT cases_pkey PRIMARY KEY (id);


--
-- Name: cases cases_tracker_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cases
    ADD CONSTRAINT cases_tracker_number_unique UNIQUE (tracker_number);


--
-- Name: email_events email_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_events
    ADD CONSTRAINT email_events_pkey PRIMARY KEY (id);


--
-- Name: email_events email_events_svix_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_events
    ADD CONSTRAINT email_events_svix_id_unique UNIQUE (svix_id);


--
-- Name: email_logs email_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_logs
    ADD CONSTRAINT email_logs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: generated_documents generated_documents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.generated_documents
    ADD CONSTRAINT generated_documents_pkey PRIMARY KEY (id);


--
-- Name: helpdesk_article_feedback helpdesk_article_feedback_article_slug_identity_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.helpdesk_article_feedback
    ADD CONSTRAINT helpdesk_article_feedback_article_slug_identity_hash_unique UNIQUE (article_slug, identity_hash);


--
-- Name: helpdesk_article_feedback helpdesk_article_feedback_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.helpdesk_article_feedback
    ADD CONSTRAINT helpdesk_article_feedback_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: milestones milestones_client_request_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.milestones
    ADD CONSTRAINT milestones_client_request_id_unique UNIQUE (client_request_id);


--
-- Name: milestones milestones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.milestones
    ADD CONSTRAINT milestones_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: referral_attachments referral_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_attachments
    ADD CONSTRAINT referral_attachments_pkey PRIMARY KEY (id);


--
-- Name: referral_client_access_links referral_client_access_links_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_access_links
    ADD CONSTRAINT referral_client_access_links_pkey PRIMARY KEY (id);


--
-- Name: referral_client_access_links referral_client_access_links_token_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_access_links
    ADD CONSTRAINT referral_client_access_links_token_hash_unique UNIQUE (token_hash);


--
-- Name: referral_client_attachment_agencies referral_client_attachment_agencies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_attachment_agencies
    ADD CONSTRAINT referral_client_attachment_agencies_pkey PRIMARY KEY (attachment_id, agency_id);


--
-- Name: referral_client_message_attachments referral_client_message_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_message_attachments
    ADD CONSTRAINT referral_client_message_attachments_pkey PRIMARY KEY (id);


--
-- Name: referral_client_messages referral_client_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_messages
    ADD CONSTRAINT referral_client_messages_pkey PRIMARY KEY (id);


--
-- Name: referral_client_request_item_services referral_client_request_item_services_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_request_item_services
    ADD CONSTRAINT referral_client_request_item_services_pkey PRIMARY KEY (request_item_id, service_requirement_id);


--
-- Name: referral_client_request_items referral_client_request_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_request_items
    ADD CONSTRAINT referral_client_request_items_pkey PRIMARY KEY (id);


--
-- Name: referral_client_request_items referral_client_request_items_request_id_canonical_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_request_items
    ADD CONSTRAINT referral_client_request_items_request_id_canonical_key_unique UNIQUE (request_id, canonical_key);


--
-- Name: referral_client_requests referral_client_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_requests
    ADD CONSTRAINT referral_client_requests_pkey PRIMARY KEY (id);


--
-- Name: referral_comments referral_comments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_comments
    ADD CONSTRAINT referral_comments_pkey PRIMARY KEY (id);


--
-- Name: referral_messages referral_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_messages
    ADD CONSTRAINT referral_messages_pkey PRIMARY KEY (id);


--
-- Name: referral_service_requirements referral_service_requirements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_service_requirements
    ADD CONSTRAINT referral_service_requirements_pkey PRIMARY KEY (id);


--
-- Name: referral_services referral_services_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_services
    ADD CONSTRAINT referral_services_pkey PRIMARY KEY (referral_id, service_id);


--
-- Name: referrals referrals_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referrals
    ADD CONSTRAINT referrals_pkey PRIMARY KEY (id);


--
-- Name: service_requirements service_requirements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.service_requirements
    ADD CONSTRAINT service_requirements_pkey PRIMARY KEY (id);


--
-- Name: services services_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.services
    ADD CONSTRAINT services_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: survey_forms survey_forms_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_forms
    ADD CONSTRAINT survey_forms_pkey PRIMARY KEY (id);


--
-- Name: survey_invitations survey_invitations_case_agency_referral_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_invitations
    ADD CONSTRAINT survey_invitations_case_agency_referral_unique UNIQUE (case_id, agency_id, referral_id);


--
-- Name: survey_invitations survey_invitations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_invitations
    ADD CONSTRAINT survey_invitations_pkey PRIMARY KEY (id);


--
-- Name: survey_invitations survey_invitations_token_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_invitations
    ADD CONSTRAINT survey_invitations_token_hash_unique UNIQUE (token_hash);


--
-- Name: survey_invitations survey_invitations_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_invitations
    ADD CONSTRAINT survey_invitations_token_unique UNIQUE (token);


--
-- Name: survey_questions survey_questions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_questions
    ADD CONSTRAINT survey_questions_pkey PRIMARY KEY (id);


--
-- Name: survey_responses survey_responses_invitation_question_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_responses
    ADD CONSTRAINT survey_responses_invitation_question_unique UNIQUE (survey_invitation_id, survey_question_id);


--
-- Name: survey_responses survey_responses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_responses
    ADD CONSTRAINT survey_responses_pkey PRIMARY KEY (id);


--
-- Name: system_settings system_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.system_settings
    ADD CONSTRAINT system_settings_pkey PRIMARY KEY (key);


--
-- Name: user_invites user_invites_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_invites
    ADD CONSTRAINT user_invites_pkey PRIMARY KEY (id);


--
-- Name: user_invites user_invites_token_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_invites
    ADD CONSTRAINT user_invites_token_hash_unique UNIQUE (token_hash);


--
-- Name: user_invites user_invites_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_invites
    ADD CONSTRAINT user_invites_token_unique UNIQUE (token);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: activity_events_action_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_events_action_index ON public.activity_events USING btree (action);


--
-- Name: activity_events_entity_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_events_entity_id_index ON public.activity_events USING btree (entity_id);


--
-- Name: activity_events_module_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_events_module_index ON public.activity_events USING btree (module);


--
-- Name: activity_events_occurred_at_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_events_occurred_at_id_index ON public.activity_events USING btree (occurred_at, id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: case_events_case_id_occurred_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX case_events_case_id_occurred_at_index ON public.case_events USING btree (case_id, occurred_at);


--
-- Name: case_events_referral_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX case_events_referral_id_index ON public.case_events USING btree (referral_id);


--
-- Name: case_notifications_case_id_client_email_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX case_notifications_case_id_client_email_index ON public.case_notifications USING btree (case_id, client_email);


--
-- Name: case_notifications_read_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX case_notifications_read_at_index ON public.case_notifications USING btree (read_at);


--
-- Name: cases_assigned_case_manager_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cases_assigned_case_manager_id_index ON public.cases USING btree (assigned_case_manager_id);


--
-- Name: cases_is_deleted_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cases_is_deleted_index ON public.cases USING btree (is_deleted);


--
-- Name: cases_ofw_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cases_ofw_user_id_index ON public.cases USING btree (ofw_user_id);


--
-- Name: cases_status_deleted_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cases_status_deleted_at_index ON public.cases USING btree (status, deleted_at);


--
-- Name: cases_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cases_status_index ON public.cases USING btree (status);


--
-- Name: cases_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cases_user_id_index ON public.cases USING btree (user_id);


--
-- Name: email_events_email_log_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_events_email_log_id_index ON public.email_events USING btree (email_log_id);


--
-- Name: email_events_event_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_events_event_type_index ON public.email_events USING btree (event_type);


--
-- Name: email_events_provider_message_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_events_provider_message_id_index ON public.email_events USING btree (provider_message_id);


--
-- Name: email_logs_provider_message_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_logs_provider_message_id_index ON public.email_logs USING btree (provider_message_id);


--
-- Name: generated_documents_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX generated_documents_status_index ON public.generated_documents USING btree (status);


--
-- Name: generated_documents_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX generated_documents_user_id_index ON public.generated_documents USING btree (user_id);


--
-- Name: helpdesk_article_feedback_updated_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX helpdesk_article_feedback_updated_at_index ON public.helpdesk_article_feedback USING btree (updated_at);


--
-- Name: idx_audit_logs_action_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_audit_logs_action_time ON public.audit_logs USING btree (action, "timestamp" DESC);


--
-- Name: idx_audit_logs_category_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_audit_logs_category_time ON public.audit_logs USING btree (category, "timestamp" DESC);


--
-- Name: idx_audit_logs_chain_seq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_audit_logs_chain_seq ON public.audit_logs USING btree (chain_seq);


--
-- Name: idx_audit_logs_description_trgm; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_audit_logs_description_trgm ON public.audit_logs USING gin (description public.gin_trgm_ops);


--
-- Name: idx_audit_logs_entity_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_audit_logs_entity_id ON public.audit_logs USING btree (entity_id);


--
-- Name: idx_audit_logs_entity_lookup; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_audit_logs_entity_lookup ON public.audit_logs USING btree (module, entity_id, "timestamp" DESC);


--
-- Name: idx_audit_logs_new_value; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_audit_logs_new_value ON public.audit_logs USING gin (new_value jsonb_path_ops);


--
-- Name: idx_audit_logs_old_value; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_audit_logs_old_value ON public.audit_logs USING gin (old_value jsonb_path_ops);


--
-- Name: idx_audit_logs_timestamp; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_audit_logs_timestamp ON public.audit_logs USING btree ("timestamp" DESC);


--
-- Name: idx_audit_logs_user_action; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_audit_logs_user_action ON public.audit_logs USING btree (user_id, action, "timestamp" DESC);


--
-- Name: idx_case_documents_case_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_case_documents_case_id ON public.case_documents USING btree (case_id);


--
-- Name: idx_case_notifications_case_email_created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_case_notifications_case_email_created ON public.case_notifications USING btree (case_id, client_email, created_at DESC);


--
-- Name: idx_cases_case_number_trgm; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cases_case_number_trgm ON public.cases USING gin (case_number public.gin_trgm_ops);


--
-- Name: idx_cases_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cases_status ON public.cases USING btree (status);


--
-- Name: idx_cases_status_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cases_status_active ON public.cases USING btree (status) WHERE (deleted_at IS NULL);


--
-- Name: idx_cases_status_user_created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cases_status_user_created ON public.cases USING btree (status, user_id, created_at DESC);


--
-- Name: idx_cases_tracker_number_trgm; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cases_tracker_number_trgm ON public.cases USING gin (tracker_number public.gin_trgm_ops);


--
-- Name: idx_cases_user_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cases_user_id ON public.cases USING btree (user_id);


--
-- Name: idx_email_logs_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_email_logs_status ON public.email_logs USING btree (status);


--
-- Name: idx_email_logs_to_email; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_email_logs_to_email ON public.email_logs USING btree (to_email);


--
-- Name: idx_milestones_refr_created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_milestones_refr_created ON public.milestones USING btree (refr_id, created_at DESC);


--
-- Name: idx_milestones_refr_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_milestones_refr_id ON public.milestones USING btree (refr_id);


--
-- Name: idx_notifications_notifiable_read; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notifications_notifiable_read ON public.notifications USING btree (notifiable_id, read_at);


--
-- Name: idx_referral_attachments_referral_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_referral_attachments_referral_id ON public.referral_attachments USING btree (referral_id);


--
-- Name: idx_referral_comments_refr_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_referral_comments_refr_id ON public.referral_comments USING btree (refr_id);


--
-- Name: idx_referrals_agcy_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_referrals_agcy_id ON public.referrals USING btree (agcy_id);


--
-- Name: idx_referrals_agcy_id_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_referrals_agcy_id_active ON public.referrals USING btree (agcy_id) WHERE (deleted_at IS NULL);


--
-- Name: idx_referrals_agcy_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_referrals_agcy_status ON public.referrals USING btree (agcy_id, status);


--
-- Name: idx_referrals_case_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_referrals_case_id ON public.referrals USING btree (case_id);


--
-- Name: idx_referrals_case_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_referrals_case_status ON public.referrals USING btree (case_id, status);


--
-- Name: idx_referrals_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_referrals_status ON public.referrals USING btree (status);


--
-- Name: idx_referrals_status_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_referrals_status_active ON public.referrals USING btree (status) WHERE (deleted_at IS NULL);


--
-- Name: idx_referrals_status_created_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_referrals_status_created_active ON public.referrals USING btree (status, created_at) WHERE ((status)::text = ANY (ARRAY[('PENDING'::character varying)::text, ('PROCESSING'::character varying)::text, ('FOR_COMPLIANCE'::character varying)::text]));


--
-- Name: idx_services_agcy_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_services_agcy_id ON public.services USING btree (agcy_id);


--
-- Name: idx_users_agcy_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_users_agcy_id ON public.users USING btree (agcy_id);


--
-- Name: idx_users_role; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_users_role ON public.users USING btree (role);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: milestones_refr_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX milestones_refr_id_index ON public.milestones USING btree (refr_id);


--
-- Name: notifications_notifiable_id_notifiable_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_notifiable_id_notifiable_type_index ON public.notifications USING btree (notifiable_id, notifiable_type);


--
-- Name: referral_client_access_links_request_id_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX referral_client_access_links_request_id_expires_at_index ON public.referral_client_access_links USING btree (request_id, expires_at);


--
-- Name: referral_client_message_attachments_message_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX referral_client_message_attachments_message_id_index ON public.referral_client_message_attachments USING btree (message_id);


--
-- Name: referral_client_message_attachments_request_item_id_review_stat; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX referral_client_message_attachments_request_item_id_review_stat ON public.referral_client_message_attachments USING btree (request_item_id, review_status);


--
-- Name: referral_client_messages_request_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX referral_client_messages_request_id_created_at_index ON public.referral_client_messages USING btree (request_id, created_at);


--
-- Name: referral_client_request_items_request_id_sort_order_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX referral_client_request_items_request_id_sort_order_index ON public.referral_client_request_items USING btree (request_id, sort_order);


--
-- Name: referral_client_requests_referral_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX referral_client_requests_referral_id_status_index ON public.referral_client_requests USING btree (referral_id, status);


--
-- Name: referral_messages_referral_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX referral_messages_referral_id_created_at_index ON public.referral_messages USING btree (referral_id, created_at);


--
-- Name: referral_service_requirements_referral_id_service_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX referral_service_requirements_referral_id_service_id_index ON public.referral_service_requirements USING btree (referral_id, service_id);


--
-- Name: referrals_agcy_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX referrals_agcy_id_index ON public.referrals USING btree (agcy_id);


--
-- Name: referrals_case_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX referrals_case_id_index ON public.referrals USING btree (case_id);


--
-- Name: referrals_rejection_reason_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX referrals_rejection_reason_index ON public.referrals USING btree (rejection_reason);


--
-- Name: referrals_required_services_trgm; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX referrals_required_services_trgm ON public.referrals USING gin (required_services public.gin_trgm_ops);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: survey_forms_agency_active_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX survey_forms_agency_active_unique ON public.survey_forms USING btree (agency_id) WHERE (is_active = true);


--
-- Name: survey_invitations_token_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX survey_invitations_token_index ON public.survey_invitations USING btree (token);


--
-- Name: survey_questions_survey_form_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX survey_questions_survey_form_id_index ON public.survey_questions USING btree (survey_form_id);


--
-- Name: survey_responses_survey_invitation_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX survey_responses_survey_invitation_id_index ON public.survey_responses USING btree (survey_invitation_id);


--
-- Name: audit_logs trg_audit_logs_append_only; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_audit_logs_append_only BEFORE DELETE OR UPDATE ON public.audit_logs FOR EACH ROW EXECUTE FUNCTION public.block_audit_log_modification();


--
-- Name: activity_events activity_events_actor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_events
    ADD CONSTRAINT activity_events_actor_id_foreign FOREIGN KEY (actor_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: activity_events activity_events_audit_log_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_events
    ADD CONSTRAINT activity_events_audit_log_id_foreign FOREIGN KEY (audit_log_id) REFERENCES public.audit_logs(id) ON DELETE SET NULL;


--
-- Name: agencies agencies_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agencies
    ADD CONSTRAINT agencies_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: agency_thread_reads agency_thread_reads_case_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agency_thread_reads
    ADD CONSTRAINT agency_thread_reads_case_id_foreign FOREIGN KEY (case_id) REFERENCES public.cases(id) ON DELETE CASCADE;


--
-- Name: agency_thread_reads agency_thread_reads_peer_agency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agency_thread_reads
    ADD CONSTRAINT agency_thread_reads_peer_agency_id_foreign FOREIGN KEY (peer_agency_id) REFERENCES public.agencies(id) ON DELETE CASCADE;


--
-- Name: agency_thread_reads agency_thread_reads_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agency_thread_reads
    ADD CONSTRAINT agency_thread_reads_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: audit_logs audit_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: case_categories case_categories_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_categories
    ADD CONSTRAINT case_categories_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: case_category_agency case_category_agency_agcy_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_category_agency
    ADD CONSTRAINT case_category_agency_agcy_id_foreign FOREIGN KEY (agcy_id) REFERENCES public.agencies(id) ON DELETE CASCADE;


--
-- Name: case_category_agency case_category_agency_case_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_category_agency
    ADD CONSTRAINT case_category_agency_case_category_id_foreign FOREIGN KEY (case_category_id) REFERENCES public.case_categories(id) ON DELETE CASCADE;


--
-- Name: case_documents case_documents_case_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_documents
    ADD CONSTRAINT case_documents_case_id_foreign FOREIGN KEY (case_id) REFERENCES public.cases(id) ON DELETE RESTRICT;


--
-- Name: case_documents case_documents_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_documents
    ADD CONSTRAINT case_documents_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: case_documents case_documents_referral_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_documents
    ADD CONSTRAINT case_documents_referral_id_foreign FOREIGN KEY (referral_id) REFERENCES public.referrals(id) ON DELETE CASCADE;


--
-- Name: case_documents case_documents_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_documents
    ADD CONSTRAINT case_documents_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: case_events case_events_case_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_events
    ADD CONSTRAINT case_events_case_id_foreign FOREIGN KEY (case_id) REFERENCES public.cases(id) ON DELETE RESTRICT;


--
-- Name: case_events case_events_referral_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_events
    ADD CONSTRAINT case_events_referral_id_foreign FOREIGN KEY (referral_id) REFERENCES public.referrals(id) ON DELETE RESTRICT;


--
-- Name: case_notifications case_notifications_case_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_notifications
    ADD CONSTRAINT case_notifications_case_id_foreign FOREIGN KEY (case_id) REFERENCES public.cases(id) ON DELETE CASCADE;


--
-- Name: case_statuses case_statuses_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.case_statuses
    ADD CONSTRAINT case_statuses_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: cases cases_assigned_case_manager_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cases
    ADD CONSTRAINT cases_assigned_case_manager_id_foreign FOREIGN KEY (assigned_case_manager_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: cases cases_confirmed_assessment_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cases
    ADD CONSTRAINT cases_confirmed_assessment_by_foreign FOREIGN KEY (confirmed_assessment_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: cases cases_consent_accepted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cases
    ADD CONSTRAINT cases_consent_accepted_by_foreign FOREIGN KEY (consent_accepted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: cases cases_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cases
    ADD CONSTRAINT cases_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: cases cases_document_requested_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cases
    ADD CONSTRAINT cases_document_requested_by_foreign FOREIGN KEY (document_requested_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: cases cases_ofw_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cases
    ADD CONSTRAINT cases_ofw_user_id_foreign FOREIGN KEY (ofw_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: cases cases_proposed_assessment_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cases
    ADD CONSTRAINT cases_proposed_assessment_by_foreign FOREIGN KEY (proposed_assessment_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: cases cases_reviewed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cases
    ADD CONSTRAINT cases_reviewed_by_foreign FOREIGN KEY (reviewed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: cases cases_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cases
    ADD CONSTRAINT cases_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: email_events email_events_email_log_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_events
    ADD CONSTRAINT email_events_email_log_id_foreign FOREIGN KEY (email_log_id) REFERENCES public.email_logs(id) ON DELETE SET NULL;


--
-- Name: generated_documents generated_documents_case_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.generated_documents
    ADD CONSTRAINT generated_documents_case_id_foreign FOREIGN KEY (case_id) REFERENCES public.cases(id) ON DELETE SET NULL;


--
-- Name: generated_documents generated_documents_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.generated_documents
    ADD CONSTRAINT generated_documents_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: milestones milestones_client_request_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.milestones
    ADD CONSTRAINT milestones_client_request_id_foreign FOREIGN KEY (client_request_id) REFERENCES public.referral_client_requests(id) ON DELETE SET NULL;


--
-- Name: milestones milestones_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.milestones
    ADD CONSTRAINT milestones_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: milestones milestones_refr_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.milestones
    ADD CONSTRAINT milestones_refr_id_foreign FOREIGN KEY (refr_id) REFERENCES public.referrals(id) ON DELETE RESTRICT;


--
-- Name: milestones milestones_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.milestones
    ADD CONSTRAINT milestones_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: referral_attachments referral_attachments_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_attachments
    ADD CONSTRAINT referral_attachments_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: referral_attachments referral_attachments_referral_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_attachments
    ADD CONSTRAINT referral_attachments_referral_id_foreign FOREIGN KEY (referral_id) REFERENCES public.referrals(id) ON DELETE RESTRICT;


--
-- Name: referral_attachments referral_attachments_replaces_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_attachments
    ADD CONSTRAINT referral_attachments_replaces_id_foreign FOREIGN KEY (replaces_id) REFERENCES public.referral_attachments(id) ON DELETE SET NULL;


--
-- Name: referral_attachments referral_attachments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_attachments
    ADD CONSTRAINT referral_attachments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: referral_client_access_links referral_client_access_links_issued_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_access_links
    ADD CONSTRAINT referral_client_access_links_issued_by_foreign FOREIGN KEY (issued_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: referral_client_access_links referral_client_access_links_request_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_access_links
    ADD CONSTRAINT referral_client_access_links_request_id_foreign FOREIGN KEY (request_id) REFERENCES public.referral_client_requests(id) ON DELETE RESTRICT;


--
-- Name: referral_client_access_links referral_client_access_links_revoked_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_access_links
    ADD CONSTRAINT referral_client_access_links_revoked_by_foreign FOREIGN KEY (revoked_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: referral_client_attachment_agencies referral_client_attachment_agencies_agency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_attachment_agencies
    ADD CONSTRAINT referral_client_attachment_agencies_agency_id_foreign FOREIGN KEY (agency_id) REFERENCES public.agencies(id) ON DELETE CASCADE;


--
-- Name: referral_client_attachment_agencies referral_client_attachment_agencies_attachment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_attachment_agencies
    ADD CONSTRAINT referral_client_attachment_agencies_attachment_id_foreign FOREIGN KEY (attachment_id) REFERENCES public.referral_client_message_attachments(id) ON DELETE CASCADE;


--
-- Name: referral_client_message_attachments referral_client_message_attachments_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_message_attachments
    ADD CONSTRAINT referral_client_message_attachments_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: referral_client_message_attachments referral_client_message_attachments_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_message_attachments
    ADD CONSTRAINT referral_client_message_attachments_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.referral_client_messages(id) ON DELETE CASCADE;


--
-- Name: referral_client_message_attachments referral_client_message_attachments_request_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_message_attachments
    ADD CONSTRAINT referral_client_message_attachments_request_item_id_foreign FOREIGN KEY (request_item_id) REFERENCES public.referral_client_request_items(id) ON DELETE SET NULL;


--
-- Name: referral_client_messages referral_client_messages_access_link_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_messages
    ADD CONSTRAINT referral_client_messages_access_link_id_foreign FOREIGN KEY (access_link_id) REFERENCES public.referral_client_access_links(id) ON DELETE RESTRICT;


--
-- Name: referral_client_messages referral_client_messages_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_messages
    ADD CONSTRAINT referral_client_messages_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: referral_client_messages referral_client_messages_request_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_messages
    ADD CONSTRAINT referral_client_messages_request_id_foreign FOREIGN KEY (request_id) REFERENCES public.referral_client_requests(id) ON DELETE CASCADE;


--
-- Name: referral_client_messages referral_client_messages_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_messages
    ADD CONSTRAINT referral_client_messages_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: referral_client_request_item_services referral_client_request_item_services_request_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_request_item_services
    ADD CONSTRAINT referral_client_request_item_services_request_item_id_foreign FOREIGN KEY (request_item_id) REFERENCES public.referral_client_request_items(id) ON DELETE CASCADE;


--
-- Name: referral_client_request_item_services referral_client_request_item_services_service_requirement_id_fo; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_request_item_services
    ADD CONSTRAINT referral_client_request_item_services_service_requirement_id_fo FOREIGN KEY (service_requirement_id) REFERENCES public.referral_service_requirements(id) ON DELETE CASCADE;


--
-- Name: referral_client_request_items referral_client_request_items_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_request_items
    ADD CONSTRAINT referral_client_request_items_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: referral_client_request_items referral_client_request_items_request_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_request_items
    ADD CONSTRAINT referral_client_request_items_request_id_foreign FOREIGN KEY (request_id) REFERENCES public.referral_client_requests(id) ON DELETE CASCADE;


--
-- Name: referral_client_request_items referral_client_request_items_source_requirement_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_request_items
    ADD CONSTRAINT referral_client_request_items_source_requirement_id_foreign FOREIGN KEY (source_requirement_id) REFERENCES public.referral_service_requirements(id) ON DELETE SET NULL;


--
-- Name: referral_client_requests referral_client_requests_creator_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_requests
    ADD CONSTRAINT referral_client_requests_creator_user_id_foreign FOREIGN KEY (creator_user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: referral_client_requests referral_client_requests_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_requests
    ADD CONSTRAINT referral_client_requests_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: referral_client_requests referral_client_requests_referral_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_client_requests
    ADD CONSTRAINT referral_client_requests_referral_id_foreign FOREIGN KEY (referral_id) REFERENCES public.referrals(id) ON DELETE RESTRICT;


--
-- Name: referral_comments referral_comments_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_comments
    ADD CONSTRAINT referral_comments_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: referral_comments referral_comments_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_comments
    ADD CONSTRAINT referral_comments_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.referral_comments(id) ON DELETE RESTRICT;


--
-- Name: referral_comments referral_comments_refr_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_comments
    ADD CONSTRAINT referral_comments_refr_id_foreign FOREIGN KEY (refr_id) REFERENCES public.referrals(id) ON DELETE RESTRICT;


--
-- Name: referral_comments referral_comments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_comments
    ADD CONSTRAINT referral_comments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: referral_messages referral_messages_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_messages
    ADD CONSTRAINT referral_messages_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: referral_messages referral_messages_referral_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_messages
    ADD CONSTRAINT referral_messages_referral_id_foreign FOREIGN KEY (referral_id) REFERENCES public.referrals(id) ON DELETE CASCADE;


--
-- Name: referral_messages referral_messages_sender_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_messages
    ADD CONSTRAINT referral_messages_sender_user_id_foreign FOREIGN KEY (sender_user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: referral_service_requirements referral_service_requirements_referral_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_service_requirements
    ADD CONSTRAINT referral_service_requirements_referral_id_foreign FOREIGN KEY (referral_id) REFERENCES public.referrals(id) ON DELETE CASCADE;


--
-- Name: referral_service_requirements referral_service_requirements_service_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_service_requirements
    ADD CONSTRAINT referral_service_requirements_service_id_foreign FOREIGN KEY (service_id) REFERENCES public.services(id) ON DELETE CASCADE;


--
-- Name: referral_services referral_services_referral_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_services
    ADD CONSTRAINT referral_services_referral_id_foreign FOREIGN KEY (referral_id) REFERENCES public.referrals(id) ON DELETE CASCADE;


--
-- Name: referral_services referral_services_service_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referral_services
    ADD CONSTRAINT referral_services_service_id_foreign FOREIGN KEY (service_id) REFERENCES public.services(id) ON DELETE CASCADE;


--
-- Name: referrals referrals_agcy_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referrals
    ADD CONSTRAINT referrals_agcy_id_foreign FOREIGN KEY (agcy_id) REFERENCES public.agencies(id) ON DELETE RESTRICT;


--
-- Name: referrals referrals_case_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referrals
    ADD CONSTRAINT referrals_case_id_foreign FOREIGN KEY (case_id) REFERENCES public.cases(id) ON DELETE RESTRICT;


--
-- Name: referrals referrals_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referrals
    ADD CONSTRAINT referrals_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.case_categories(id) ON DELETE RESTRICT;


--
-- Name: referrals referrals_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.referrals
    ADD CONSTRAINT referrals_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: service_requirements service_requirements_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.service_requirements
    ADD CONSTRAINT service_requirements_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: service_requirements service_requirements_service_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.service_requirements
    ADD CONSTRAINT service_requirements_service_id_foreign FOREIGN KEY (service_id) REFERENCES public.services(id) ON DELETE RESTRICT;


--
-- Name: services services_agcy_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.services
    ADD CONSTRAINT services_agcy_id_foreign FOREIGN KEY (agcy_id) REFERENCES public.agencies(id) ON DELETE RESTRICT;


--
-- Name: services services_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.services
    ADD CONSTRAINT services_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: survey_forms survey_forms_agency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_forms
    ADD CONSTRAINT survey_forms_agency_id_foreign FOREIGN KEY (agency_id) REFERENCES public.agencies(id) ON DELETE CASCADE;


--
-- Name: survey_invitations survey_invitations_agency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_invitations
    ADD CONSTRAINT survey_invitations_agency_id_foreign FOREIGN KEY (agency_id) REFERENCES public.agencies(id) ON DELETE CASCADE;


--
-- Name: survey_invitations survey_invitations_case_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_invitations
    ADD CONSTRAINT survey_invitations_case_id_foreign FOREIGN KEY (case_id) REFERENCES public.cases(id) ON DELETE CASCADE;


--
-- Name: survey_invitations survey_invitations_referral_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_invitations
    ADD CONSTRAINT survey_invitations_referral_id_foreign FOREIGN KEY (referral_id) REFERENCES public.referrals(id) ON DELETE CASCADE;


--
-- Name: survey_invitations survey_invitations_survey_form_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_invitations
    ADD CONSTRAINT survey_invitations_survey_form_id_foreign FOREIGN KEY (survey_form_id) REFERENCES public.survey_forms(id) ON DELETE SET NULL;


--
-- Name: survey_questions survey_questions_survey_form_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_questions
    ADD CONSTRAINT survey_questions_survey_form_id_foreign FOREIGN KEY (survey_form_id) REFERENCES public.survey_forms(id) ON DELETE CASCADE;


--
-- Name: survey_responses survey_responses_survey_invitation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_responses
    ADD CONSTRAINT survey_responses_survey_invitation_id_foreign FOREIGN KEY (survey_invitation_id) REFERENCES public.survey_invitations(id) ON DELETE CASCADE;


--
-- Name: survey_responses survey_responses_survey_question_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_responses
    ADD CONSTRAINT survey_responses_survey_question_id_foreign FOREIGN KEY (survey_question_id) REFERENCES public.survey_questions(id) ON DELETE SET NULL;


--
-- Name: user_invites user_invites_agcy_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_invites
    ADD CONSTRAINT user_invites_agcy_id_foreign FOREIGN KEY (agcy_id) REFERENCES public.agencies(id) ON DELETE SET NULL;


--
-- Name: user_invites user_invites_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_invites
    ADD CONSTRAINT user_invites_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: users users_agcy_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_agcy_id_foreign FOREIGN KEY (agcy_id) REFERENCES public.agencies(id) ON DELETE RESTRICT;


--
-- Name: users users_deleted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_deleted_by_foreign FOREIGN KEY (deleted_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: case_documents admin_all_case_documents; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY admin_all_case_documents ON public.case_documents USING ((current_setting('app.user_role'::text, true) = 'ADMIN'::text)) WITH CHECK ((current_setting('app.user_role'::text, true) = 'ADMIN'::text));


--
-- Name: case_notifications admin_all_case_notifications; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY admin_all_case_notifications ON public.case_notifications USING ((current_setting('app.user_role'::text, true) = 'ADMIN'::text)) WITH CHECK ((current_setting('app.user_role'::text, true) = 'ADMIN'::text));


--
-- Name: cases admin_all_cases; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY admin_all_cases ON public.cases USING ((current_setting('app.user_role'::text, true) = 'ADMIN'::text)) WITH CHECK ((current_setting('app.user_role'::text, true) = 'ADMIN'::text));


--
-- Name: milestones admin_all_milestones; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY admin_all_milestones ON public.milestones USING ((current_setting('app.user_role'::text, true) = 'ADMIN'::text)) WITH CHECK ((current_setting('app.user_role'::text, true) = 'ADMIN'::text));


--
-- Name: referral_attachments admin_all_referral_attachments; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY admin_all_referral_attachments ON public.referral_attachments USING ((current_setting('app.user_role'::text, true) = 'ADMIN'::text)) WITH CHECK ((current_setting('app.user_role'::text, true) = 'ADMIN'::text));


--
-- Name: referral_comments admin_all_referral_comments; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY admin_all_referral_comments ON public.referral_comments USING ((current_setting('app.user_role'::text, true) = 'ADMIN'::text)) WITH CHECK ((current_setting('app.user_role'::text, true) = 'ADMIN'::text));


--
-- Name: referrals admin_all_referrals; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY admin_all_referrals ON public.referrals USING ((current_setting('app.user_role'::text, true) = 'ADMIN'::text)) WITH CHECK ((current_setting('app.user_role'::text, true) = 'ADMIN'::text));


--
-- Name: cases agency_referred_cases; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY agency_referred_cases ON public.cases USING (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (id IN ( SELECT r.case_id
   FROM public.referrals r
  WHERE (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid)))))));


--
-- Name: agency_thread_reads; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.agency_thread_reads ENABLE ROW LEVEL SECURITY;

--
-- Name: agency_thread_reads agency_thread_reads_agency_own; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY agency_thread_reads_agency_own ON public.agency_thread_reads USING (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (user_id = (current_setting('app.current_user_id'::text, true))::uuid) AND (EXISTS ( SELECT 1
   FROM public.referrals r
  WHERE ((r.case_id = agency_thread_reads.case_id) AND (r.agcy_id = agency_thread_reads.peer_agency_id)))) AND (EXISTS ( SELECT 1
   FROM public.referrals r2
  WHERE ((r2.case_id = agency_thread_reads.case_id) AND (r2.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid)))))))) WITH CHECK (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (user_id = (current_setting('app.current_user_id'::text, true))::uuid) AND (EXISTS ( SELECT 1
   FROM public.referrals r
  WHERE ((r.case_id = agency_thread_reads.case_id) AND (r.agcy_id = agency_thread_reads.peer_agency_id)))) AND (EXISTS ( SELECT 1
   FROM public.referrals r2
  WHERE ((r2.case_id = agency_thread_reads.case_id) AND (r2.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid))))))));


--
-- Name: case_documents; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.case_documents ENABLE ROW LEVEL SECURITY;

--
-- Name: case_documents case_documents_agency_referred; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY case_documents_agency_referred ON public.case_documents USING (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (referral_id IS NOT NULL) AND (EXISTS ( SELECT 1
   FROM public.referrals r
  WHERE ((r.id = case_documents.referral_id) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid))))))));


--
-- Name: case_documents case_manager_all_access_case_documents; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY case_manager_all_access_case_documents ON public.case_documents USING ((current_setting('app.user_role'::text, true) = 'CASE_MANAGER'::text));


--
-- Name: case_notifications case_manager_all_access_case_notifications; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY case_manager_all_access_case_notifications ON public.case_notifications USING ((current_setting('app.user_role'::text, true) = 'CASE_MANAGER'::text));


--
-- Name: cases case_manager_all_access_cases; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY case_manager_all_access_cases ON public.cases USING ((current_setting('app.user_role'::text, true) = 'CASE_MANAGER'::text));


--
-- Name: milestones case_manager_all_access_milestones; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY case_manager_all_access_milestones ON public.milestones USING ((current_setting('app.user_role'::text, true) = 'CASE_MANAGER'::text));


--
-- Name: referral_attachments case_manager_all_access_referral_attachments; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY case_manager_all_access_referral_attachments ON public.referral_attachments USING ((current_setting('app.user_role'::text, true) = 'CASE_MANAGER'::text));


--
-- Name: referral_comments case_manager_all_access_referral_comments; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY case_manager_all_access_referral_comments ON public.referral_comments USING ((current_setting('app.user_role'::text, true) = 'CASE_MANAGER'::text));


--
-- Name: referrals case_manager_all_access_referrals; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY case_manager_all_access_referrals ON public.referrals USING ((current_setting('app.user_role'::text, true) = 'CASE_MANAGER'::text));


--
-- Name: case_notifications; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.case_notifications ENABLE ROW LEVEL SECURITY;

--
-- Name: case_notifications case_notifications_agency_referred; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY case_notifications_agency_referred ON public.case_notifications USING (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (EXISTS ( SELECT 1
   FROM (public.cases c
     JOIN public.referrals r ON ((r.case_id = c.id)))
  WHERE ((c.id = r.case_id) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid))))))));


--
-- Name: cases; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.cases ENABLE ROW LEVEL SECURITY;

--
-- Name: milestones; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.milestones ENABLE ROW LEVEL SECURITY;

--
-- Name: milestones milestones_agency_referred; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY milestones_agency_referred ON public.milestones USING (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (EXISTS ( SELECT 1
   FROM public.referrals r
  WHERE ((r.id = milestones.refr_id) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid))))))));


--
-- Name: referral_attachments; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.referral_attachments ENABLE ROW LEVEL SECURITY;

--
-- Name: referral_attachments referral_attachments_agency_referred; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_attachments_agency_referred ON public.referral_attachments USING (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (EXISTS ( SELECT 1
   FROM public.referrals r
  WHERE ((r.id = referral_attachments.referral_id) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid))))))));


--
-- Name: referral_client_access_links; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.referral_client_access_links ENABLE ROW LEVEL SECURITY;

--
-- Name: referral_client_access_links referral_client_access_links_admin_all; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_access_links_admin_all ON public.referral_client_access_links USING ((current_setting('app.user_role'::text, true) = 'ADMIN'::text)) WITH CHECK ((current_setting('app.user_role'::text, true) = 'ADMIN'::text));


--
-- Name: referral_client_access_links referral_client_access_links_agency_own; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_access_links_agency_own ON public.referral_client_access_links USING (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (EXISTS ( SELECT 1
   FROM (public.referral_client_requests q
     JOIN public.referrals r ON ((r.id = q.referral_id)))
  WHERE ((q.id = referral_client_access_links.request_id) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid)))))))) WITH CHECK (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (EXISTS ( SELECT 1
   FROM (public.referral_client_requests q
     JOIN public.referrals r ON ((r.id = q.referral_id)))
  WHERE ((q.id = referral_client_access_links.request_id) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid))))))));


--
-- Name: referral_client_access_links referral_client_access_links_capability_session; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_access_links_capability_session ON public.referral_client_access_links FOR SELECT USING (((id)::text = NULLIF(current_setting('app.client_link_id'::text, true), ''::text)));


--
-- Name: referral_client_access_links referral_client_access_links_case_manager_read; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_access_links_case_manager_read ON public.referral_client_access_links FOR SELECT USING (((current_setting('app.user_role'::text, true) = 'CASE_MANAGER'::text) AND (EXISTS ( SELECT 1
   FROM ((public.referral_client_requests q
     JOIN public.referrals r ON ((r.id = q.referral_id)))
     JOIN public.cases c ON ((c.id = r.case_id)))
  WHERE ((q.id = referral_client_access_links.request_id) AND (c.user_id = (current_setting('app.current_user_id'::text, true))::uuid))))));


--
-- Name: referral_client_access_links referral_client_access_links_case_manager_revoke; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_access_links_case_manager_revoke ON public.referral_client_access_links FOR UPDATE USING (((current_setting('app.user_role'::text, true) = 'CASE_MANAGER'::text) AND (EXISTS ( SELECT 1
   FROM ((public.referral_client_requests q
     JOIN public.referrals r ON ((r.id = q.referral_id)))
     JOIN public.cases c ON ((c.id = r.case_id)))
  WHERE ((q.id = referral_client_access_links.request_id) AND (c.user_id = (current_setting('app.current_user_id'::text, true))::uuid)))))) WITH CHECK (((revoked_at IS NOT NULL) AND (revoked_by = (current_setting('app.current_user_id'::text, true))::uuid)));


--
-- Name: referral_client_message_attachments; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.referral_client_message_attachments ENABLE ROW LEVEL SECURITY;

--
-- Name: referral_client_message_attachments referral_client_message_attachments_admin_all; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_message_attachments_admin_all ON public.referral_client_message_attachments USING ((current_setting('app.user_role'::text, true) = 'ADMIN'::text)) WITH CHECK ((current_setting('app.user_role'::text, true) = 'ADMIN'::text));


--
-- Name: referral_client_message_attachments referral_client_message_attachments_agency_own; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_message_attachments_agency_own ON public.referral_client_message_attachments USING (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (EXISTS ( SELECT 1
   FROM ((public.referral_client_messages m
     JOIN public.referral_client_requests q ON ((q.id = m.request_id)))
     JOIN public.referrals r ON ((r.id = q.referral_id)))
  WHERE ((m.id = referral_client_message_attachments.message_id) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid)))))))) WITH CHECK (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (EXISTS ( SELECT 1
   FROM ((public.referral_client_messages m
     JOIN public.referral_client_requests q ON ((q.id = m.request_id)))
     JOIN public.referrals r ON ((r.id = q.referral_id)))
  WHERE ((m.id = referral_client_message_attachments.message_id) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid))))))));


--
-- Name: referral_client_message_attachments referral_client_message_attachments_capability_insert; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_message_attachments_capability_insert ON public.referral_client_message_attachments FOR INSERT WITH CHECK ((EXISTS ( SELECT 1
   FROM public.referral_client_messages m
  WHERE ((m.id = referral_client_message_attachments.message_id) AND ((m.request_id)::text = NULLIF(current_setting('app.client_request_id'::text, true), ''::text))))));


--
-- Name: referral_client_message_attachments referral_client_message_attachments_capability_session; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_message_attachments_capability_session ON public.referral_client_message_attachments FOR SELECT USING ((EXISTS ( SELECT 1
   FROM public.referral_client_messages m
  WHERE ((m.id = referral_client_message_attachments.message_id) AND ((m.request_id)::text = NULLIF(current_setting('app.client_request_id'::text, true), ''::text))))));


--
-- Name: referral_client_message_attachments referral_client_message_attachments_case_manager_read; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_message_attachments_case_manager_read ON public.referral_client_message_attachments FOR SELECT USING (((current_setting('app.user_role'::text, true) = 'CASE_MANAGER'::text) AND (EXISTS ( SELECT 1
   FROM (((public.referral_client_messages m
     JOIN public.referral_client_requests q ON ((q.id = m.request_id)))
     JOIN public.referrals r ON ((r.id = q.referral_id)))
     JOIN public.cases c ON ((c.id = r.case_id)))
  WHERE ((m.id = referral_client_message_attachments.message_id) AND (c.user_id = (current_setting('app.current_user_id'::text, true))::uuid))))));


--
-- Name: referral_client_messages; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.referral_client_messages ENABLE ROW LEVEL SECURITY;

--
-- Name: referral_client_messages referral_client_messages_admin_all; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_messages_admin_all ON public.referral_client_messages USING ((current_setting('app.user_role'::text, true) = 'ADMIN'::text)) WITH CHECK ((current_setting('app.user_role'::text, true) = 'ADMIN'::text));


--
-- Name: referral_client_messages referral_client_messages_agency_own; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_messages_agency_own ON public.referral_client_messages USING (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (EXISTS ( SELECT 1
   FROM (public.referral_client_requests q
     JOIN public.referrals r ON ((r.id = q.referral_id)))
  WHERE ((q.id = referral_client_messages.request_id) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid)))))))) WITH CHECK (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND ((sender_kind)::text = 'AGENCY_USER'::text) AND (user_id = (current_setting('app.current_user_id'::text, true))::uuid) AND (access_link_id IS NULL) AND (EXISTS ( SELECT 1
   FROM (public.referral_client_requests q
     JOIN public.referrals r ON ((r.id = q.referral_id)))
  WHERE ((q.id = referral_client_messages.request_id) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid))))))));


--
-- Name: referral_client_messages referral_client_messages_capability_insert; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_messages_capability_insert ON public.referral_client_messages FOR INSERT WITH CHECK (((request_id)::text = NULLIF(current_setting('app.client_request_id'::text, true), ''::text)));


--
-- Name: referral_client_messages referral_client_messages_capability_session; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_messages_capability_session ON public.referral_client_messages FOR SELECT USING (((request_id)::text = NULLIF(current_setting('app.client_request_id'::text, true), ''::text)));


--
-- Name: referral_client_messages referral_client_messages_case_manager_read; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_messages_case_manager_read ON public.referral_client_messages FOR SELECT USING (((current_setting('app.user_role'::text, true) = 'CASE_MANAGER'::text) AND (EXISTS ( SELECT 1
   FROM ((public.referral_client_requests q
     JOIN public.referrals r ON ((r.id = q.referral_id)))
     JOIN public.cases c ON ((c.id = r.case_id)))
  WHERE ((q.id = referral_client_messages.request_id) AND (c.user_id = (current_setting('app.current_user_id'::text, true))::uuid))))));


--
-- Name: referral_client_request_items; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.referral_client_request_items ENABLE ROW LEVEL SECURITY;

--
-- Name: referral_client_request_items referral_client_request_items_admin_all; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_request_items_admin_all ON public.referral_client_request_items USING ((current_setting('app.user_role'::text, true) = 'ADMIN'::text)) WITH CHECK ((current_setting('app.user_role'::text, true) = 'ADMIN'::text));


--
-- Name: referral_client_request_items referral_client_request_items_agency_own; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_request_items_agency_own ON public.referral_client_request_items USING (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (EXISTS ( SELECT 1
   FROM (public.referral_client_requests q
     JOIN public.referrals r ON ((r.id = q.referral_id)))
  WHERE ((q.id = referral_client_request_items.request_id) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid)))))))) WITH CHECK (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (EXISTS ( SELECT 1
   FROM (public.referral_client_requests q
     JOIN public.referrals r ON ((r.id = q.referral_id)))
  WHERE ((q.id = referral_client_request_items.request_id) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid))))))));


--
-- Name: referral_client_request_items referral_client_request_items_capability_session; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_request_items_capability_session ON public.referral_client_request_items FOR SELECT USING (((request_id)::text = NULLIF(current_setting('app.client_request_id'::text, true), ''::text)));


--
-- Name: referral_client_request_items referral_client_request_items_case_manager_read; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_request_items_case_manager_read ON public.referral_client_request_items FOR SELECT USING (((current_setting('app.user_role'::text, true) = 'CASE_MANAGER'::text) AND (EXISTS ( SELECT 1
   FROM ((public.referral_client_requests q
     JOIN public.referrals r ON ((r.id = q.referral_id)))
     JOIN public.cases c ON ((c.id = r.case_id)))
  WHERE ((q.id = referral_client_request_items.request_id) AND (c.user_id = (current_setting('app.current_user_id'::text, true))::uuid))))));


--
-- Name: referral_client_requests; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.referral_client_requests ENABLE ROW LEVEL SECURITY;

--
-- Name: referral_client_requests referral_client_requests_admin_all; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_requests_admin_all ON public.referral_client_requests USING ((current_setting('app.user_role'::text, true) = 'ADMIN'::text)) WITH CHECK ((current_setting('app.user_role'::text, true) = 'ADMIN'::text));


--
-- Name: referral_client_requests referral_client_requests_agency_own; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_requests_agency_own ON public.referral_client_requests USING (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (EXISTS ( SELECT 1
   FROM public.referrals r
  WHERE ((r.id = referral_client_requests.referral_id) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid)))))))) WITH CHECK (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (EXISTS ( SELECT 1
   FROM public.referrals r
  WHERE ((r.id = referral_client_requests.referral_id) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid))))))));


--
-- Name: referral_client_requests referral_client_requests_capability_session; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_requests_capability_session ON public.referral_client_requests FOR SELECT USING (((id)::text = NULLIF(current_setting('app.client_request_id'::text, true), ''::text)));


--
-- Name: referral_client_requests referral_client_requests_capability_update; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_requests_capability_update ON public.referral_client_requests FOR UPDATE USING (((id)::text = NULLIF(current_setting('app.client_request_id'::text, true), ''::text))) WITH CHECK (((id)::text = NULLIF(current_setting('app.client_request_id'::text, true), ''::text)));


--
-- Name: referral_client_requests referral_client_requests_case_manager_read; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_client_requests_case_manager_read ON public.referral_client_requests FOR SELECT USING (((current_setting('app.user_role'::text, true) = 'CASE_MANAGER'::text) AND (EXISTS ( SELECT 1
   FROM (public.referrals r
     JOIN public.cases c ON ((c.id = r.case_id)))
  WHERE ((r.id = referral_client_requests.referral_id) AND (c.user_id = (current_setting('app.current_user_id'::text, true))::uuid))))));


--
-- Name: referral_comments; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.referral_comments ENABLE ROW LEVEL SECURITY;

--
-- Name: referral_comments referral_comments_agency_referred; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_comments_agency_referred ON public.referral_comments USING (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (EXISTS ( SELECT 1
   FROM public.referrals r
  WHERE ((r.id = referral_comments.refr_id) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid))))))));


--
-- Name: referral_messages; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.referral_messages ENABLE ROW LEVEL SECURITY;

--
-- Name: referral_messages referral_messages_agency_pair; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referral_messages_agency_pair ON public.referral_messages USING (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (EXISTS ( SELECT 1
   FROM public.referrals r
  WHERE ((r.case_id = ( SELECT r2.case_id
           FROM public.referrals r2
          WHERE (r2.id = referral_messages.referral_id))) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid)))))) AND ((EXISTS ( SELECT 1
   FROM public.users us
  WHERE ((us.id = referral_messages.sender_user_id) AND (us.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid)))))) OR (EXISTS ( SELECT 1
   FROM public.referrals r3
  WHERE ((r3.id = referral_messages.referral_id) AND (r3.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid))))))))) WITH CHECK (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (EXISTS ( SELECT 1
   FROM public.referrals r
  WHERE ((r.case_id = ( SELECT r2.case_id
           FROM public.referrals r2
          WHERE (r2.id = referral_messages.referral_id))) AND (r.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid)))))) AND ((EXISTS ( SELECT 1
   FROM public.users us
  WHERE ((us.id = referral_messages.sender_user_id) AND (us.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid)))))) OR (EXISTS ( SELECT 1
   FROM public.referrals r3
  WHERE ((r3.id = referral_messages.referral_id) AND (r3.agcy_id = ( SELECT u.agcy_id
           FROM public.users u
          WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid)))))))));


--
-- Name: referrals; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.referrals ENABLE ROW LEVEL SECURITY;

--
-- Name: referrals referrals_agency_own; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY referrals_agency_own ON public.referrals USING (((current_setting('app.user_role'::text, true) = 'AGENCY'::text) AND (agcy_id = ( SELECT u.agcy_id
   FROM public.users u
  WHERE (u.id = (current_setting('app.current_user_id'::text, true))::uuid)))));


--
-- PostgreSQL database dump complete
--

\unrestrict 0b0pulyjzPiuHbNUF8C3Hgq9i57lRYoRL5ABDrzXneoZ2HoyaxDuEcVuqZbTjBp

--
-- PostgreSQL database dump
--

\restrict FqIhnZIxPB99ZksvL61kROuw6SsdpxYQtkjQkPNN5R0VS6fdQ1HzPX7SJXIBUGo

-- Dumped from database version 18.2
-- Dumped by pg_dump version 18.2

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_framework_tables	1
2	2026_06_01_000001_create_core_reference_tables	1
3	2026_06_01_000002_create_case_tables	1
4	2026_06_01_000003_create_referral_tables	1
5	2026_06_01_000004_create_feedback_tables	1
6	2026_06_01_000006_create_monitoring_tables	1
7	2026_06_01_000007_enable_extensions	1
8	2026_06_01_000008_enable_row_level_security	1
9	2026_06_02_000001_rls_policies_remaining_tables	1
10	2026_06_21_112449_convert_address_codes_to_names	1
11	2026_07_02_074905_drop_case_comments_table	1
12	2026_07_03_000001_drop_type_from_referrals	1
13	2026_07_03_000002_rename_client_middle_name_to_middle_initial	1
14	2026_07_04_000001_create_feedback_invitations_table	1
15	2026_07_04_000001_improve_audit_logs_table	1
16	2026_07_08_000001_drop_philippine_addresses_table	1
17	2026_07_08_000002_drop_escalated_at_from_cases	1
18	2026_07_08_000002_make_audit_logs_trigger_conditional	1
19	2026_07_09_000001_encrypt_pii_fields	1
20	2026_07_10_000001_add_service_id_to_feedback_tables	1
21	2026_07_11_000001_add_onboarding_guide_columns_to_users_table	1
22	2026_07_12_000001_add_category_to_audit_logs	1
23	2026_07_12_000001_add_remark_to_referral_compliance_requirements	1
24	2026_07_12_000001_create_case_events_table	1
25	2026_07_12_000002_create_audit_archive_tables	1
26	2026_07_12_000003_expand_audit_logs_action_check	1
27	2026_07_12_000004_add_chain_seq_to_audit_logs	1
28	2026_07_12_000005_backfill_audit_categories	1
29	2026_07_12_100001_add_performance_indexes	1
30	2026_07_14_000001_create_survey_tables	1
31	2026_07_14_000002_hash_survey_invitation_tokens	1
32	2026_07_17_000001_create_case_category_pivot_table	1
33	2026_07_18_000001_refactor_case_files_requirements	1
34	2026_07_19_000001_add_referral_id_to_case_documents	1
35	2026_07_19_000002_create_referral_client_request_tables	1
36	2026_07_19_000003_harden_case_document_referral_rls	1
37	2026_07_19_000004_enable_referral_client_inbox_rls	1
38	2026_07_20_000001_create_user_invites_table	1
39	2026_07_21_000001_fix_case_manager_rls_read_access	1
40	2026_07_24_000001_add_deletion_reason_to_cases_table	1
41	2026_07_24_000002_add_restore_purge_to_audit_logs_action_check	1
42	2026_07_24_051214_create_generated_documents_table	1
43	2026_07_25_000001_add_source_and_intake_reviewed_by_to_cases	1
44	2026_07_25_000002_add_client_id_to_users	1
45	2026_07_25_000003_make_cases_user_id_nullable	1
46	2026_07_26_120000_create_chatbot_embeddings_table	1
47	2026_07_26_134507_add_fts_to_chatbot_embeddings_table	1
48	2026_07_27_000001_create_case_number_counters_table	1
49	2026_07_27_000002_backfill_address_codes_to_names	1
50	2026_07_27_000003_add_resend_delivery_tracking	1
51	2026_07_28_000001_switch_case_number_counters_to_monthly_periods	1
52	2026_07_28_000002_drop_requirements_from_referrals	1
53	2026_07_28_000003_create_referral_services_table	1
54	2026_07_28_000004_create_referral_service_requirements_table	1
55	2026_08_13_000001_rename_middle_initial_to_middle_name	1
56	2026_08_13_000002_create_referral_client_message_attachments	1
57	2026_09_08_000001_create_referral_message_tables	1
58	2026_09_16_000001_drop_chatbot_embeddings_table	1
59	2026_09_16_000002_drop_legacy_feedback_tables	1
60	2026_09_21_000001_add_entity_label_to_audit_logs	1
61	2026_09_30_000001_add_rejection_reason_to_referrals	1
62	2026_10_02_145604_remove_ip_whitelist_settings	1
63	2026_10_02_154806_add_missing_indexes_to_hot_filter_columns	1
64	2026_10_03_120000_remove_password_policy_settings	1
65	2026_10_03_120001_remove_session_lockout_settings	1
66	2026_10_03_120002_remove_debug_otp_settings	1
67	2026_10_09_000001_hash_user_invite_tokens	1
68	2026_10_09_000002_add_mfa_last_totp_counter_to_users	1
69	2026_10_10_000001_add_capability_session_rls_policies	1
70	2026_10_11_000001_enforce_single_live_client_address	1
71	2026_10_12_000001_add_types_to_clients	1
72	2026_10_12_000002_update_cases_for_review	1
73	2026_10_12_000003_backfill_ofw_account_first	1
74	2026_10_12_000004_drop_intake_columns_from_cases	1
75	2026_10_12_060118_schema_best_practices_hardening	1
76	2026_10_15_000001_seed_canonical_case_statuses	2
77	2026_10_15_045916_restore_clients_type_user_and_ofw_links	2
78	2026_10_15_072238_drop_cases_client_type_column	2
79	2026_10_16_053349_restore_cases_reviewed_by_and_reviewed_at	2
80	2026_10_19_055511_add_resent_at_to_email_logs_table	2
81	2026_10_19_101500_add_files_deleted_at_to_file_tables	2
82	2026_10_22_000001_seed_case_statuses_for_case_type	2
83	2026_10_28_100352_create_activity_events_table	2
84	2026_10_30_150813_add_document_review_to_client_request_attachments	2
85	2026_10_30_151415_create_referral_client_attachment_agencies_table	2
86	2026_10_30_152840_add_source_requirement_to_client_request_items	2
87	2026_10_30_153251_create_client_request_item_service_requirements_table	2
88	2026_10_31_105529_create_helpdesk_article_feedback_table	2
89	2026_10_31_152736_add_client_user_sender_to_referral_client_messages	2
90	2026_11_01_000001_expand_audit_logs_action_check_for_purposeful_verbs	2
91	2026_11_02_000001_add_mfa_policy_to_users	2
92	2026_11_07_120000_add_delivery_state_to_survey_invitations	2
93	2026_11_09_164608_add_case_owned_intake_to_cases_table	2
94	2026_11_10_051000_add_referral_classification_and_category_agencies	2
95	2026_11_10_060000_add_intake_compliance_to_cases	2
96	2026_11_10_070000_drop_legacy_client_case_schema	3
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 96, true);


--
-- PostgreSQL database dump complete
--

\unrestrict FqIhnZIxPB99ZksvL61kROuw6SsdpxYQtkjQkPNN5R0VS6fdQ1HzPX7SJXIBUGo
