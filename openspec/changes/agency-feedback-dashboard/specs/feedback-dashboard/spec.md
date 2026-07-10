## ADDED Requirements

### Requirement: Agency feedback dashboard
The system SHALL provide agency users with a feedback dashboard at `/feedbacks` that replaces the previous flat list view. The dashboard SHALL display aggregate metrics scoped to the logged-in user's agency.

#### Scenario: Agency user views dashboard
- **WHEN** an agency user navigates to `/feedbacks`
- **THEN** the system displays a dashboard with: total feedback sent, response rate (%), average overall rating (1-5), average SERVQUAL score (1-5), rating distribution, SERVQUAL dimension scores, per-service breakdown, and recent feedback list

#### Scenario: Agency with no feedback
- **WHEN** an agency user views the dashboard and no feedback exists
- **THEN** the system displays zero-state cards with "No feedback records yet" messaging

### Requirement: Response rate calculation
The system SHALL calculate response rate as: (feedback submitted / invitations sent) × 100. Response rate SHALL be filterable by time window.

#### Scenario: Response rate all-time
- **WHEN** agency views dashboard with "All Time" filter
- **THEN** response rate reflects all invitations and submissions for that agency

#### Scenario: Response rate time window
- **WHEN** agency views dashboard with a specific time window (e.g., "Last 30 days")
- **THEN** response rate reflects only invitations sent and submissions received within that window

### Requirement: Per-service breakdown
The system SHALL show a breakdown of feedback metrics per service. Each service row SHALL display: service name, invitations sent, response rate, and average rating.

#### Scenario: Service with feedback
- **WHEN** a service has received feedback
- **THEN** the breakdown row shows the service name, count of feedbacks, and average rating

#### Scenario: Service with no feedback
- **WHEN** a service has no feedback
- **THEN** the breakdown row shows the service name with zero counts and no rating

### Requirement: Admin cross-agency dashboard
The system SHALL provide admin users with a cross-agency feedback dashboard at `/feedbacks`. The dashboard SHALL show an all-agency summary table and a detailed feedback table with filtering.

#### Scenario: Admin views dashboard
- **WHEN** an admin navigates to `/feedbacks`
- **THEN** the system displays an all-agency summary table (agency name, total sent, response rate, avg rating) and a detailed feedback table below

#### Scenario: Admin filters by agency
- **WHEN** admin selects a specific agency from the filter dropdown
- **THEN** the detailed feedback table shows only feedback for that agency

#### Scenario: Admin filters by service
- **WHEN** admin selects a specific service from the filter dropdown
- **THEN** the detailed feedback table shows only feedback for that service

#### Scenario: Admin filters by date range
- **WHEN** admin sets a date range filter
- **THEN** the detailed feedback table shows only feedback within that date range

#### Scenario: Admin filters by rating
- **WHEN** admin selects a minimum rating filter
- **THEN** the detailed feedback table shows only feedback with that rating or higher

### Requirement: Feedback list table
The detailed feedback table SHALL display: date, client name, agency name, service name, overall rating (star display), SERVQUAL average, and a link to the detail page.

#### Scenario: Feedback row display
- **WHEN** feedback data is available
- **THEN** each row shows all columns with proper formatting (dates as display format, stars for ratings, link to `/feedbacks/{id}`)

### Requirement: Time window filter
The dashboard SHALL support filtering by: All Time, Last 7 days, Last 30 days, Last 90 days, Last Quarter, Last Year.

#### Scenario: Filter selection
- **WHEN** user selects a time window filter
- **THEN** all dashboard metrics recalculate to reflect only data within that window

#### Scenario: Default filter
- **WHEN** user first loads the dashboard
- **THEN** the default filter is "All Time"
