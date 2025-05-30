<?php
/**
 * Analytics Class for tracking user data and generating reports
 */

require_once __DIR__ . '/../includes/database.php';

class Analytics {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDb();
    }
    
    /**
     * Track user session with IP, browser, and location data
     */
    public function trackUserSession($userId, $ipAddress, $userAgent) {
        try {
            // Parse user agent for browser info
            $browserInfo = $this->parseUserAgent($userAgent);
            
            // Get location data from IP
            $locationData = $this->getLocationFromIP($ipAddress);
            
            // Check if we already have an active session for this user today
            $stmt = $this->pdo->prepare("
                SELECT analytics_id FROM user_analytics 
                WHERE user_id = ? AND ip_address = ? 
                AND DATE(session_start) = CURDATE() 
                ORDER BY session_start DESC LIMIT 1
            ");
            $stmt->execute([$userId, $ipAddress]);
            $existingSession = $stmt->fetch();
            
            if ($existingSession) {
                // Update last activity for existing session
                $stmt = $this->pdo->prepare("
                    UPDATE user_analytics 
                    SET last_activity = CURRENT_TIMESTAMP 
                    WHERE analytics_id = ?
                ");
                $stmt->execute([$existingSession['analytics_id']]);
            } else {
                // Create new analytics record
                $stmt = $this->pdo->prepare("
                    INSERT INTO user_analytics (
                        user_id, ip_address, user_agent, browser, browser_version, 
                        platform, country, region, city, latitude, longitude
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $userId,
                    $ipAddress,
                    $userAgent,
                    $browserInfo['browser'],
                    $browserInfo['version'],
                    $browserInfo['platform'],
                    $locationData['country'],
                    $locationData['region'],
                    $locationData['city'],
                    $locationData['latitude'],
                    $locationData['longitude']
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Analytics tracking error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Parse user agent string to extract browser and platform info
     */
    private function parseUserAgent($userAgent) {
        $browser = 'Unknown';
        $version = '0.0';
        $platform = 'Unknown';
        
        // Detect platform
        if (preg_match('/Windows NT/i', $userAgent)) {
            $platform = 'Windows';
        } elseif (preg_match('/Mac OS X/i', $userAgent)) {
            $platform = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $platform = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $platform = 'Android';
        } elseif (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
            $platform = 'iOS';
        }
        
        // Detect browser
        if (preg_match('/Chrome\/([0-9.]+)/i', $userAgent, $matches)) {
            $browser = 'Chrome';
            $version = $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/i', $userAgent, $matches)) {
            $browser = 'Firefox';
            $version = $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/i', $userAgent, $matches) && !preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Safari';
            $version = $matches[1];
        } elseif (preg_match('/Edge\/([0-9.]+)/i', $userAgent, $matches)) {
            $browser = 'Edge';
            $version = $matches[1];
        } elseif (preg_match('/Opera\/([0-9.]+)/i', $userAgent, $matches)) {
            $browser = 'Opera';
            $version = $matches[1];
        }
        
        return [
            'browser' => $browser,
            'version' => $version,
            'platform' => $platform
        ];
    }
    
    /**
     * Get location data from IP address using a free IP geolocation service
     */
    private function getLocationFromIP($ipAddress) {
        // Default values
        $locationData = [
            'country' => null,
            'region' => null,
            'city' => null,
            'latitude' => null,
            'longitude' => null
        ];
        
        // Skip local/private IP addresses
        if ($ipAddress === '127.0.0.1' || $ipAddress === '::1' || 
            filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return $locationData;
        }
        
        try {
            // Using ip-api.com free service (limit: 1000 requests per minute)
            $url = "http://ip-api.com/json/{$ipAddress}";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'Blackjack-PHP/1.0'
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            if ($response !== false) {
                $data = json_decode($response, true);
                if ($data && $data['status'] === 'success') {
                    $locationData = [
                        'country' => $data['country'] ?? null,
                        'region' => $data['regionName'] ?? null,
                        'city' => $data['city'] ?? null,
                        'latitude' => $data['lat'] ?? null,
                        'longitude' => $data['lon'] ?? null
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("IP geolocation error: " . $e->getMessage());
        }
        
        return $locationData;
    }
    
    /**
     * Get user location statistics for dashboard
     */
    public function getLocationStats($timeframe = 'all') {
        $whereClause = $this->getTimeframeWhereClause($timeframe);
        
        $sql = "
            SELECT 
                country,
                COUNT(DISTINCT user_id) as user_count,
                COUNT(*) as session_count,
                ROUND((COUNT(DISTINCT user_id) * 100.0 / (
                    SELECT COUNT(DISTINCT user_id) 
                    FROM user_analytics 
                    WHERE country IS NOT NULL {$whereClause}
                )), 2) as percentage
            FROM user_analytics 
            WHERE country IS NOT NULL {$whereClause}
            GROUP BY country 
            ORDER BY user_count DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get browser statistics for dashboard
     */
    public function getBrowserStats($timeframe = 'all') {
        $whereClause = $this->getTimeframeWhereClause($timeframe);
        
        $sql = "
            SELECT 
                browser,
                COUNT(DISTINCT user_id) as user_count,
                COUNT(*) as session_count,
                ROUND((COUNT(DISTINCT user_id) * 100.0 / (
                    SELECT COUNT(DISTINCT user_id) 
                    FROM user_analytics 
                    WHERE 1=1 {$whereClause}
                )), 2) as percentage
            FROM user_analytics 
            WHERE 1=1 {$whereClause}
            GROUP BY browser 
            ORDER BY user_count DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent user IPs
     */
    public function getRecentUserIPs($timeframe = 'all', $limit = 100) {
        $whereClause = $this->getTimeframeWhereClause($timeframe);
        
        $sql = "
            SELECT DISTINCT
                ua.ip_address,
                ua.country,
                ua.city,
                ua.browser,
                ua.platform,
                MAX(ua.last_activity) as last_seen,
                COUNT(DISTINCT ua.user_id) as unique_users,
                u.username as last_user
            FROM user_analytics ua
            LEFT JOIN users u ON ua.user_id = (
                SELECT user_id FROM user_analytics ua2 
                WHERE ua2.ip_address = ua.ip_address 
                ORDER BY ua2.last_activity DESC LIMIT 1
            )
            WHERE 1=1 {$whereClause}
            GROUP BY ua.ip_address, ua.country, ua.city, ua.browser, ua.platform
            ORDER BY last_seen DESC
            LIMIT {$limit}
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get timeframe WHERE clause for SQL queries
     */
    private function getTimeframeWhereClause($timeframe) {
        switch ($timeframe) {
            case 'today':
                return ' AND DATE(session_start) = CURDATE()';
            case 'month':
                return ' AND session_start >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)';
            case 'all':
            default:
                return '';
        }
    }
}
?>
