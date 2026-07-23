</main>

<?php if (!isAdmin()): ?>
<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <h3><i class="fas fa-leaf"></i> Avocado Nail Studio</h3>
                <p>Where fresh meets elegant. Premium nail artistry inspired by nature and fashion.</p>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="/nail_salon/customer/dashboard.php">Home</a></li>
                    <li><a href="/nail_salon/customer/services.php">Services</a></li>
                    <li><a href="/nail_salon/customer/staff.php">Our Team</a></li>
                    <li><a href="/nail_salon/customer/booking.php">Book Now</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Account</h4>
                <ul>
                    <li><a href="/nail_salon/customer/profile.php">My Profile</a></li>
                    <li><a href="/nail_salon/auth/change-password.php">Change Password</a></li>
                    <li><a href="/nail_salon/auth/logout.php">Logout</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Get in Touch</h4>
                <ul>
                    <li><i class="fas fa-map-marker-alt"></i> Quezon City, PH</li>
                    <li><i class="fas fa-phone"></i> +63 912 345 6789</li>
                    <li><i class="fas fa-envelope"></i> hello@avocadonail.com</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> Avocado Nail & Model Studio. All rights reserved.</span>
            <div class="social-links">
                <a href="#" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="#" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
                <a href="#" aria-label="Pinterest"><i class="fab fa-pinterest"></i></a>
            </div>
        </div>
    </div>
</footer>
<?php endif; ?>

</body>
</html>
