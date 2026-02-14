# Inventory & Order Management Service

FastAPI + SQLAlchemy backend for products and orders with transactional stock updates.

## Features
- Product management with non-negative stock and price constraints.
- Order creation with multiple items, stock checks, and atomic stock deduction.
- Order status updates with transition validation (`Pending -> Shipped|Cancelled`).
- PostgreSQL schema migrations using Alembic.
- Dockerized app + database via Docker Compose.
- Pytest integration tests for order creation scenarios.

## Run with Docker
```bash
docker-compose up --build
```

API: `http://localhost:8000`

## Run locally
1. Create a virtual environment and install deps:
   ```bash
   python -m venv .venv
   source .venv/bin/activate
   pip install -r requirements.txt
   ```
2. Set database URL if needed:
   ```bash
   export DATABASE_URL=postgresql+psycopg2://postgres:postgres@localhost:5432/inventory
   ```
3. Run migrations:
   ```bash
   alembic upgrade head
   ```
4. Start app:
   ```bash
   uvicorn app.main:app --reload
   ```

## Run tests
```bash
pytest -q
```

## Design Notes / Trade-offs
- `SELECT ... FOR UPDATE` is used during order creation and order status updates to reduce race conditions under concurrent requests.
- `OrderItem.price_at_order` stores a historical price snapshot to prevent later product price changes from altering old orders.
- Tests run on in-memory SQLite for speed; production uses PostgreSQL. The transactional logic is still validated at the API level, but DB-specific locking behavior is strongest in PostgreSQL.
