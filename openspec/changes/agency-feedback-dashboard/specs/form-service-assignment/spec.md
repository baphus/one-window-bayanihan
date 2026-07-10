## ADDED Requirements

### Requirement: ServvalConfig service assignment
Each SERVQUAL config SHALL be assignable to a specific service via a `service_id` foreign key, or serve as the agency default when `service_id` is NULL.

#### Scenario: Create default form
- **WHEN** an agency creates a SERVQUAL config with service_id = NULL
- **THEN** it becomes the agency's default form for all services without a specific override

#### Scenario: Create service-specific form
- **WHEN** an agency creates a SERVQUAL config with a specific service_id
- **THEN** it overrides the default form for that service

#### Scenario: Only one default per agency
- **WHEN** an agency creates a second SERVQUAL config with service_id = NULL
- **THEN** the system prevents creation and returns an error "Agency already has a default form"

### Requirement: Form resolution on invitation creation
When creating a feedback invitation for a referral, the system SHALL resolve the correct SERVQUAL config by: (1) looking for a service-specific override, (2) falling back to the agency default.

#### Scenario: Service-specific override exists
- **WHEN** a referral is completed for a service that has a specific SERVQUAL config override
- **THEN** the invitation uses the override's questions as the form_snapshot

#### Scenario: No override, use default
- **WHEN** a referral is completed for a service that has no specific override
- **THEN** the invitation uses the agency default form's questions as the form_snapshot

#### Scenario: No form configured
- **WHEN** a referral is completed but the agency has no active SERVQUAL config (neither override nor default)
- **THEN** the system logs a warning and creates the invitation without a form_snapshot (client sees "No form configured" message)

### Requirement: Form management UI
The SERVQUAL config management page SHALL clearly show which form is the default and which forms are assigned to specific services.

#### Scenario: View form list
- **WHEN** agency navigates to the SERVQUAL config management page
- **THEN** the page shows the default form (labeled "Default Form — All Services") and any service-specific overrides (labeled with the service name)

#### Scenario: Assign form to service
- **WHEN** agency edits a SERVQUAL config and selects a service from the assignment dropdown
- **THEN** the config is linked to that service and will be used as the override for that service

#### Scenario: Unassign form from service
- **WHEN** agency removes the service assignment from a SERVQUAL config
- **THEN** the config becomes the default form (service_id = NULL) if no default exists, or is deleted if a default already exists

#### Scenario: Assign default form
- **WHEN** agency selects "All Services (Default)" in the assignment dropdown
- **THEN** the config becomes the agency default (service_id = NULL)

### Requirement: ServqualConfig name field
Each SERVQUAL config SHALL have a `name` field for human-readable labeling (e.g., "Legal Aid Client Feedback").

#### Scenario: Create form with name
- **WHEN** agency creates a SERVQUAL config with a name
- **THEN** the name is stored and displayed in the form list

#### Scenario: Form name required
- **WHEN** agency creates a SERVQUAL config without a name
- **THEN** the system returns a validation error requiring the name field

### Requirement: Service ID FK on feedback tables
The `feedback_invitations` and `feedback` tables SHALL have a `service_id` foreign key linking to the services table. The `service_name` field SHALL be kept as a denormalized display field.

#### Scenario: Invitation created with service_id
- **WHEN** a feedback invitation is created
- **THEN** both `service_id` and `service_name` are populated from the referral's service

#### Scenario: Feedback created with service_id
- **WHEN** feedback is submitted from an invitation
- **THEN** the `service_id` and `service_name` are copied from the invitation to the feedback record

### Requirement: Unique constraint on service assignment
The system SHALL enforce that only one SERVQUAL config exists per agency per service (including the default where service_id IS NULL).

#### Scenario: Duplicate service assignment blocked
- **WHEN** agency tries to create a second SERVQUAL config for the same service
- **THEN** the system returns a validation error "A form is already assigned to this service"

#### Scenario: Switching service assignment
- **WHEN** agency reassigns a config from one service to another
- **THEN** the old service reverts to using the default form, and the new service uses the reassigned config
