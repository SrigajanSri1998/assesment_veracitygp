from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from app.db.session import get_db
from app.schemas import OrderCreate, OrderRead, OrderStatusUpdate
from app.services.order_service import create_order, get_order_by_id, update_order_status

router = APIRouter(prefix="/orders", tags=["orders"])


@router.post("", response_model=OrderRead, status_code=201)
def create_order_endpoint(payload: OrderCreate, db: Session = Depends(get_db)):
    return create_order(db, payload)


@router.get("/{order_id}", response_model=OrderRead)
def get_order_endpoint(order_id: int, db: Session = Depends(get_db)):
    return get_order_by_id(db, order_id)


@router.patch("/{order_id}/status", response_model=OrderRead)
def update_order_status_endpoint(order_id: int, payload: OrderStatusUpdate, db: Session = Depends(get_db)):
    return update_order_status(db, order_id, payload.status)
