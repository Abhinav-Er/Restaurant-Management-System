<?php
// Draws a printable bill. Used by public/bill.php (customer) and admin/bill.php (staff).
// The page that includes it must set:
//   $pdo, $order_id, $only_customer_id (a customer id, or null for staff), $css_href, $back_href

$sql = 'SELECT o.order_id, o.order_date, o.scheduled_time, o.order_type, o.table_no,
               o.contact_name, o.contact_phone, o.discount_amount, o.total_amount,
               p.payment_method, p.payment_status, p.transaction_ref, p.paid_at,
               cp.code AS coupon_code
        FROM orders o
        LEFT JOIN payment p ON p.order_id = o.order_id
        LEFT JOIN coupon cp ON cp.coupon_id = o.coupon_id
        WHERE o.order_id = ?';
$params = [$order_id];
if ($only_customer_id !== null) {
    $sql .= ' AND o.customer_id = ?';
    $params[] = $only_customer_id;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bill = $stmt->fetch();

if (!$bill) {
    header('Location: ' . $back_href);
    exit;
}

// A bill exists only once payment is confirmed
$has_bill = in_array($bill['payment_status'], ['success', 'refunded'], true);
$items = [];
if ($has_bill) {
    $it = $pdo->prepare(
        'SELECT m.name, oi.quantity, oi.unit_price, oi.subtotal
         FROM order_item oi JOIN menu_item m ON m.item_id = oi.item_id
         WHERE oi.order_id = ? ORDER BY oi.order_item_id'
    );
    $it->execute([$order_id]);
    $items = $it->fetchAll();
}

$total    = (float) $bill['total_amount'];
$discount = (float) $bill['discount_amount'];
$subtotal = $total + $discount;
$bill_no  = 'PPC-' . str_pad((string) $bill['order_id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bill <?php echo htmlspecialchars($bill_no); ?> | Pawan Punjabi Chinese Restaurant</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo htmlspecialchars($css_href); ?>">
<style>
  .bill-wrap { max-width: 760px; margin: 0 auto; padding: 32px 24px 60px; }
  .bill-bar { display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
  .bill { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 36px; position: relative; }
  .bill h1 { font-family: 'Fraunces', serif; font-size: 1.6rem; margin: 0 0 4px; }
  .bill .muted { opacity: 0.65; font-size: 0.86rem; line-height: 1.6; }
  .bill-head { display: flex; justify-content: space-between; gap: 20px; flex-wrap: wrap; padding-bottom: 20px; border-bottom: 2px solid var(--ink); }
  .bill-head .right { text-align: right; }
  .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 20px 0; font-size: 0.9rem; line-height: 1.7; }
  @media (max-width: 560px) { .meta { grid-template-columns: 1fr; } .bill { padding: 22px; } }
  .meta strong { display: block; font-size: 0.78rem; opacity: 0.6; }
  table.lines { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
  table.lines th { text-align: left; font-size: 0.78rem; opacity: 0.6; padding: 8px 6px; border-bottom: 1px solid var(--line); }
  table.lines td { padding: 10px 6px; border-bottom: 1px solid var(--line); }
  table.lines .num { text-align: right; white-space: nowrap; }
  .totals { margin: 16px 0 0 auto; width: 280px; max-width: 100%; font-size: 0.95rem; }
  .totals div { display: flex; justify-content: space-between; padding: 5px 0; }
  .totals .grand { border-top: 2px solid var(--ink); margin-top: 6px; padding-top: 10px; font-weight: 700; font-size: 1.15rem; }
  .totals .disc { color: var(--jade); }
  .stamp { position: absolute; top: 90px; right: 36px; border: 3px solid var(--brick); color: var(--brick); padding: 4px 14px; border-radius: 8px; font-weight: 800; letter-spacing: 2px; transform: rotate(-8deg); }
  .thanks { text-align: center; margin-top: 28px; font-size: 0.86rem; opacity: 0.65; }
  @media print {
    header, .bill-bar { display: none !important; }
    body { background: #fff !important; color: #000 !important; }
    .bill { border: none; padding: 0; box-shadow: none; }
    .bill-wrap { padding: 0; }
  }
</style>
</head>
<body>
<div class="bill-wrap">
  <div class="bill-bar">
    <a href="<?php echo htmlspecialchars($back_href); ?>" class="btn-ghost">Back</a>
    <?php if ($has_bill): ?><button type="button" class="btn-primary" onclick="window.print()">Print or save as PDF</button><?php endif; ?>
  </div>

<?php if (!$has_bill): ?>

  <div class="bill" style="text-align:center;">
    <h1>No bill yet</h1>
    <p class="muted">A bill is created once payment is confirmed.</p>
  </div>

<?php else: ?>

  <div class="bill">
    <?php if ($bill['payment_status'] === 'refunded'): ?><div class="stamp">REFUNDED</div><?php endif; ?>

    <div class="bill-head">
      <div>
        <h1>Pawan Punjabi Chinese Restaurant</h1>
        <div class="muted">Shop No 1, Near ICICI Bank, Bardoli&ndash;Mahuva Road,<br>Tarsadi, Surat, Gujarat 394350</div>
      </div>
      <div class="right">
        <div style="font-family:'Fraunces',serif;font-size:1.2rem;font-weight:600;">Bill</div>
        <div class="muted"><?php echo htmlspecialchars($bill_no); ?><br><?php echo date('j M Y, g:i A', strtotime($bill['order_date'])); ?></div>
      </div>
    </div>

    <div class="meta">
      <div>
        <strong>BILLED TO</strong>
        <?php echo htmlspecialchars($bill['contact_name']); ?><br>
        <?php echo htmlspecialchars($bill['contact_phone']); ?>
      </div>
      <div>
        <strong>ORDER</strong>
        #<?php echo (int) $bill['order_id']; ?> &middot; <?php echo $bill['order_type'] === 'dinein' ? 'Dine-in' : 'Takeaway'; ?>
        <?php if ($bill['table_no']): ?> &middot; Table <?php echo htmlspecialchars($bill['table_no']); ?><?php endif; ?>
        <?php if ($bill['scheduled_time']): ?><br>Scheduled for <?php echo date('j M Y, g:i A', strtotime($bill['scheduled_time'])); ?><?php endif; ?>
      </div>
      <div>
        <strong>PAYMENT</strong>
        <?php echo strtoupper(htmlspecialchars($bill['payment_method'])); ?> &middot; <?php echo $bill['payment_status'] === 'refunded' ? 'Refunded' : 'Paid'; ?>
        <?php if ($bill['paid_at']): ?><br><?php echo date('j M Y, g:i A', strtotime($bill['paid_at'])); ?><?php endif; ?>
      </div>
      <div>
        <strong>REFERENCE</strong>
        <?php echo htmlspecialchars($bill['transaction_ref'] ?? ''); ?>
      </div>
    </div>

    <table class="lines">
      <thead><tr><th>Item</th><th class="num">Price</th><th class="num">Qty</th><th class="num">Amount</th></tr></thead>
      <tbody>
        <?php foreach ($items as $i): ?>
          <tr>
            <td><?php echo htmlspecialchars($i['name']); ?></td>
            <td class="num">&#8377;<?php echo number_format((float) $i['unit_price'], 2); ?></td>
            <td class="num"><?php echo (int) $i['quantity']; ?></td>
            <td class="num">&#8377;<?php echo number_format((float) $i['subtotal'], 2); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="totals">
      <div><span>Subtotal</span><span>&#8377;<?php echo number_format($subtotal, 2); ?></span></div>
      <?php if ($discount > 0): ?>
        <div class="disc"><span>Discount<?php echo $bill['coupon_code'] ? ' (' . htmlspecialchars($bill['coupon_code']) . ')' : ''; ?></span><span>-&#8377;<?php echo number_format($discount, 2); ?></span></div>
      <?php endif; ?>
      <div class="grand"><span>Total</span><span>&#8377;<?php echo number_format($total, 2); ?></span></div>
    </div>

    <p class="thanks">Thank you for ordering from Pawan Punjabi Chinese Restaurant.</p>
  </div>

<?php endif; ?>
</div>
</body>
</html>