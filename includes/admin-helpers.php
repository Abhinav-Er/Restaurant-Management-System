<?php
// Small helpers shared by admin/orders.php and admin/kitchen.php

$status_labels = [
    'placed' => 'Placed', 'paid' => 'Paid', 'preparing' => 'Preparing',
    'ready' => 'Ready', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
];

// The next step for each status: [new status, button label]
$next_step = [
    'paid'      => ['preparing', 'Start preparing'],
    'preparing' => ['ready',     'Mark ready'],
    'ready'     => ['delivered', 'Mark delivered'],
];

// A small form with one button that sends the order to the status action
function status_form(int $order_id, string $status, string $label, string $return, string $class = 'btn-sm', string $confirm = ''): string {
    $onsubmit = $confirm !== '' ? ' onsubmit="return confirm(\'' . htmlspecialchars($confirm, ENT_QUOTES) . '\');"' : '';
    return '<form method="post" action="../action/admin/order-status-action.php" style="display:inline"' . $onsubmit . '>'
         . '<input type="hidden" name="order_id" value="' . $order_id . '">'
         . '<input type="hidden" name="status" value="' . htmlspecialchars($status) . '">'
         . '<input type="hidden" name="return" value="' . htmlspecialchars($return) . '">'
         . '<button type="submit" class="' . $class . '">' . htmlspecialchars($label) . '</button></form>';
}

function time_ago(int $minutes): string {
    if ($minutes < 1)  return 'just now';
    if ($minutes < 60) return $minutes . ' min ago';
    return floor($minutes / 60) . ' h ' . ($minutes % 60) . ' min ago';
}

// Messages the status action can send back
$admin_success = ['updated' => 'Order updated. The customer has been notified.', 'cancelled' => 'Order cancelled and stock returned.'];
$admin_errors  = [
    'invalid' => 'That change is not possible. The order may already have been updated by someone else.',
    'denied'  => 'Your role is not allowed to do that.',
    'early'   => 'This is a scheduled order. It is too early to start preparing it.',
    'failed'  => 'Something went wrong. Please try again.',
];