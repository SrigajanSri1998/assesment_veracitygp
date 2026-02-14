def test_create_order_success_reduces_stock(client):
    product_resp = client.post(
        "/products",
        json={"name": "Widget", "price": "10.00", "stock_quantity": 5},
    )
    assert product_resp.status_code == 201
    product = product_resp.json()

    order_resp = client.post(
        "/orders",
        json={"items": [{"product_id": product['id'], "quantity": 3}]},
    )

    assert order_resp.status_code == 201
    payload = order_resp.json()
    assert payload["status"] == "Pending"
    assert payload["items"][0]["quantity_ordered"] == 3
    assert payload["items"][0]["price_at_order"] == "10.00"

    products_resp = client.get("/products")
    assert products_resp.status_code == 200
    assert products_resp.json()[0]["stock_quantity"] == 2


def test_create_order_insufficient_stock_returns_400(client):
    product_resp = client.post(
        "/products",
        json={"name": "Limited", "price": "20.00", "stock_quantity": 1},
    )
    product = product_resp.json()

    order_resp = client.post(
        "/orders",
        json={"items": [{"product_id": product['id'], "quantity": 2}]},
    )

    assert order_resp.status_code == 400
    assert "Insufficient stock" in order_resp.json()["detail"]


def test_cannot_ship_cancelled_order(client):
    product_resp = client.post(
        "/products",
        json={"name": "Cancelable", "price": "8.50", "stock_quantity": 10},
    )
    product = product_resp.json()

    order_resp = client.post(
        "/orders",
        json={"items": [{"product_id": product['id'], "quantity": 1}]},
    )
    order_id = order_resp.json()["id"]

    cancel_resp = client.patch(f"/orders/{order_id}/status", json={"status": "Cancelled"})
    assert cancel_resp.status_code == 200

    ship_resp = client.patch(f"/orders/{order_id}/status", json={"status": "Shipped"})
    assert ship_resp.status_code == 400
    assert "Invalid status transition" in ship_resp.json()["detail"]
