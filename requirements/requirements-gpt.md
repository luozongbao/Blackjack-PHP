# Black Projects: PHP BlackJack Game

## Overview
Develop a BlackJack game using PHP and MySQL, with persistent user profiles, comprehensive statistics tracking, and customizable game settings.

---

## 1. Database Requirements

- **MySQL** will store user profiles and all statistics.

---

## 2. User Profile Structure

Each user should have the following information:

### Basic Information
- **Display Name** (editable)
- **Username** (not editable)
- **Password** (hashed)
- **Email** (editable)

### Statistics

#### a. Session Stats (Resets on session restart)
- **Money**
    - Current Money
    - Total Loss
    - Total Won
    - Total Bet
    - ROI = (Total Won - Total Loss) / Total Bet
- **Games**
    - Games Played
    - Games Won
    - Games Push
    - Games Lost
    - Winning per Game = (Total Won - Total Loss) / Games Played

#### b. All-Time Stats (Resets only on user request)
- **Money**
    - Total Loss
    - Total Won
    - Total Bet
    - ROI = (Total Won - Total Loss) / Total Bet
- **Games**
    - Games Played
    - Games Won
    - Games Push
    - Games Lost
    - Winning per Game = (Total Won - Total Loss) / Games Played

---

## 3. Forms & Pages

### All Forms
- Header with navigation:  
  - Game Table
  - Settings
  - Profile
  - Logout

### Profile Page
- Display Name [Editable]
- Username [Read-only]
- Change Password Section
- Email [Editable]
- Session Stats Display
- All-Time Stats Display

### Login Page
- Username
- Password
- Sign Up link
- Forgot Password link

### Settings Page
- **Deck Configuration**
    - Number of decks per shoe (integer)
    - Shuffling Method:
        - Auto-shuffle machine (shuffle after every game)
        - Shuffle every shoe (shuffles after X% of cards used; Deck Penetration, default 80%)
- **Deal Style** (radio/buttons, default American)
    - American: Dealer checks for Blackjack after two cards if face-up is 10 or A
    - European: Dealer gets one card, all doubles/splits lost if dealer has blackjack
    - Macau: Dealer gets one card, only original bet lost if dealer has blackjack
- **Rules**
    - Dealer draws to: Any 17 / Hard 17
    - Blackjack Pay: 3:2 / 1:1
    - Allow Surrender: Early / Late / Not Allowed
    - Allow Double after Split: Yes / No
    - Allow Insurance: Yes / No
    - Allow Double: On Any Two Cards / On 9, 10, 11 only
    - Number of Splits Allowed: 1–4 (default 3)
- **Restart Section**
    - Initial Money (default: $10,000)
    - “Restart Session” button (resets session stats and money)
    - “Reset All-Time Stats” button (confirmation required)

### Game Table Page

- **Restrictions:**  
    - If a game is in progress (cards dealt), user cannot leave the page.

- **Layout:**
    - Top: Session Current Money
    - Dealer Section (top half): Display dealer cards
    - Player Section (bottom half): Display player cards

- **Action Section:**
    - **Before game starts:** Bet amount input and Bet button.
    - **During game:**  
        - If player has blackjack, and dealer shows Ace, show: Insurance / No Insurance buttons (if insurance allowed).
        - After two cards:
            - If hand has two cards of same value: Show Split (if allowed), Hit, Stand, Surrender (if allowed), Double (if allowed by rule)
            - Double only appears if allowed by settings and hand value matches criteria
        - After more than two cards: Show Hit, Stand, Surrender (late, if allowed)
        - Split only if split limit not reached and sufficient funds.
        - Double only if sufficient funds.

- **Actions:**
    - **No Insurance:** Continue to next action.
    - **Insurance:** Place half bet as insurance; continue.
    - **Hit:** Deal one card, continue.
    - **Split:** Split hand, duplicate bet (if funds), deal to each, continue.
    - **Stand:** End turn for this hand; dealer acts or next hand.
    - **Double:** Double bet (if funds), deal one card, automatically stand.
    - **Surrender:** Lose half bet, return half to player.

---

## 4. Gameplay Rules

### Card Values
- A = 1 or 11
- 2–10 = face value
- J, Q, K = 10

### Deck
- 4 suits (Spades, Hearts, Diamonds, Clubs)
- Number of decks per settings; all cards placed in shoe.

### Hand Value
- **Soft Value:** Any hand with Ace counted as 11 (if not busted)
- **Hard Value:** Hand with no Ace, or Ace(s) counted as 1

### Game Start
- If using auto-shuffle: shuffle before each game
- If using shuffle-per-shoe: shuffle when deck penetration exceeds threshold

### Dealing Styles
- **American:** Deal player, dealer (up), player, dealer (down)
    - If dealer up card is 10/A, check for blackjack
    - If dealer has blackjack and player does not, player loses
    - If both have blackjack, push
- **European/Macau:** Deal per rules above

### Player Actions
- Player acts first; can hit until bust or stand. If split, play each hand in turn.

### Dealer Actions
- Dealer acts per “Dealer Draw to” rule:
    - “Any 17”: stand on soft/hard 17+
    - “Hard 17”: hit on soft 17, stand on hard 17+
- Dealer busts if over 21

---

## 5. Game End & Decisions

- Compare dealer and player hands for win/loss/push
- Update stats accordingly
- Allow new game

---

## 6. Additional Features (optional, stretch goals)
- Leaderboards (based on ROI or total winnings)
- Basic card counting stats/tracking
- Responsible gaming reminders

---

## 7. Security

- Passwords stored as hashes (never plaintext)
- Sessions managed securely (prevent hijacking)
- Input validation everywhere (prevent SQL injection/XSS/etc)