# Blackjack PHP v1.1.2

A sophisticated web-based Blackjack game implementation using PHP, MySQL, and JavaScript with full session management, comprehensive statistics tracking, revised dashboard metrics, dynamic betting limits, advanced dealer logic with authentic regional dealing styles, and immersive audio experience.

## Features

### Game Features
- **Authentic Regional Dealing Styles** with accurate rule implementations:
  - **American Style**: Standard Las Vegas rules with hole card dealing
  - **European Style**: No hole card, dealer blackjack beats all non-blackjack hands
  - **Macau Style**: Unique dealer blackjack rules with partial loss protection
- Advanced all-busted logic with deal style specific behavior
- Configurable number of decks (1-8)
- Flexible shuffling methods (Auto-shuffle or Shoe with customizable deck penetration)
- Complete rule customization
- Comprehensive betting options (Split, Double, Insurance, Surrender)
- Real-time game statistics
- Immersive sound effects and background music
- Customizable audio controls

### Customizable Game Rules
- **Regional Dealing Variations**:
  - **American**: Dealer receives hole card, immediate blackjack detection
  - **European**: No hole card until player decisions complete, dealer blackjack beats all non-blackjack hands
  - **Macau**: Unique dealer blackjack rule - original hand loses original bet only, split hands get full refund
- Dealer behavior (Hit on Soft 17 / Stand on All 17s)
- Blackjack payout options (3:2 or 1:1)
- Surrender options (Early, Late, or None)
- Double down rules (Any Two Cards or 9-10-11 only)
- Split limits (1-4 splits)
- Insurance betting
- Double after split

### Player Features
- User authentication and profile management
- Revised dashboard statistics with clear financial metrics
- Comprehensive session statistics
- All-time statistics tracking
- Customizable initial bankroll
- Dynamic table betting limits (minimum and maximum)
- Session management with restart capability

### Technical Features
- Secure user authentication with session management
- Persistent game state with session restoration
- Mobile-responsive design with modern UI
- Real-time statistics updates and tracking
- Database-backed persistence with transaction safety
- CSRF protection and XSS prevention
- Nginx web server support with PHP-FPM
- Error handling and debugging capabilities

## Requirements

- PHP 8.0 or higher (tested with PHP 8.3)
- MySQL 5.7 or MariaDB 10.2 or higher
- Web server (Apache/Nginx recommended)
- Modern web browser with JavaScript enabled
- PHP extensions: PDO, PDO_MySQL, session, json
- Docker and Docker Compose (optional, for containerized setup)

## Installation

### Option 1: Traditional Installation

1. **Clone the repository** to your web server directory:
   ```bash
   git clone https://github.com/username/Blackjack-PHP.git
   ```

2. **Set up the database**:
   - Create a MySQL database with appropriate privileges
   - Note your database credentials (host, database name, username, password)

3. **Configure the application**:
   - Navigate to `http://your-domain/includes/install.php`
   - Follow the installation wizard to set up database connection
   - Create your initial admin user account

4. **Set proper file permissions**:
   ```bash
   sudo chown -R www-data:www-data /path/to/Blackjack-PHP
   sudo chmod -R 755 /path/to/Blackjack-PHP
   ```

5. **Access the application** through your web browser

### Option 2: Docker Compose Installation (Recommended)

1. **Clone the repository**:
   ```bash
   git clone https://github.com/username/Blackjack-PHP.git
   cd Blackjack-PHP
   ```

2. **Create environment variables**:
   Create a `.env` file in the root directory with the following variables:
   ```
   DATABASENAME=blackjack
   DATABASEUSER=blackjackuser
   DATABASEPASS=your_secure_password
   ```

3. **Start the containers**:
   ```bash
   docker-compose up -d
   ```

4. **Access the installation wizard**:
   - Navigate to `http://localhost/includes/install.php` in your browser
   - Use the following database details:
     - Database Host: `db`
     - Database Name: `blackjack` (or whatever you set in .env)
     - Database Username: `blackjackuser` (or whatever you set in .env)
     - Database Password: (the password you set in .env)

5. **Complete the installation** by following the wizard

6. **Access the application** at `http://localhost`

### Docker Compose Structure

The containerized setup includes:
- **Nginx**: Web server on port 80
- **PHP-FPM 8.3**: PHP processor with all required extensions
- **MariaDB**: Database server
- **Persistent volumes**: For database data and logs

### Docker Commands

- **Start the application**:
  ```bash
  docker-compose up -d
  ```

- **Stop the application**:
  ```bash
  docker-compose down
  ```

- **View logs**:
  ```bash
  docker-compose logs
  ```
  
- **Restart a specific service**:
  ```bash
  docker-compose restart php
  ```

- **Access the database**:
  ```bash
  docker-compose exec db mysql -u root -p
  ```

### Nginx Configuration Example
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/Blackjack-PHP;
    index index.php;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

## Configuration

Game settings can be configured through the Settings page after logging in:

### Deck Configuration
- **Number of decks**: 1-8 decks per shoe
- **Shuffling method**: Auto-shuffle (every game) or Shoe method
- **Deck penetration**: 50-100% (when using shoe method)

#### Shuffle System Details
The game features two distinct shuffling methods:

1. **Auto-shuffle**: Deck is reshuffled after every game (traditional casino style)
2. **Shoe method**: Manual shuffle when deck penetration reaches the configured threshold
   - Penetration percentage tracks how much of the shoe has been dealt
   - When threshold is exceeded, the deck automatically resets to full size in the next game
   - Provides more realistic casino-style gameplay with shoe penetration tracking

**Note**: The shoe method was recently fixed (v0.3.2) to properly reset the deck to full size rather than just rearranging remaining cards.

### Money & Betting Configuration
- **Initial money**: Customizable starting bankroll for new sessions ($100 - $1,000,000)
- **Table minimum bet**: Minimum allowed bet amount (minimum $100, customizable)
- **Table maximum bet**: Maximum allowed bet amount (must be at least 2x minimum bet)
- **Bet enforcement**: Real-time validation ensures all bets comply with table limits
- **Multi-user support**: Each user can set their own betting limits independently

### Game Rules
- **Dealer behavior**: Hit on Soft 17 / Stand on All 17s
- **Blackjack payout**: 3:2 or 1:1 ratios
- **Surrender options**: Early, Late, or None
- **Double down rules**: Any Two Cards or 9-10-11 only
- **Split limits**: 1-4 splits allowed
- **Insurance betting**: Enable/disable
- **Double after split**: Allow/disallow

### Deal Styles
- **American**: Two cards dealt, dealer checks for blackjack
- **European**: One card dealt, lose all on dealer blackjack
- **Macau**: One card dealt, lose only original bet on dealer blackjack

### Audio System
- **Background Music**: Toggleable ambient casino music
- **Game Action Sounds**: Distinct sound effects for different actions:
  - **Deal**: Card dealing sound
  - **Hit**: Card hit sound
  - **Stand**: Stand action sound
  - **Double**: Double down action sound with chip sounds
  - **Split**: Card split sound
  - **Shuffle**: Deck shuffling sound
  - **Chips**: Betting sound when placing bets
- **Result Sounds**: Different sounds for game outcomes:
  - **Win**: Victory sound
  - **Lose**: Loss sound
  - **Push**: Push/tie sound
  - **Blackjack**: Special blackjack win sound
- **Audio Controls**: 
  - Sound toggle button (bottom-right corner)
  - Music toggle button (bottom-right corner)
  - Sound settings persist between sessions
  - Responsive design for controls

### Betting System
- **Table minimum bet**: Configurable minimum bet amount (default: $100)
- **Table maximum bet**: Configurable maximum bet amount (default: $10,000)
- **Bet validation**: Comprehensive validation ensures bets are within limits
- **Step validation**: Bets must be in multiples of the table minimum
- **Funds checking**: Cannot bet more than current available money
- **Dynamic limits**: Each user can customize their own table limits

### Session Management
- **Initial bankroll**: Customizable starting amount
- **Table betting limits**: Configurable minimum and maximum bet amounts
- **Session restart**: Reset statistics and money
- **All-time statistics**: Persistent cross-session tracking

## Security

- **Password hashing**: Secure bcrypt algorithm implementation
- **Database security**: Prepared statements prevent SQL injection
- **Input validation**: All forms validated on both client and server side
- **Session security**: Secure session management with regeneration
- **CSRF protection**: Token-based protection on all forms
- **XSS prevention**: Output escaping and input sanitization
- **File permissions**: Proper access controls on sensitive files

## Troubleshooting

### Common Issues

1. **HTTP 500 Error**: Check nginx error logs at `/var/log/nginx/error.log`
2. **Database Connection**: Verify credentials in `includes/config.php`
3. **File Permissions**: Ensure web server has read/write access
4. **PHP Extensions**: Verify required extensions are installed
5. **Session Issues**: Check PHP session configuration
6. **Betting Issues**: If betting validation is not working properly:
   - Verify table limits are set correctly in user settings
   - Check that minimum bet is at least $100
   - Ensure maximum bet is at least 2x the minimum bet
   - Clear browser cache and reload the page
7. **Shuffle Not Working**: If using shoe method and deck doesn't reset when penetration threshold is reached:
   - Verify deck penetration setting is between 50-100%
   - Check that shuffle method is set to "shoe" in game settings
   - Run test files (`test_simple_shuffle.php` or `test_comprehensive_shuffle.php`) to verify shuffle logic

### Debug Mode
Enable debug mode by adding to your configuration:
```php
define('DEBUG_MODE', true);
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

© 2025 Blackjack PHP. All rights reserved.

## Version History

**v1.1.1** - Dashboard Statistics Revision (May 26, 2025)
- 📊 **Revised**: Complete dashboard statistics overhaul for improved clarity
- 💰 **Enhanced**: Separate tracking of positive winnings vs actual losses
- 🎯 **Added**: Net calculation (Total Won + Total Loss) with proper color coding
- 📈 **Improved**: ROI calculation using Net/Total Bet formula
- 🎨 **Enhanced**: Color-coded statistics (Green for positive, Red for negative)
- ✅ **Added**: Real-time statistics updates in game interface
- 🧪 **Added**: Comprehensive testing suite for statistics verification
- 📚 **Updated**: Documentation with detailed implementation report

**v1.1.0** - All-Busted Logic Enhancement (May 26, 2025)
- 🚫 **Added**: Advanced all-busted logic for optimized dealer behavior
- 🎯 **Enhanced**: Deal style specific handling when all player hands are busted
- 🇺🇸 **American Style**: Dealer shows hole card but doesn't draw further when all hands busted
- 🇪🇺 **European/Macau Style**: Dealer doesn't draw any cards when all hands busted
- ⚡ **Improved**: Game performance by eliminating unnecessary dealer actions
- 🔧 **Added**: Helper method `areAllPlayerHandsBusted()` for comprehensive hand checking
- ✅ **Enhanced**: Early game termination logic for better user experience
- 🧪 **Added**: Comprehensive testing suite for all-busted scenarios
- 📚 **Updated**: Testing guide with all-busted logic verification instructions

**v1.0.0** - First Official Release (May 25, 2025)
- 🎵 **Added**: Immersive sound effects for all game actions (deal, hit, stand, etc.)
- 🎶 **Added**: Background music with toggle controls
- 💿 **Added**: Sound control system with mute functionality
- 🎮 **Enhanced**: Game interface with sound toggles in bottom-right corner
- 📊 **Fixed**: All-time statistics tracking and reset functionality
- 🔍 **Improved**: Game flow and user experience
- 🏆 **Added**: Full release status with all major features implemented

**v0.3.3** - Previous stable release (May 25, 2025)
- 💰 **Added**: Dynamic table betting limits - configurable minimum and maximum bet amounts per user
- 🔧 **Enhanced**: Betting validation system with both client-side and server-side validation
- ✅ **Improved**: Betting forms now use dynamic values from user settings instead of hardcoded limits
- 🎯 **Added**: Smart max bet calculation considering both available funds and table limits
- 🔒 **Enhanced**: Comprehensive bet validation with clear error messages
- 📊 **Updated**: All betting interfaces to support customizable table limits
- 🎮 **Improved**: User experience with real-time validation and formatted error messages

**v0.3.2** - Previous stable release (May 25, 2025)
- 🐛 **Fixed**: Critical manual shuffle bug in shoe method - deck now properly resets to full size when penetration threshold is exceeded
- 🔧 **Improved**: Shuffle logic to use `resetDeck()` instead of `shuffle()` for proper deck restoration
- ✅ **Added**: Comprehensive shuffle testing suite for verification
- 🎯 **Enhanced**: Game state management for more reliable shoe penetration handling
- 🔒 **Fixed**: API deck preservation - game API now properly preserves deck state for shoe method like the web interface
- 🎲 **Enhanced**: Deck creation validation - added proper validation to ensure deck settings are correctly used
- 📚 **Updated**: Documentation with shuffle system details and troubleshooting

**v0.3.1** - Previous stable release (May 25, 2025)
- 🐛 **Fixed**: Session restoration bug causing HTTP 500 errors
- 🔧 **Added**: Missing Hand class methods (`markStood()`, `markDoubled()`, `markSurrendered()`)
- ✅ **Improved**: Error handling and debugging capabilities
- 🔒 **Enhanced**: Nginx configuration support and compatibility
- 📚 **Updated**: Documentation and installation instructions

**v0.3.0** - Previous stable release
- ✨ **Added**: Multiple dealing styles support (American, European, Macau)
- 📊 **Added**: Comprehensive game statistics tracking
- ⚙️ **Added**: Flexible rule configuration system
- 👤 **Added**: User profile management with authentication
- 🎮 **Added**: Session tracking and state management
- 🎯 **Added**: Split, double, insurance, and surrender options
- 🔄 **Added**: Customizable shuffling methods and deck penetration