<?php
/*
 * Shared footer. Include at the bottom of every customer page:
 *   include '../includes/footer.php';
 * The page should also load the shared script after this include:
 *   <script src="js/main.js"></script>
 */
?>
<footer class="site" id="footer">
  <div class="foot-grid">
    <div>
      <div class="brand display" style="font-size:1.2rem;">Pawan Punjabi Chinese</div>
      <p>Shop No 1, Near ICICI Bank,<br>Bardoli&ndash;Mahuva Road, Tarsadi,<br>Surat, Gujarat 394350</p>
    </div>
    <div>
      <h4>Explore</h4>
      <ul>
        <li><a href="menu.php">Full menu</a></li>
        <li><a href="group-order.php">Group order</a></li>
        <li><a href="reviews.php">Customer reviews</a></li>
        <li><a href="track-order.php">Track my order</a></li>
      </ul>
    </div>
    <div>
      <h4>Hours</h4>
      <ul>
        <li>Mon &ndash; Sun: 11:00 AM &ndash; 11:00 PM</li>
        <li>Dine-in &amp; takeaway available</li>
      </ul>
    </div>
  </div>
  <div class="foot-bottom">
    <span>&copy; <?php echo date('Y'); ?> Pawan Punjabi Chinese Restaurant</span>
    <!-- The admin folder is built in a later stage; this link works once it exists. -->
    <a href="../admin/login.php" style="opacity:0.8;">Admin / Manager login</a>
  </div>
</footer>

<div class="toast" id="toast" role="status" aria-live="polite"></div>