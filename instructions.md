# System Requirements: Bayanihan One Window

## 1. Project Context & Purpose
The Bayanihan One Window is a web-based inter-agency referral and tracking system designed to streamline assistance for distressed Overseas Filipino Workers (OFWs) in Region VII. It replaces fragmented manual processes with a "One OFW, One Entry" digital model, using a Unified Master Case File accessible to multiple government agencies.

## 2. Technical Stack & Architecture
- Architecture: Modular Model-View-Controller (MVC).
- Frontend: React with Tailwind CSS, using Inertia.js for server-side routing.
- Backend: PHP Laravel (latest version).
- Database: PostgreSQL (hosted via Supabase) for ACID-compliant transactions.
- Storage: Cloudinary for secure document management and CDN delivery.
- Deployment: Render (cloud hosting).
- Communication: Pusher/Redis for real-time dashboard updates.

## 3. Core Functional Modules
### A. Case Intake & Profiling
- Single-entry intake: DMW Case Managers create a centralized record containing personal details, employment history, and vulnerability indicators.
- Tracking IDs: the system must auto-generate a unique Case Number and Tracker Number for every new entry.

### B. Referral Management
- Parallel referrals: Case Managers can dispatch multiple referrals to different agencies simultaneously.
- Lane-based logic: Agency users can only view and process referrals assigned to their agency lane.
- Status workflow: PENDING, PROCESSING, COMPLETED, REJECTED, or FOR COMPLIANCE.

### C. Case Tracking & Monitoring
- Unified timeline: real-time view of all agency actions, milestones, and status updates linked to a single case.
- Public portal: OFWs can track using their Tracker Number, protected by OTP.

### D. Support Features
- AI chatbot: automated guidance on service requirements and case status inquiries.
- Reporting & analytics: charts and graphs for case trends and agency performance.

## 4. Database & Data Integrity Rules
- Primary keys: all tables must use UUIDs (128-bit).
- Soft deletes: every table must include `is_deleted` and `deleted_at`.
- Audit logging: append-only AUDIT_LOG table must record CREATE, UPDATE, DELETE, and LOGIN actions with user ID, module, and timestamps.

## 5. Security & User Roles
### User Roles (RBAC)
1. System Administrator: manages users, agencies, and global configurations.
2. DMW Case Manager: primary coordinator (intake, referral, case closure).
3. Agency Focal Person: manages interventions and milestones for assigned lane.
4. OFW Tracking User: view-only access to their own case status.

### Security Protocols
- Authentication: OTP required for administrative logins and OFW tracking portal.
- Data privacy: encrypt PII at rest and in transit; comply with the Data Privacy Act of 2012.
- Network security: SSL/TLS, WAF, and IP whitelisting for agency backends.

## 6. Developer Guidelines for AI
- Eloquent relationships: define relationships in models (Case hasMany Referral, Referral hasMany Milestone).
- Validation: enforce strict backend validation for all required fields in the Data Dictionary.
- Closure logic: a case cannot be closed unless all associated referrals are in terminal states (COMPLETED or REJECTED).

## 7. Additional Technical Standards
- Eloquent models: always define `$fillable` arrays and relationships (`belongsTo`, `hasMany`).
- Migrations: define foreign keys and use `onDelete('restrict')` unless specified otherwise.
- UI/UX: controllers return `Inertia::render()` with React components.
- Analytics: use Chart.js for dashboard visualizations.

---

### How to use this with GitHub Copilot
1. Save the above as `instructions.md` in your project root.
2. When asking Copilot to generate code, use the `#` symbol to reference the file:
   - Example: "@workspace /explain based on #instructions.md how I should structure the Referral migration and its lane-based middleware."
   - Example: "Generate a Laravel Controller for the Case Management module following the RBAC and UUID rules in #instructions.md."

---

# Laravel Refactoring & Code Quality Standards
## GitHub Copilot Custom Instructions for Laravel Projects

# Core Philosophy

Always generate Laravel code that is:
- Clean
- Readable
- Testable
- Maintainable
- Scalable
- RESTful
- Convention-driven

Follow Laravel best practices and framework conventions whenever possible.

Prefer:
- Simplicity
- Separation of concerns
- SOLID principles
- Reusability
- Clean architecture
- Explicit naming

Never prioritize shortcuts over maintainability.

---

# Laravel Project Structure Rules

## Controllers
Controllers must remain thin.

Controllers should:
- Accept requests
- Validate requests
- Delegate business logic to services/actions
- Return responses/resources

Controllers should NOT:
- Contain heavy business logic
- Perform complex database operations
- Handle formatting logic
- Become God classes

### Bad
```php
public function store(Request $request)
{
    // validation
    // database logic
    // calculations
    // email sending
    // report generation
}
```

### Good
```php
public function store(StoreOrderRequest $request)
{
    $order = $this->orderService->create($request->validated());

    return new OrderResource($order);
}
```

---

# Validation Standards

Always use:
- Form Request classes

Avoid inline validation inside controllers unless extremely simple.

---

# Business Logic Rules

## Use Services or Actions
Business logic belongs in:
- Service classes
- Action classes
- Domain classes

NOT in:
- Controllers
- Models
- Routes
- Blade files

---

# Eloquent Standards

## Keep Models Focused
Models should:
- Define relationships
- Define scopes
- Handle simple domain behavior

Models should NOT:
- Contain large workflows
- Perform external API logic
- Become service containers

---

# Query Standards

## Avoid N+1 Problems
Always eager load relationships when needed.

---

# Database Standards

## Prefer Query Scopes
Use local scopes for reusable filtering logic.

---

# Refactoring Rules

## Extract Method
If a method becomes too long:
- Extract private methods
- Extract services
- Extract actions

---

# Code Smells to Avoid
- Fat Controllers
- Fat Models
- Duplicated Queries
- Massive Blade Templates

---

# Routing Standards

## Use Resource Controllers
Prefer:
```php
Route::resource('users', UserController::class);
```

---

# API Standards

## Use API Resources
Never return raw models directly for APIs.

---

# Dependency Injection Rules
Always prefer constructor injection.

---

# Configuration Rules
Never hardcode secrets. Use `.env` and `config()`.

---

# Testing Standards
Every important business flow should be testable.

---

# Queue & Job Standards
Heavy tasks should use queues.

---

# Event-Driven Design
Use events/listeners/notifications for decoupled workflows.

---

# Error Handling Standards
Use exceptions and custom exception classes. Avoid silent failures.

---

# Security Standards
Always validate input and authorize actions. Prevent mass assignment.

---

# Performance Standards
Use pagination, chunking, or lazy collections for large datasets.

---

# Laravel Naming Standards
Controllers: `UserController`
Services: `UserService`
Requests: `StoreUserRequest`
Jobs: `SendInvoiceJob`
Events: `UserRegistered`

---

# Architecture Guidelines
Controller → Service/Action → Repository/Model → Database

---

# Recommended Laravel Features
Use: Form Requests, API Resources, Policies, Events/Listeners, Queues, Jobs, Middleware, DTOs, Eloquent Scopes.

---

# Clean Code Checklist
- Controller is thin
- Business logic is extracted
- Validation is separated
- Queries are optimized
- Duplication minimized
- Naming is meaningful
- Code is testable
- Responsibilities separated
- Laravel convention followed
- Solution is scalable
