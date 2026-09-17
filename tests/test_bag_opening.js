import assert from 'assert';

// Mock a minimal Alpine environment for store testing
let registeredStore = null;
const mockAlpine = {
    store(name, obj) {
        if (obj) {
            registeredStore = obj;
        }
        return registeredStore;
    },
    effect(fn) {
        // Mock effect execution
    }
};

// Mock localStorage
const storage = {};
global.localStorage = {
    getItem(k) { return storage[k] || null; },
    setItem(k, v) { storage[k] = String(v); },
    removeItem(k) { delete storage[k]; }
};

// Mock window and document
const listeners = {};
global.window = {
    addEventListener(event, fn) {
        listeners[event] = listeners[event] || [];
        listeners[event].push(fn);
    },
    dispatchEvent(event) {
        if (listeners[event.type]) {
            listeners[event.type].forEach(fn => fn(event));
        }
    }
};

global.document = {
    body: {
        classList: {
            classes: new Set(),
            add(c) { this.classes.add(c); },
            remove(c) { this.classes.delete(c); },
            contains(c) { return this.classes.has(c); }
        }
    },
    addEventListener(event, fn) {
        listeners[event] = listeners[event] || [];
        listeners[event].push(fn);
    }
};

global.CustomEvent = class {
    constructor(type) { this.type = type; }
};

// Import store
import { initStore } from '../resources/js/store.js';

console.log('--- Testing Laijau Bag Opening Store Logic ---');

// 1. Initialize store
initStore(mockAlpine);
const store = registeredStore;
assert.ok(store, 'Store must be registered');
store.init();

// 2. Initial state: drawer must be closed
assert.strictEqual(store.isCartDrawerOpen, false, 'Cart drawer must be initially closed');
console.log('✓ Initial state: isCartDrawerOpen is false');

// 3. Open cart drawer via openCartDrawer()
store.openCartDrawer();
assert.strictEqual(store.isCartDrawerOpen, true, 'openCartDrawer() must set isCartDrawerOpen to true');
console.log('✓ openCartDrawer(): isCartDrawerOpen is true');

// 4. Close cart drawer via closeCartDrawer()
store.closeCartDrawer();
assert.strictEqual(store.isCartDrawerOpen, false, 'closeCartDrawer() must set isCartDrawerOpen to false');
console.log('✓ closeCartDrawer(): isCartDrawerOpen is false');

// 5. Toggle cart drawer via toggleCartDrawer()
store.toggleCartDrawer();
assert.strictEqual(store.isCartDrawerOpen, true, 'toggleCartDrawer() from closed must open');
store.toggleCartDrawer();
assert.strictEqual(store.isCartDrawerOpen, false, 'toggleCartDrawer() from open must close');
console.log('✓ toggleCartDrawer(): toggles open/close correctly');

// 6. Test opening via global window event 'open-cart-drawer'
window.dispatchEvent(new CustomEvent('open-cart-drawer'));
assert.strictEqual(store.isCartDrawerOpen, true, 'open-cart-drawer custom event must open drawer');
console.log('✓ CustomEvent("open-cart-drawer"): opens drawer');

// 7. Test closing via global window event 'close-cart-drawer'
window.dispatchEvent(new CustomEvent('close-cart-drawer'));
assert.strictEqual(store.isCartDrawerOpen, false, 'close-cart-drawer custom event must close drawer');
console.log('✓ CustomEvent("close-cart-drawer"): closes drawer');

// 8. Test Escape key listener
store.openCartDrawer();
assert.strictEqual(store.isCartDrawerOpen, true);
if (listeners['keydown']) {
    listeners['keydown'].forEach(fn => fn({ key: 'Escape' }));
}
assert.strictEqual(store.isCartDrawerOpen, false, 'Escape key must close cart drawer');
console.log('✓ Escape keydown: closes drawer');

// 9. Test delegated click on [data-open-cart]
let prevented = false;
const mockEvent = {
    preventDefault() { prevented = true; },
    target: {
        closest(sel) {
            if (sel === '[data-open-cart]') return { id: 'header-cart-button' };
            return null;
        }
    }
};
if (listeners['click']) {
    listeners['click'].forEach(fn => fn(mockEvent));
}
assert.strictEqual(store.isCartDrawerOpen, true, 'Clicking element with [data-open-cart] must open drawer');
assert.strictEqual(prevented, true, 'Click event default must be prevented');
console.log('✓ Delegated click on [data-open-cart]: opens drawer and prevents default');

// 10. Test adding an item opens the drawer
store.closeCartDrawer();
assert.strictEqual(store.isCartDrawerOpen, false);
store.addToCart({
    product_id: 1,
    name: 'Leather Oxford Shoes',
    price_npr: 1899,
    quantity: 1
});
assert.strictEqual(store.isCartDrawerOpen, true, 'addToCart() must automatically open the drawer');
assert.strictEqual(store.cart.length, 1, 'Item must be in cart');
console.log('✓ addToCart(): adds item and automatically opens drawer');

console.log('\n--- ALL BAG OPENING UNIT CHECKS PASSED PERFECTLY ---');
