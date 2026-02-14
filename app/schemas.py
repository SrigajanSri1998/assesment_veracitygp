from datetime import datetime
from decimal import Decimal

from pydantic import BaseModel, Field

from app.models import OrderStatus


class ProductCreate(BaseModel):
    name: str = Field(min_length=1, max_length=255)
    price: Decimal = Field(ge=0)
    stock_quantity: int = Field(ge=0)


class ProductRead(BaseModel):
    id: int
    name: str
    price: Decimal
    stock_quantity: int

    model_config = {"from_attributes": True}


class OrderItemCreate(BaseModel):
    product_id: int
    quantity: int = Field(gt=0)


class OrderCreate(BaseModel):
    items: list[OrderItemCreate] = Field(min_length=1)


class OrderItemRead(BaseModel):
    id: int
    product_id: int
    quantity_ordered: int
    price_at_order: Decimal

    model_config = {"from_attributes": True}


class OrderRead(BaseModel):
    id: int
    created_at: datetime
    status: OrderStatus
    items: list[OrderItemRead]

    model_config = {"from_attributes": True}


class OrderStatusUpdate(BaseModel):
    status: OrderStatus
