</main>

    <!-- Fixed Bottom Navigation Bar -->
    <nav class="bottom-nav">
        <a href="/tailorpro/dashboard.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'dashboard.php') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
        </a>
        <a href="/tailorpro/customers/addingcustomer.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'addingcustomer.php') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-user-plus"></i>
            <span>Add customer</span>
        </a>
        <a href="/tailorpro/customers/index.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'customers') !== false && strpos($_SERVER['REQUEST_URI'], 'addingcustomer.php') === false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-user"></i>
            <span>Customers</span>
        </a>
        <a href="/tailorpro/measurements/index.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'measurements') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-tape"></i>
            <span>Measurements</span>
        </a>
        <a href="/tailorpro/orders/index.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'orders') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-bag-shopping"></i>
            <span>Orders</span>
        </a>
    </nav>

</body>
</html>