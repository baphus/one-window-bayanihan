## 1. Database Migration

- [x] 1.1 Create migration: add `service_id` (UUID, nullable FK → services), `name` (string) to `servqual_configs`; add `service_id` (UUID, nullable FK → services) to `feedback_invitations` and `feedback`
- [x] 1.2 Add unique partial index on `servqual_configs(agency_id, service_id)` where service_id IS NOT NULL
- [x] 1.3 Backfill `service_id` on `servqual_configs` by matching `service_name` to `services.name` where `agcy_id` matches
- [x] 1.4 Backfill `service_id` on `feedback_invitations` and `feedback` similarly
- [x] 1.5 Make `service_id` non-nullable on `feedback_invitations` and `feedback` (records without a match get NULL service_id, service_name preserved)

## 2. Backend: ServqualConfig Model & CRUD

- [x] 2.1 Update `ServqualConfig` model: add `service_id`, `name` to fillable; add `service()` belongsTo relationship; add unique validation for agency+service combo
- [x] 2.2 Update `AgencyServqualConfigController`: add service assignment to create/edit flows; add service dropdown data (agency's services list); enforce one-default-per-agency constraint
- [x] 2.3 Update `ServqualConfigStoreRequest` and `ServqualConfigUpdateRequest`: add `name` required field, `service_id` nullable with exists validation
- [x] 2.4 Add controller method to unassign service (set service_id = NULL, or delete if default already exists)
- [x] 2.5 Update seeders to include `name` field and `service_id` for existing configs

## 3. Backend: Form Resolution Logic

- [x] 3.1 Update `FeedbackInvitationService::createInvitation()` to resolve form: look up service-specific override first, then agency default; snapshot questions into form_snapshot
- [x] 3.2 Handle edge case: no active form found → log warning, create invitation without form_snapshot
- [x] 3.3 Update `PublicFeedbackController::showForm()` to handle missing form_snapshot gracefully (show "No form configured" message)

## 4. Backend: Agency Dashboard

- [x] 4.1 Create `FeedbackController::dashboard()` method: aggregate queries for total sent, response rate, avg rating, avg SERVQUAL, rating distribution, per-service breakdown, recent feedback
- [x] 4.2 Add time window filtering (All Time, Last 7/30/90 days, Last Quarter, Last Year) via query parameter
- [x] 4.3 Return data as Inertia props for `Feedback/Dashboard.jsx`
- [x] 4.4 Remove old `FeedbackController::index()` and its route
- [x] 4.5 Add dashboard route: `GET /feedbacks` → `FeedbackController::dashboard`

## 5. Backend: Admin Cross-Agency Dashboard

- [x] 5.1 Create `AdminFeedbackController::dashboard()` method: all-agency summary + detailed feedback table
- [x] 5.2 Add filtering by agency_id, service_id, date_from/date_to, min_rating
- [x] 5.3 Return paginated feedback table with agency/service/client joins
- [x] 5.4 Add admin route: `GET /admin/feedbacks` → `AdminFeedbackController::dashboard`

## 6. Frontend: Agency Dashboard

- [x] 6.1 Create `resources/js/Pages/Feedback/Dashboard.jsx`: overview cards (total, response rate, avg rating, avg SERVQUAL), rating distribution chart, SERVQUAL dimension scores, per-service breakdown table, recent feedback list
- [x] 6.2 Add time window filter dropdown (All Time, Last 7/30/90 days, Last Quarter, Last Year)
- [x] 6.3 Handle zero-state (no feedback records)
- [x] 6.4 Link individual feedback items to `Feedback/Show.jsx`

## 7. Frontend: Admin Dashboard

- [x] 7.1 Create `resources/js/Pages/Feedback/AdminDashboard.jsx`: all-agency summary table, detailed feedback table with columns (date, client, agency, service, rating, SERVQUAL, link)
- [x] 7.2 Add filter controls: agency dropdown, service dropdown, date range picker, rating filter
- [x] 7.3 Wire filters to query params and re-fetch data

## 8. Frontend: Form Management UI

- [x] 8.1 Update `Feedback/ServqualConfig/Index.jsx`: show default form vs. service overrides; label each with name and assigned service; add "Assign to Service" action
- [x] 8.2 Update `Feedback/ServqualConfig/Form.jsx`: add name field input; add service assignment dropdown (All Services default / specific service)
- [x] 8.3 Add unassign/remove service action (with confirmation)

## 9. Frontend: Cleanup

- [x] 9.1 Remove `Feedback/Index.jsx` (replaced by Dashboard)
- [x] 9.2 Update any navigation links pointing to `feedbacks.index` route

## 10. Tests

- [x] 10.1 Update `FeedbackServiceTest`: test form resolution logic (override vs default vs no form)
- [x] 10.2 Update `FeedbackInvitationTest`: test invitation creation with service_id FK
- [x] 10.3 Update `FeedbackControllerTest`: test dashboard endpoint, test admin dashboard, test time window filtering
- [x] 10.4 Add test: agency cannot create two default forms
- [x] 10.5 Add test: service-specific override takes precedence over default
- [x] 10.6 Run `php artisan test` and `./vendor/bin/pint --test` to verify

## 11. Final Verification

- [x] 11.1 Run `npm run build` to verify Vite build succeeds
- [x] 11.2 Run full test suite: `php artisan test`
- [x] 11.3 Run `./vendor/bin/pint` to fix code style
- [ ] 11.4 Manual smoke test: create form, assign to service, complete referral, verify invitation uses correct form, verify dashboard shows feedback
