<?php
/**
 * Hall of Fame Page
 *
 * Shows ranking of top 20 players based on ranking score (ROI * Total Bet)
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
$currentPage = 'hall_of_fame.php';

// Get database connection
$pdo = getDb();

// Get hall of fame data
try {
    $hallOfFameData = getHallOfFameData($pdo, $timeframe);
} catch (Exception $e) {
    $error = "Unable to load Hall of Fame data: " . $e->getMessage();
    $hallOfFameData = [];
}

// Set page title
$pageTitle = 'Hall of Fame';

/**
 * Get Hall of Fame data based on timeframe
 */
function getHallOfFameData($pdo, $timeframe) {
    // Base query to calculate ranking score (ROI * Total Bet)
    $baseQuery = "
        SELECT 
            u.display_name,
            COALESCE(SUM(s.all_time_total_won), 0) as total_won,
            COALESCE(SUM(s.all_time_total_loss), 0) as total_loss,
            COALESCE(SUM(s.all_time_total_bet), 0) as total_bet,
            CASE 
                WHEN COALESCE(SUM(s.all_time_total_bet), 0) > 0 
                THEN ((COALESCE(SUM(s.all_time_total_won), 0) + COALESCE(SUM(s.all_time_total_loss), 0)) / COALESCE(SUM(s.all_time_total_bet), 0)) * 100
                ELSE 0 
            END as roi,
            CASE 
                WHEN COALESCE(SUM(s.all_time_total_bet), 0) > 0 
                THEN (((COALESCE(SUM(s.all_time_total_won), 0) + COALESCE(SUM(s.all_time_total_loss), 0)) / COALESCE(SUM(s.all_time_total_bet), 0)) * 100) * COALESCE(SUM(s.all_time_total_bet), 0)
                ELSE 0 
            END as ranking_score
        FROM users u
        LEFT JOIN game_sessions s ON u.user_id = s.user_id
    ";
    
    // Add time filter based on timeframe
    $whereClause = "";
    $params = [];
    
    switch ($timeframe) {
        case 'today':
            $whereClause = "WHERE DATE(s.start_time) = CURDATE()";
            // For today, use session stats instead of all-time
            $baseQuery = "
                SELECT 
                    u.display_name,
                    COALESCE(SUM(s.session_total_won), 0) as total_won,
                    COALESCE(SUM(s.session_total_loss), 0) as total_loss,
                    COALESCE(SUM(s.session_total_bet), 0) as total_bet,
                    CASE 
                        WHEN COALESCE(SUM(s.session_total_bet), 0) > 0 
                        THEN ((COALESCE(SUM(s.session_total_won), 0) + COALESCE(SUM(s.session_total_loss), 0)) / COALESCE(SUM(s.session_total_bet), 0)) * 100
                        ELSE 0 
                    END as roi,
                    CASE 
                        WHEN COALESCE(SUM(s.session_total_bet), 0) > 0 
                        THEN (((COALESCE(SUM(s.session_total_won), 0) + COALESCE(SUM(s.session_total_loss), 0)) / COALESCE(SUM(s.session_total_bet), 0)) * 100) * COALESCE(SUM(s.session_total_bet), 0)
                        ELSE 0 
                    END as ranking_score
                FROM users u
                LEFT JOIN game_sessions s ON u.user_id = s.user_id
            ";
            break;
        case 'month':
            $whereClause = "WHERE YEAR(s.start_time) = YEAR(CURDATE()) AND MONTH(s.start_time) = MONTH(CURDATE())";
            // For this month, use session stats instead of all-time
            $baseQuery = "
                SELECT 
                    u.display_name,
                    COALESCE(SUM(s.session_total_won), 0) as total_won,
                    COALESCE(SUM(s.session_total_loss), 0) as total_loss,
                    COALESCE(SUM(s.session_total_bet), 0) as total_bet,
                    CASE 
                        WHEN COALESCE(SUM(s.session_total_bet), 0) > 0 
                        THEN ((COALESCE(SUM(s.session_total_won), 0) + COALESCE(SUM(s.session_total_loss), 0)) / COALESCE(SUM(s.session_total_bet), 0)) * 100
                        ELSE 0 
                    END as roi,
                    CASE 
                        WHEN COALESCE(SUM(s.session_total_bet), 0) > 0 
                        THEN (((COALESCE(SUM(s.session_total_won), 0) + COALESCE(SUM(s.session_total_loss), 0)) / COALESCE(SUM(s.session_total_bet), 0)) * 100) * COALESCE(SUM(s.session_total_bet), 0)
                        ELSE 0 
                    END as ranking_score
                FROM users u
                LEFT JOIN game_sessions s ON u.user_id = s.user_id
            ";
            break;
        case 'all':
        default:
            // No additional where clause for all time
            break;
    }
    
    $query = $baseQuery . " " . $whereClause . "
        GROUP BY u.user_id, u.display_name
        HAVING COALESCE(SUM(s.all_time_total_bet), 0) > 0 OR COALESCE(SUM(s.session_total_bet), 0) > 0
        ORDER BY ranking_score DESC
        LIMIT 20
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Include header
include_once 'includes/header.php';
?>

<div class="card mb-3">
    <h1>Hall of Fame</h1>
    <p class="text-center">Top 20 players ranked by performance score (ROI × Total Bet)</p>
    
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
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3>
                                <?php 
                                switch($timeframe) {
                                    case 'today': echo "Today's Top Players"; break;
                                    case 'month': echo "This Month's Top Players"; break;
                                    default: echo "All Time Top Players"; break;
                                }
                                ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($hallOfFameData)): ?>
                                <p class="text-muted">No player data available for this timeframe.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>Display Name</th>
                                                <th>Ranking Score</th>
                                                <th>ROI</th>
                                                <th>Total Bet</th>
                                                <th>Net Winnings</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($hallOfFameData as $index => $player): ?>
                                                <tr class="<?php echo $index < 3 ? 'top-player top-' . ($index + 1) : ''; ?>">
                                                    <td>
                                                        <span class="rank-badge rank-<?php echo $index + 1; ?>">
                                                            <?php if ($index == 0): ?>
                                                                <i class="fas fa-crown" style="color: gold;"></i> #1
                                                            <?php elseif ($index == 1): ?>
                                                                <i class="fas fa-medal" style="color: silver;"></i> #2
                                                            <?php elseif ($index == 2): ?>
                                                                <i class="fas fa-medal" style="color: #cd7f32;"></i> #3
                                                            <?php else: ?>
                                                                #<?php echo $index + 1; ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <strong class="player-name">
                                                            <?php echo htmlspecialchars($player['display_name']); ?>
                                                        </strong>
                                                    </td>
                                                    <td>
                                                        <span class="ranking-score">
                                                            <?php echo number_format($player['ranking_score'], 2); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="roi <?php echo $player['roi'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo number_format($player['roi'], 2); ?>%
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="total-bet">
                                                            $<?php echo number_format($player['total_bet'], 2); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $netWinnings = $player['total_won'] + $player['total_loss'];
                                                        ?>
                                                        <span class="net-winnings <?php echo $netWinnings >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                            $<?php echo number_format($netWinnings, 2); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="hall-of-fame-info mt-3">
                                    <div class="info-card">
                                        <h4>How Ranking Score is Calculated</h4>
                                        <p><strong>Ranking Score = ROI × Total Bet</strong></p>
                                        <ul>
                                            <li><strong>ROI (Return on Investment):</strong> (Net Winnings ÷ Total Bet) × 100</li>
                                            <li><strong>Total Bet:</strong> Sum of all bets placed</li>
                                            <li><strong>Net Winnings:</strong> Total Won - Total Lost</li>
                                        </ul>
                                        <p class="text-muted">
                                            This scoring system rewards both profitability (ROI) and volume of play (Total Bet), 
                                            encouraging consistent, skilled play over time.
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
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

// Add trophy animations for top 3 players
document.addEventListener('DOMContentLoaded', function() {
    const topPlayers = document.querySelectorAll('.top-player');
    topPlayers.forEach((player, index) => {
        player.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.02)';
            this.style.transition = 'transform 0.3s ease';
        });
        
        player.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
});
</script>
