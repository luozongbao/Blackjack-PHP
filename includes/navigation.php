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
            <a href="/Blackjack-PHP/views/lobby.php" class="nav-link <?php echo ($currentPage === 'lobby.php') ? 'active' : ''; ?>">Dashboard</a>
        </li>
        <li class="nav-item">
            <a href="/Blackjack-PHP/views/game.php" class="nav-link <?php echo ($currentPage === 'game.php') ? 'active' : ''; ?>">Game Table</a>
        </li>
        <li class="nav-item">
            <a href="/Blackjack-PHP/views/settings.php" class="nav-link <?php echo ($currentPage === 'settings.php') ? 'active' : ''; ?>">Settings</a>
        </li>
        <li class="nav-item">
            <a href="/Blackjack-PHP/views/profile.php" class="nav-link <?php echo ($currentPage === 'profile.php') ? 'active' : ''; ?>"><?php echo htmlspecialchars($_SESSION['display_name']); ?></a>
        </li>
        <li class="nav-item">
            <a href="/Blackjack-PHP/views/logout.php" class="nav-link">Logout</a>
        </li>
    </ul>
</nav>
<?php else: ?>
<nav>
    <ul class="navbar-nav">
        <li class="nav-item">
            <a href="/Blackjack-PHP/views/login.php" class="nav-link <?php echo ($currentPage === 'login.php') ? 'active' : ''; ?>">Login</a>
        </li>
        <li class="nav-item">
            <a href="/Blackjack-PHP/views/register.php" class="nav-link <?php echo ($currentPage === 'register.php') ? 'active' : ''; ?>">Register</a>
        </li>
    </ul>
</nav>
<?php endif; ?>