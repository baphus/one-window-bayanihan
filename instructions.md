# Project Instructions: Bayanihan One Window System

## 1. Project Overview
**Project Title:** Bayanihan One Window: An Inter-Agency Referral and Tracking System for Distressed OFWs in Region VII.
**Core Objective:** To transform fragmented manual processes into a unified "One OFW, One Entry" digital platform for inter-agency coordination.

## 2. Technical Stack
- **Frontend:** React with Tailwind CSS and Inertia.js (for seamless integration with Laravel).
- **Backend:** Laravel (Latest Version) using PHP.
- **Database:** PostgreSQL managed via Supabase.
- **Storage:** Cloudinary for document management and media hosting.
- **Deployment:** Render (Hosting) and GitHub (Version Control).

## 3. Core Architectural Rules
- **Primary Keys:** Use **UUID (128-bit)** for all table identifiers to ensure unique tracking across distributed systems.
- **Design Pattern:** Follow the **Model-View-Controller (MVC)** architecture.
- **Database Integrity:** Enforce ACID compliance (PostgreSQL) for all transactions involving case and referral data.
- **Auditability:** Implement an **immutable, append-only audit trail**. Every record must include `is_deleted`, `deleted_at`, and `deleted_by` for soft deletes to maintain a permanent history.
- **Real-Time Capability:** Use WebSocket protocols (Pusher/Redis) for live dashboard updates when referrals are dispatched or updated.

## 4. Security & Access Control
- **Role-Based Access Control (RBAC):** Users are divided into ADMIN, CASE_MANAGER, and AGENCY_FOCAL_PERSON.
- **Lane-Based Restrictions:** Implement strict middleware logic where Agency users can only view cases and referrals specifically assigned to their "lane" (agency ID).
- **Authentication:** Implement secure login with **One-Time Password (OTP)** verification for all administrative users.
- **Data Privacy:** All code must comply with the **Data Privacy Act of 2012**. PII (Personally Identifiable Information) must be encrypted at rest and in transit.

## 5. Module Logic & Requirements
- **Case Intake:** DMW Case Managers create a **Unified Master Case File**. A unique `CASE_NUMBER` and `TRACKER_NUMBER` must be generated upon creation.
- **Referral Management:**
  - DMW can initiate multiple parallel referrals for a single case.
  - Referrals have statuses: PENDING, PROCESSING, COMPLETED, REJECTED, or FOR COMPLIANCE.
  - Agency users must provide a mandatory comment when accepting or rejecting a referral.
- **Milestones:** Agencies record "milestones" for each referral to provide granular progress tracking for the OFW.
- **OFW Tracking:** A public-facing route allows OFWs to enter a tracker number. Access is **view-only** and must hide internal sensitive comments or documents.

## 6. Coding Standards for Copilot
- **Eloquent Models:** Always define `$fillable` arrays and relationships (`belongsTo`, `hasMany`) based on the Data Dictionary.
- **Migrations:** Ensure all foreign keys are clearly defined and use `onDelete('restrict')` for data integrity unless specified otherwise.
- **UI/UX:** Adhere to the "Interia.js" pattern—controllers should return `Inertia::render()` with React components.
- **Analytics:** Use **Chart.js** for dashboard visualizations, focusing on case statistics and agency performance metrics.

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
