from collections import Counter

from fastapi import HTTPException, status
from sqlalchemy import select
from sqlalchemy.orm import Session, selectinload

from app.models import Order, OrderItem, OrderStatus, Product
from app.schemas import OrderCreate


ALLOWED_TRANSITIONS: dict[OrderStatus, set[OrderStatus]] = {
    OrderStatus.PENDING: {OrderStatus.SHIPPED, OrderStatus.CANCELLED},
    OrderStatus.SHIPPED: set(),
    OrderStatus.CANCELLED: set(),
}


def create_order(db: Session, payload: OrderCreate) -> Order:
    quantities = Counter()
    for item in payload.items:
        quantities[item.product_id] += item.quantity

    product_ids = list(quantities.keys())

    with db.begin():
        stmt = select(Product).where(Product.id.in_(product_ids)).with_for_update()
        products = db.execute(stmt).scalars().all()
        product_map = {product.id: product for product in products}

        missing_ids = [product_id for product_id in product_ids if product_id not in product_map]
        if missing_ids:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Products not found: {missing_ids}",
            )

        for product_id, qty in quantities.items():
            product = product_map[product_id]
            if product.stock_quantity < qty:
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail=f"Insufficient stock for product {product_id}",
                )

        order = Order(status=OrderStatus.PENDING)
        db.add(order)
        db.flush()

        for product_id, qty in quantities.items():
            product = product_map[product_id]
            product.stock_quantity -= qty
            order_item = OrderItem(
                order_id=order.id,
                product_id=product.id,
                quantity_ordered=qty,
                price_at_order=product.price,
            )
            db.add(order_item)

    return get_order_by_id(db, order.id)


def get_order_by_id(db: Session, order_id: int) -> Order:
    stmt = select(Order).where(Order.id == order_id).options(selectinload(Order.items))
    order = db.execute(stmt).scalars().first()
    if not order:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Order not found")
    return order


def update_order_status(db: Session, order_id: int, new_status: OrderStatus) -> Order:
    with db.begin():
        stmt = select(Order).where(Order.id == order_id).with_for_update()
        order = db.execute(stmt).scalars().first()
        if not order:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Order not found")

        if new_status == order.status:
            return order

        allowed = ALLOWED_TRANSITIONS.get(order.status, set())
        if new_status not in allowed:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"Invalid status transition from {order.status.value} to {new_status.value}",
            )

        order.status = new_status

    return get_order_by_id(db, order_id)
