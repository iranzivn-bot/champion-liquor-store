<?php
/**
 * Checkout API
 *
 * RESTful API endpoint for order checkout operations.
 *
 * This file handles API requests using different HTTP methods:
 *   POST   — Submit a new order
 *   GET    — Retrieve checkout information (shipping, payment methods)
 *
 * Expected response format (JSON):
 *   { "success": true, "data": { "order_id": 1, "total": 150.00 } }
 *   or
 *   { "success": false, "error": "Error message" }
 *
 * IMPORTANT: This is a STRUCTURE-ONLY file.
 * The full checkout API will be implemented in a future iteration.
 *
 * PHP 8.3
 */

// TODO: Implement checkout API
// Steps:
// 1. Set Content-Type: application/json
// 2. Check HTTP method (GET / POST)
// 3. Validate authentication (checkout requires logged-in user)
// 4. Validate cart has items
// 5. Process payment (integration with payment gateway)
// 6. Create order in database
// 7. Clear the cart
// 8. Send order confirmation email
// 9. Return JSON response with order details

header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'error'   => 'Checkout API is not yet implemented. Please use the checkout page.',
]);
exit;
