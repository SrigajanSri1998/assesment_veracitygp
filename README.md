# Track A Submission — Vanilla PHP + PDO Task Management API

This repository now contains a **Track A** implementation from the assessment prompt:

- Login endpoint (JWT bearer token)
- Task CRUD endpoints
- Soft deletes (`deleted_at`)
- Filtering (`status`, `q`)
- Pagination (`page`, `limit`)
- Basic browser-based frontend

Implementation lives in: `track_a_php/`

## Assumptions

- Used **Vanilla PHP + PDO** with SQLite by default for zero-friction local setup.
- MySQL can be used by updating `DB_DSN`, `DB_USER`, and `DB_PASS` in `.env`.
- A default user is auto-seeded on first boot from env values.

## Setup

```bash
cd track_a_php
cp .env.example .env
php -S 0.0.0.0:8080 -t public
```

Open:

- App UI: `http://localhost:8080/`
- API base: `http://localhost:8080/api`

Default login (from `.env.example`):

- Email: `admin@example.com`
- Password: `password123`

## Environment Variables

See `.env.example`:

- `DB_DSN`
- `DB_USER`
- `DB_PASS`
- `JWT_SECRET`
- `DEFAULT_USER_EMAIL`
- `DEFAULT_USER_PASSWORD`

## API Endpoints

- `POST /api/login`
- `GET /api/tasks?page=1&limit=10&status=todo&q=search`
- `POST /api/tasks`
- `PUT /api/tasks/{id}`
- `DELETE /api/tasks/{id}` (soft delete)

## Demo Video

A short demo video should be recorded by the submitter and added to the repository/release according to the assessment requirement.
