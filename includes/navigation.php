<?php 
/**
 * Navigation template for Blackjack PHP
 *
 * This file contains the navigation menu that appears in the header.
 * It changes based on whether the user is logged in or not.
 */

// Only show full navigation if user is logged in
if ($isLoggedIn):
?>
<nav>
    <ul class="navbar-nav">
        <li class="nav-item">
            <a href="lobby.php" class="nav-link <?php echo ($currentPage === 'lobby.php') ? 'active' : ''; ?>">Dashboard</a>
        </li>
        <li class="nav-item">
            <a href="game.php" class="nav-link <?php echo ($currentPage === 'game.php') ? 'active' : ''; ?>">Game Table</a>
        </li>
        <li class="nav-item">
            <a href="settings.php" class="nav-link <?php echo ($currentPage === 'settings.php') ? 'active' : ''; ?>">Settings</a>
        </li>
        <li class="nav-item">
            <a href="community.php" class="nav-link <?php echo ($currentPage === 'community.php') ? 'active' : ''; ?>">Community</a>
        </li>
        <li class="nav-item">
            <a href="profile.php" class="nav-link <?php echo ($currentPage === 'profile.php') ? 'active' : ''; ?>"><?php echo htmlspecialchars($_SESSION['display_name']); ?></a>
        </li>
        <li class="nav-item">
            <a href="logout.php" class="nav-link">Logout</a>
        </li>
    </ul>
</nav>
<?php else: ?>
<nav>
    <ul class="navbar-nav">
        <li class="nav-item">
            <a href="login.php" class="nav-link <?php echo ($currentPage === 'login.php') ? 'active' : ''; ?>">Login</a>
        </li>
        <li class="nav-item">
            <a href="register.php" class="nav-link <?php echo ($currentPage === 'register.php') ? 'active' : ''; ?>">Register</a>
        </li>
    </ul>
</nav>
<?php endif; ?>