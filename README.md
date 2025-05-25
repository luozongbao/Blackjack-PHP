# Blackjack PHP v0.3.1

A sophisticated web-based Blackjack game implementation using PHP, MySQL, and JavaScript with full session management and comprehensive statistics tracking.

## Features

### Game Features
- Multiple dealing styles (American, European, Macau)
- Configurable number of decks (1-8)
- Flexible shuffling methods (Auto-shuffle or Shoe with customizable deck penetration)
- Complete rule customization
- Comprehensive betting options (Split, Double, Insurance, Surrender)
- Real-time game statistics

### Customizable Game Rules
- Dealer behavior (Hit on Soft 17 / Stand on All 17s)
- Blackjack payout options (3:2 or 1:1)
- Surrender options (Early, Late, or None)
- Double down rules (Any Two Cards or 9-10-11 only)
- Split limits (1-4 splits)
- Insurance betting
- Double after split

### Player Features
- User authentication and profile management
- Comprehensive session statistics
- All-time statistics tracking
- Customizable initial bankroll
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

## Installation

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

### Session Management
- **Initial bankroll**: Customizable starting amount
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

**v0.3.1** - Latest stable release (May 25, 2025)
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