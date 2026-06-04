# Bayanihan One Window

Laravel project for the Bayanihan One Window system. The codebase follows Laravel conventions and uses Eloquent as the primary model and schema workflow.

## Stack
- Laravel (Eloquent-first)
- PostgreSQL (Supabase managed DB)
- Inertia.js + React + Tailwind CSS
- Cloudinary for storage
- Redis for cache/queues
- Pusher (or laravel-websockets) for realtime
- UUID primary keys and audit columns
- RBAC via `users.role` column (CheckRole middleware)

## Quick start
1. Copy `.env.example` to `.env` and fill DB, Supabase, Cloudinary, Pusher, Redis values.
2. Generate app key: `php artisan key:generate`
3. Run migrations: `php artisan migrate`
4. Install frontend: `npm install` then `npm run dev`

## Notes
- Models use UUID primary keys via a shared `UsesUuid` trait.
- Soft delete audit fields (`is_deleted`, `deleted_at`, `deleted_by`) are required on case/referral tables.

If you want the Inertia + React scaffolding installed, ask me to run Breeze setup.
