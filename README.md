# Blackjack PHP v0.3.0

A sophisticated web-based Blackjack game implementation using PHP, MySQL, and JavaScript.

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
- Secure user authentication
- Session-based gameplay
- Mobile-responsive design
- Real-time statistics updates
- Database-backed persistence
- CSRF protection
- XSS prevention

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or MariaDB 10.2 or higher
- Web server (Apache/Nginx)
- Modern web browser with JavaScript enabled

## Installation

1. Clone the repository to your web server directory
2. Create a MySQL database with grant privilieges to a user
3. Configure database connection run `http://[url]/includes/install.php`
4. Ensure proper file permissions
5. Access the application through your web browser

## Configuration

Game settings can be configured through the Settings page after logging in:
- Deck configuration (number of decks, shuffling method)
- Deal style (American/European/Macau)
- Game rules (dealer behavior, payout ratios, etc.)
- Betting limits and initial bankroll

## Security

- Passwords are hashed using secure algorithms
- Prepared statements for database queries
- Input validation on all forms
- Session security measures
- CSRF token protection

## License

vibe coding © 2025 Blackjack PHP. All rights reserved.

## Version History

v0.3.0 - Current stable release
- Multiple dealing styles support
- Comprehensive game statistics
- Flexible rule configuration
- User profile management
- Session tracking