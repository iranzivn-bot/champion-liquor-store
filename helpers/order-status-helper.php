<?php
declare(strict_types=1);

/**
 * Order & Delivery Status Helpers
 *
 * Consolidated from pages/dashboard/order-view.php and pages/track-order.php.
 * Requires constants.php (loaded via config.php) for ORDER_* and DELIVERY_* constants.
 */

if (!function_exists('orderBadge')) {
    function orderBadge(string $status): string
    {
        return match ($status) {
            ORDER_PENDING,
            DELIVERY_PENDING          => 'bg-secondary',
            ORDER_CONFIRMED           => 'bg-info text-dark',
            ORDER_PROCESSING,
            DELIVERY_PACKED           => 'bg-warning text-dark',
            ORDER_SHIPPED,
            DELIVERY_OUT_FOR_DELIVERY => 'bg-primary',
            ORDER_DELIVERED,
            DELIVERY_DELIVERED        => 'bg-success',
            ORDER_CANCELLED           => 'bg-danger',
            default                   => 'bg-secondary',
        };
    }
}

if (!function_exists('orderLabel')) {
    function orderLabel(string $status): string
    {
        return match ($status) {
            ORDER_PENDING,
            DELIVERY_PENDING          => 'Pending',
            ORDER_CONFIRMED           => 'Confirmed',
            ORDER_PROCESSING          => 'Processing',
            ORDER_SHIPPED             => 'Shipped',
            ORDER_DELIVERED,
            DELIVERY_DELIVERED        => 'Delivered',
            ORDER_CANCELLED           => 'Cancelled',
            DELIVERY_PACKED           => 'Packed',
            DELIVERY_OUT_FOR_DELIVERY => 'Out For Delivery',
            default                   => ucfirst($status),
        };
    }
}

if (!function_exists('orderTimelineIconColor')) {
    function orderTimelineIconColor(string $status): string
    {
        return match ($status) {
            ORDER_PENDING,
            DELIVERY_PENDING          => 'secondary',
            ORDER_CONFIRMED           => 'info',
            ORDER_PROCESSING,
            DELIVERY_PACKED           => 'warning',
            ORDER_SHIPPED,
            DELIVERY_OUT_FOR_DELIVERY => 'primary',
            ORDER_DELIVERED,
            DELIVERY_DELIVERED        => 'success',
            ORDER_CANCELLED           => 'danger',
            default                   => 'secondary',
        };
    }
}

if (!function_exists('orderTimelineIcon')) {
    function orderTimelineIcon(string $status): string
    {
        return match ($status) {
            ORDER_PENDING,
            DELIVERY_PENDING          => 'hourglass',
            ORDER_CONFIRMED           => 'check2-circle',
            ORDER_PROCESSING          => 'arrow-repeat',
            ORDER_SHIPPED             => 'truck',
            ORDER_DELIVERED,
            DELIVERY_DELIVERED        => 'check2',
            ORDER_CANCELLED           => 'x',
            DELIVERY_PACKED           => 'box-seam',
            DELIVERY_OUT_FOR_DELIVERY => 'truck-flatbed',
            default                   => 'circle',
        };
    }
}
