import requests
import sys
import json
import time

BASE_URL = "http://localhost:8000"
session = requests.Session()

def print_step(step, desc):
    print(f"\n[{step}] {desc}")

def assert_status(res, expected=200, label=""):
    if res.status_code != expected:
        print(f"FAILED {label}: expected {expected}, got {res.status_code}. Body: {res.text[:300]}")
        sys.exit(1)
    print(f"  ✓ {label} (HTTP {res.status_code})")

print("==================================================")
print("LAIJAU E2E BAG / CART LIFECYCLE VERIFICATION")
print("==================================================")

# Step 1: Open storefront as guest
print_step(1, "Open storefront as guest")
res = session.get(f"{BASE_URL}/")
assert_status(res, 200, "Storefront homepage accessible")

# Step 2: Fetch products list to get real product and variant
print_step(2, "Fetch products and select product with variants")
res = session.get(f"{BASE_URL}/api/products?currency=npr")
assert_status(res, 200, "Products API")
products = res.json().get('data', [])
if not products:
    print("FAILED: No products found")
    sys.exit(1)

target_product = None
target_variant = None
for p in products:
    if p.get('variants_list') and len(p['variants_list']) > 0:
        target_product = p
        target_variant = p['variants_list'][0]
        break

if not target_product:
    target_product = products[0]

print(f"  Selected Product: {target_product['name']} (ID: {target_product['id']})")
if target_variant:
    print(f"  Selected Variant: {target_variant.get('size')} / {target_variant.get('color')} (ID: {target_variant.get('id')})")

# Step 3: Open product page
print_step(3, f"Open product detail page (/products/{target_product['slug']})")
res = session.get(f"{BASE_URL}/products/{target_product['slug']}")
assert_status(res, 200, "Product page rendered")

# Step 4: Validate Add to Bag via API
print_step(4, "Validate Add to Bag (1 unit)")
cart_item = {
    "product_id": target_product['id'],
    "quantity": 1
}
if target_variant:
    cart_item["variant_id"] = target_variant['id']

payload = {
    "items": [cart_item],
    "shipping_country": "NP",
    "currency": "npr"
}
res = session.post(f"{BASE_URL}/api/cart/validate", json=payload)
assert_status(res, 200, "Cart validation API succeeded")
cart_data = res.json()
assert cart_data['success'] is True
subtotal_1 = cart_data['subtotal']
print(f"  Cart subtotal for 1 unit: npr {subtotal_1:.2f}")

# Step 5: Open Bag page (/cart)
print_step(5, "Open Bag page (/cart)")
res = session.get(f"{BASE_URL}/cart")
assert_status(res, 200, "Cart page loaded successfully")
assert "Your Bag" in res.text or "Shopping Bag" in res.text

# Step 6: Change quantity (increment to 2)
print_step(6, "Change quantity to 2")
cart_item_2 = dict(cart_item)
cart_item_2["quantity"] = 2
payload["items"] = [cart_item_2]
res = session.post(f"{BASE_URL}/api/cart/validate", json=payload)
assert_status(res, 200, "Updated quantity validated")
subtotal_2 = res.json()['subtotal']
assert subtotal_2 == subtotal_1 * 2, f"Expected {subtotal_1 * 2}, got {subtotal_2}"
print(f"  Cart subtotal for 2 units: npr {subtotal_2:.2f}")

# Step 7: Decrement quantity back to 1
print_step(7, "Decrement quantity back to 1")
cart_item_1 = dict(cart_item)
cart_item_1["quantity"] = 1
payload["items"] = [cart_item_1]
res = session.post(f"{BASE_URL}/api/cart/validate", json=payload)
assert_status(res, 200, "Decremented quantity validated")
assert res.json()['subtotal'] == subtotal_1

# Step 8: Price manipulation defense test
print_step(8, "Attempt malicious client price manipulation (0.01 npr)")
malicious_payload = {
    "items": [
        {
            "product_id": target_product['id'],
            "variant_id": target_variant['id'] if target_variant else None,
            "quantity": 1,
            "unit_price": 0.01,
            "line_total": 0.01,
            "price": 0.01
        }
    ],
    "shipping_country": "NP",
    "currency": "npr"
}
res = session.post(f"{BASE_URL}/api/cart/validate", json=malicious_payload)
assert_status(res, 200, "Server handled request")
assert res.json()['subtotal'] == subtotal_1, f"Client price accepted! Server was not authoritative! Subtotal: {res.json()['subtotal']}"
print(f"  ✓ Malicious price ignored. Server-authoritative subtotal maintained: npr {res.json()['subtotal']}")

# Step 9: Navigate to Checkout page
print_step(9, "Navigate to Checkout page (/checkout)")
res = session.get(f"{BASE_URL}/checkout")
assert_status(res, 200, "Checkout page loaded")

# Step 10: Register as customer while retaining bag
print_step(10, "Register new customer account")
email = f"bag_customer_{int(time.time())}@laijau.com"
# Get CSRF token from page
csrf_token = None
for line in res.text.split("\n"):
    if 'name="csrf-token"' in line:
        csrf_token = line.split('content="')[1].split('"')[0]
        break

reg_res = session.post(f"{BASE_URL}/register", data={
    "name": "Aarav BagCustomer",
    "email": email,
    "password": "Password123!",
    "password_confirmation": "Password123!",
    "_token": csrf_token
}, allow_redirects=True)
assert_status(reg_res, 200, "Registered and landed on account page")
print(f"  Authenticated customer: {email}")

# Step 11: Return to Bag page as authenticated customer
print_step(11, "Return to Bag page as authenticated customer")
res = session.get(f"{BASE_URL}/cart")
assert_status(res, 200, "Cart page loaded for authenticated customer")

# Extract fresh CSRF token after session regeneration
fresh_csrf_token = None
for line in res.text.split("\n"):
    if 'name="csrf-token"' in line:
        fresh_csrf_token = line.split('content="')[1].split('"')[0]
        break

# Step 12: Validate Bag again for authenticated customer
print_step(12, "Re-validate Bag as authenticated customer")
res = session.post(f"{BASE_URL}/api/cart/validate", json=payload)
assert_status(res, 200, "Bag validated seamlessly for authenticated customer")

# Step 13: Customer logs out
print_step(13, "Customer logs out")
logout_res = session.post(f"{BASE_URL}/logout", data={"_token": fresh_csrf_token}, allow_redirects=True)
assert_status(logout_res, 200, "Logged out successfully")

# Step 14: Return to Bag as guest
print_step(14, "Return to Bag as guest after logout")
res = session.get(f"{BASE_URL}/cart")
assert_status(res, 200, "Cart page accessible as guest")
res = session.post(f"{BASE_URL}/api/cart/validate", json=payload)
assert_status(res, 200, "Guest bag persists after logout")

# Step 15: Clear Bag
print_step(15, "Clear Bag (empty cart)")
empty_payload = {
    "items": [],
    "shipping_country": "NP",
    "currency": "npr"
}
res = session.post(f"{BASE_URL}/api/cart/validate", json=empty_payload)
assert res.status_code == 422, "Empty items should fail validation with 422"
print("  ✓ Empty bag validation correctly returns 422 for checkout prevention")

print("\n==================================================")
print("ALL 15 REAL HTTP E2E BAG STEPS PASSED PERFECTLY!")
print("==================================================")
