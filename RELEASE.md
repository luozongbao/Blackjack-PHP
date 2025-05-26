# Blackjack PHP v1.1.1 Release Notes

**Release Date: May 26, 2025**

We're pleased to announce Blackjack PHP v1.1.1, featuring a comprehensive revision of the dashboard statistics system that provides clearer financial tracking and improved user experience with intuitive metrics.

## What's New in v1.1.1

### 📊 Revised Dashboard Statistics System
- **Clear Financial Metrics**: Complete overhaul of how winnings and losses are displayed
- **Separated Tracking**: Distinct tracking of positive winnings vs actual losses
- **Intuitive Color Coding**: Green for positive values, red for negative values
- **Enhanced Clarity**: Eliminated confusion between total payouts and actual winnings

### 💰 New Statistics Structure
- **Current Balance**: Player's current available money (blue/primary)
- **Total Won**: Sum of actual positive winnings only (always positive, green)
- **Total Loss**: Sum of actual losses only (always negative, red)  
- **Net**: Total Won + Total Loss (green if positive, red if negative)
- **Total Bet**: Sum of all bets placed across all games
- **ROI**: Return on Investment = (Net / Total Bet) × 100%

### 🎯 Technical Enhancements
- **Real-time Updates**: Statistics update immediately during gameplay
- **Improved Calculations**: More accurate separation of wins vs losses
- **Consistent Display**: Both current session and all-time statistics use same structure
- **Enhanced JavaScript**: Real-time calculation and display updates

### 📈 User Experience Improvements
- **Clearer Financial Picture**: Users can immediately understand their performance
- **Percentage-based ROI**: Better understanding of investment returns
- **Intuitive Design**: Color coding makes positive/negative values immediately clear
- **Consistent Interface**: Same metrics shown in lobby and game interface

## Previous Release: v1.1.0

## Previous Release: v1.1.0

## What's New in v1.1.0

### 🚫 Advanced All-Busted Logic
- **Intelligent Dealer Behavior**: Implemented sophisticated logic to handle scenarios where all player hands are busted
- **Deal Style Specific Handling**: Different behaviors based on the selected dealing style:
  - **🇺🇸 American Style**: Dealer reveals hole card but doesn't draw additional cards
  - **🇪🇺 European/Macau Style**: Dealer doesn't draw any additional cards
- **Performance Optimization**: Eliminates unnecessary dealer card draws when game outcome is predetermined
- **Early Game Termination**: Game ends immediately when all player hands are busted, improving user experience

### 🔧 Technical Enhancements
- **New Helper Method**: Added `areAllPlayerHandsBusted()` for comprehensive hand state checking
- **Enhanced Game Flow**: Improved `playDealerHand()` method with intelligent decision-making
- **Comprehensive Testing**: Added thorough test suite for all-busted scenarios
- **Mixed Scenario Support**: Properly handles combinations of busted and surrendered hands

### 📚 Documentation Updates
- **Testing Guide**: Updated TESTING_GUIDE.md with all-busted logic verification instructions
- **Implementation Verification**: Added automated tests to verify proper implementation
- **Code Documentation**: Enhanced inline documentation for better maintainability

## Previous Release: v1.0.0

## What's New in v1.0.0

### 🎵 Immersive Audio System
- **Game Action Sounds**: Added distinct sound effects for all game actions:
  - Deal, hit, stand, double, split, shuffle, and chip placement sounds
- **Result Sounds**: Implemented unique sounds for different game outcomes:
  - Win, lose, push, and special blackjack sounds
- **Background Music**: Added toggleable ambient casino music
- **Audio Controls**: Implemented intuitive sound controls in the bottom-right corner:
  - Sound effect toggle with visual feedback
  - Background music toggle with visual feedback
  - Settings persist between sessions using localStorage

### 📊 Statistics System Improvements
- **All-time Stats**: Finalized implementation of all-time statistics tracking
- **Stats Reset**: Fixed and improved the stats reset functionality in settings
- **Data Accumulation**: Enhanced logic for proper accumulation of statistics across sessions

### 🎮 User Experience Enhancements
- **Sound Controls UI**: Sleek, non-intrusive controls with hover effects
- **Responsive Design**: Improved mobile responsiveness across all game elements
- **Visual Feedback**: Enhanced feedback for game actions and results

### 🐳 Docker Compose Support
- **Containerization**: Added full Docker Compose support for easy deployment
- **Multi-container Architecture**: Nginx, PHP-FPM, and MariaDB services preconfigured
- **Environment Variables**: Simple configuration via environment variables
- **Volume Persistence**: Data and logs persist between container restarts
- **Zero-configuration Setup**: Ready to run with minimal setup requirements

## Complete Feature Set

- ♠️ Multiple dealing styles (American, European, Macau)
- 🎲 Configurable number of decks (1-8)
- 🔄 Flexible shuffling methods with customizable deck penetration
- ⚙️ Complete rule customization
- 💰 Comprehensive betting options (Split, Double, Insurance, Surrender)
- 📈 Real-time game statistics and history
- 🎵 Full audio system with game action sounds and music
- 👤 User authentication and profile management
- 💾 Session management with restart capability
- 🔒 Secure implementation with modern web best practices
- 🐳 Docker Compose support for easy deployment

## Technical Implementations

- **Sound System**: Implemented using the Web Audio API with preloading for responsive playback
- **Persistent Settings**: Sound preferences stored in browser localStorage
- **Responsive Design**: Enhanced for all screen sizes and device types
- **Performance Optimizations**: Improved loading times and game responsiveness
- **Docker Integration**: Optimized container configuration for production use

## Installation and Upgrade Notes

### Fresh Installation
Follow the standard installation procedure outlined in the README.md file.

For Docker-based installations:
1. Clone the repository
2. Create a `.env` file with your database credentials
3. Run `docker-compose up -d`
4. Visit `http://localhost/includes/install.php` to complete setup

### Upgrading from v0.3.x
1. Back up your database
2. Replace all application files with the new version
3. No database schema changes are required for this update
4. Clear your browser cache after upgrading

## Known Issues
- Sound playback may be delayed on first interaction due to browser autoplay restrictions
- Background music may not automatically start until user interaction in some browsers

## What's Next
We're now working on:
- Additional card themes and table backgrounds
- Extended statistics and reporting capabilities
- Advanced betting strategies implementation
- Multiplayer functionality
- Advanced Docker configurations for scaling

## Acknowledgments
Special thanks to all contributors who have helped make this release possible.

---

For any issues, please open a ticket in our GitHub repository or contact support.
