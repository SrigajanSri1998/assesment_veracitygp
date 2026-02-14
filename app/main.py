from fastapi import FastAPI

from app.api.orders import router as orders_router
from app.api.products import router as products_router

app = FastAPI(title="Inventory & Order Management Service")
app.include_router(products_router)
app.include_router(orders_router)


@app.get("/health")
def healthcheck() -> dict[str, str]:
    return {"status": "ok"}
