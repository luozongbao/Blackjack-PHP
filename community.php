<?php
/**
 * Community Dashboard Page
 *
 * Shows community analytics including user locations, browser stats, and recent IPs
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include necessary files
require_once 'includes/database.php';
require_once 'classes/analytics_class.php';

// Track user activity
$analytics = new Analytics();
$ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$analytics->trackUserSession($_SESSION['user_id'], $ipAddress, $userAgent);

// Get timeframe from request (default to 'all')
$timeframe = $_GET['timeframe'] ?? 'all';
$validTimeframes = ['all', 'month', 'today'];
if (!in_array($timeframe, $validTimeframes)) {
    $timeframe = 'all';
}

// Set current page for navigation
$currentPage = 'community.php';

// Get analytics data
try {
    $locationStats = $analytics->getLocationStats($timeframe);
    $browserStats = $analytics->getBrowserStats($timeframe);
    $recentIPs = $analytics->getRecentUserIPs($timeframe, 50);
} catch (Exception $e) {
    $error = "Unable to load community data: " . $e->getMessage();
    $locationStats = [];
    $browserStats = [];
    $recentIPs = [];
}

// Set page title
$pageTitle = 'Community Dashboard';

// Include header
include_once 'includes/header.php';
?>

<div class="card mb-3">
    <h1>Community Dashboard</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
</div>

<div class="community-dashboard">
    <!-- Timeframe Tabs -->
    <div class="tabs-container">
        <ul class="nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?php echo $timeframe === 'all' ? 'active' : ''; ?>" 
                   href="?timeframe=all">All Time</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $timeframe === 'month' ? 'active' : ''; ?>" 
                   href="?timeframe=month">This Month</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $timeframe === 'today' ? 'active' : ''; ?>" 
                   href="?timeframe=today">Today</a>
            </li>
        </ul>
    </div>
            
    <div class="tab-content">
        <div class="tab-pane active">
            <div class="row mt-4">
                <!-- User Locations -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3>Player Locations</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($locationStats)): ?>
                                <p class="text-muted">No location data available for this timeframe.</p>
                            <?php else: ?>
                                <div class="stats-list">
                                    <?php foreach ($locationStats as $location): ?>
                                        <div class="stat-item">
                                            <div class="stat-info">
                                                <div class="stat-header">
                                                    <span class="stat-label">
                                                        <img src="https://flagcdn.com/16x12/<?php echo strtolower(substr($location['country'], 0, 2)); ?>.png" 
                                                             alt="<?php echo htmlspecialchars($location['country']); ?>" 
                                                             onerror="this.style.display='none'">
                                                        <?php echo htmlspecialchars($location['country']); ?>
                                                    </span>
                                                    <span class="stat-count"><?php echo $location['user_count']; ?> players</span>
                                                </div>
                                                <div class="stat-bar">
                                                    <div class="stat-progress" style="width: <?php echo $location['percentage']; ?>%"></div>
                                                </div>
                                                <div class="stat-percentage"><?php echo number_format($location['percentage'], 1); ?>%</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Browser Statistics -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3>Browser Usage</h3>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($browserStats)): ?>
                                        <p class="text-muted">No browser data available for this timeframe.</p>
                                    <?php else: ?>
                                        <div class="stats-list">
                                            <?php foreach ($browserStats as $browser): ?>
                                                <div class="stat-item">
                                                    <div class="stat-info">
                                                        <div class="stat-header">
                                                            <span class="stat-label">
                                                                <i class="browser-icon browser-<?php echo strtolower($browser['browser']); ?>"></i>
                                                                <?php echo htmlspecialchars($browser['browser']); ?>
                                                            </span>
                                                            <span class="stat-count"><?php echo $browser['user_count']; ?> players</span>
                                                        </div>
                                                        <div class="stat-bar">
                                                            <div class="stat-progress" style="width: <?php echo $browser['percentage']; ?>%"></div>
                                                        </div>
                                                        <div class="stat-percentage"><?php echo number_format($browser['percentage'], 1); ?>%</div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent User IPs -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3>Recent User Activity (by IP)</h3>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recentIPs)): ?>
                                        <p class="text-muted">No recent activity data available for this timeframe.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>IP Address</th>
                                                        <th>Location</th>
                                                        <th>Browser</th>
                                                        <th>Platform</th>
                                                        <th>Last User</th>
                                                        <th>Unique Users</th>
                                                        <th>Last Seen</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recentIPs as $ip): ?>
                                                        <tr>
                                                            <td>
                                                                <code><?php echo htmlspecialchars($ip['ip_address']); ?></code>
                                                            </td>
                                                            <td>
                                                                <?php if ($ip['country']): ?>
                                                                    <img src="https://flagcdn.com/16x12/<?php echo strtolower(substr($ip['country'], 0, 2)); ?>.png" 
                                                                         alt="<?php echo htmlspecialchars($ip['country']); ?>" 
                                                                         onerror="this.style.display='none'">
                                                                    <?php echo htmlspecialchars($ip['country']); ?>
                                                                    <?php if ($ip['city']): ?>
                                                                        <br><small class="text-muted"><?php echo htmlspecialchars($ip['city']); ?></small>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Unknown</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <i class="browser-icon browser-<?php echo strtolower($ip['browser']); ?>"></i>
                                                                <?php echo htmlspecialchars($ip['browser']); ?>
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($ip['platform']); ?>
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($ip['last_user'] ?? 'Unknown'); ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-info"><?php echo $ip['unique_users']; ?></span>
                                                            </td>
                                                            <td>
                                                                <span class="time-ago" data-time="<?php echo $ip['last_seen']; ?>">
                                                                    <?php echo date('M j, Y g:i A', strtotime($ip['last_seen'])); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<script>
// Auto-refresh page every 5 minutes
setTimeout(function() {
    window.location.reload();
}, 300000);

// Format time ago for better UX
document.addEventListener('DOMContentLoaded', function() {
    const timeElements = document.querySelectorAll('.time-ago');
    timeElements.forEach(function(element) {
        const time = new Date(element.dataset.time);
        const now = new Date();
        const diffInSeconds = Math.floor((now - time) / 1000);
        
        let timeAgo;
        if (diffInSeconds < 60) {
            timeAgo = 'Just now';
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            timeAgo = minutes + ' minute' + (minutes !== 1 ? 's' : '') + ' ago';
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            timeAgo = hours + ' hour' + (hours !== 1 ? 's' : '') + ' ago';
        } else {
            const days = Math.floor(diffInSeconds / 86400);
            timeAgo = days + ' day' + (days !== 1 ? 's' : '') + ' ago';
        }
        
        element.title = element.textContent;
        element.textContent = timeAgo;
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>
