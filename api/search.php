<?php
/**
 * Search API
 *
 * RESTful API endpoint for product search.
 *
 * This file handles API requests:
 *   GET /api/search.php?q=query&category=slug&page=1
 *
 * Expected response format (JSON):
 *   {
 *     "success": true,
 *     "data": {
 *       "products": [ ... ],
 *       "total": 25,
 *       "page": 1,
 *       "per_page": 12
 *     }
 *   }
 *   or
 *   { "success": false, "error": "Error message" }
 *
 * IMPORTANT: This is a STRUCTURE-ONLY file.
 * The full search API will be implemented in a future iteration.
 *
 * PHP 8.3
 */

// TODO: Implement search API
// Steps:
// 1. Set Content-Type: application/json
// 2. Get query parameters (q, category, page)
// 3. Sanitize inputs
// 4. Search products table with LIKE query
// 5. Apply category filter if provided
// 6. Paginate results
// 7. Return JSON response

header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'error'   => 'Search API is not yet implemented. Please use the search page.',
    'data'    => [
        'products' => [],
        'total'    => 0,
        'page'     => 1,
        'per_page' => 12,
    ],
]);
exit;
