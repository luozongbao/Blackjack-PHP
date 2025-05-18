# Black Projects 
    I want to create a blackjack game on PHP Mariadb javascript
## Requirements
- using MYSQL to keep record of user profile, and stats
- User Profile 
    - Display Name
    - username
    - password
    - email
    - Session Stats
        - money
            - Current Money
            - total loss 
            - total won
            - total bet
            - ROI = (total won - total loss)/total bet
        - games
            - games played
            - games won
            - games push
            - games loss
            - winning per game = (total won - total loss)/games played
    - All time Stats
        - money
            - total loss 
            - total won
            - total bet
            - ROI = (total won - total loss)/total bet
        - games
            - games played
            - games won
            - games push
            - games loss
            - winning per game = (total won - total loss)/games played
## Forms
- All forms have a header:
    Navigation:
        - Game Table 
        - Settings
        - Profile
        - Logout
- Installation Page
- Profile page
    - Display Name [Editable]
    - UserName [Not Editable]
    - Change Password section
    - Email [Editable]
    - Session Stats Display Section
    - All time Stats Display Section
- login page 
    - username
    - passowrd
    - signup new user
    - forget password
- Sign up form
- Forget Password Form
- Reset Password Form
- Lobby Page - use as a landing page after login and showing stats and menu
- settings Page
    - number of deck per shoe
    - shuffleing method
        - autoshuffle machine (Shuffle very game end)
        - shuffle every shoe ()
            - able to set percentage of card play per shoe call "Deck Penetration" (Default: 80%)
    - Deal Style 
        Options:
        - American (Default): Dealt two cards (Dealer Check for Blackjack after dealt 2 cards and faceup card is showing 10)
        - European: Dealt one card, lose all double and splits money if dealer has blackjack 
        - Macau: Dealt one card, lose only original bet
    - Rules
        - Dealer draw to: ['Any 17', 'Hard 17']
        - Backjack Pay: ['3:2', '1:1']
        - Allow Surrender: [Allow Early Surrender, Allow Late Surrender, Not Allow Surrender]
        - Allow Double after Split: [Yes, No]
        - Allow Insurance: [Yes, No]
        - Allow Double: ['On Any Two Cards', 'On Hand Value 9, 10, 11']
        - Number of Splits Allow: [1, 2, 3 (default), 4]
    - Restart Section 
        - Initial Money: [Default:10000$]
        - Restart Session Button (Onclik: resets all session stats to 0 and money to initial money)
    - Reset All Time Status Buttons (Onclick: alert for confirmation, on confirm reset All Time Stats)
- Game Table If game started (Cards Dealt) can not leave the page
    - On top Show Session currenty Money
    - On top half considerd as Dealer section: Showing card delt for the dealer
    - On Bottom Half considered as Player Section: Showing Card Delt for the Player
    - Action Section Consisting of action buttons for the Player to Take Action
        - Action Before game start, Shows (Bet Amount box, and Bet Button)
        - Action After Game Start:
            - Actions after Player has a blackjack:
                if dealer has A then If Insurance allowed then shows: Insurance Button, No Insurance Button.
            - After Player 2 Cards Dealt
                - If the hand has two cards of the same value (Don't care K, and Q just value of 10s or any same 2 value cards) Shows: Hit Button, (If not exceed split amount show) Split Button, Stand Button, (if allowed early surrender Show) Surrender button
                - If Double Setting is Allow 'On Any two cards' then also show: Double button, Hit Button, (If not exceed split amount show) Split Button, Stand Button, (if allowed surrender Show) Surrender button
                - If Double Setting is Allow 'On Hand Value 9, 10, 11' then if hand value is 9, 10, 11 show Double Button, Hit Button, (If not exceed split amount show) Split Button, Stand Button, (if allowed early surrender Show) Surrender button
                - Others, Show Hit Button, (If not exceed split amount show) Split Button, Stand Button, (if allowed Early surrender Show) Surrender button
            - More than 2 Card Dealt
                Hit Button, Stand Button, (if allowed late surrender Show) Surrender button
    - Actions Buttons:
        - No Insurance: Player take next action.
        - Insurance: place half the bet to the table as insurance, then wait for player action 
        - Hit: Deal one more card to the player hand and wait for next palyer action
        - Split: separate the hand into two hands and also put the same bet amount to the other hand (deduct from the Session Current Money. If not enough disable split button) and deal another card to the first hand and wait for next player action
        - Stand:
            - if it is only hand then Dealer take actions
            - if it has next hand then move to next hand action and deal a card to that hand and wait for next player action
        - Double: add the same bet amount to the bet, so it become 2 times original bet (deduct from the Session Current Money. If not enough, disable Double Button), then deal one card to the hand and automatically stand
        - Surrender: Lose half the bet, return half bet amount to the Session Current Money
# Game play 
- Card Value
    A = 1 or 11
    2 = 2
    3 = 3
    4 = 4
    5 = 5
    6 = 6
    7 = 7
    8 = 8
    9 = 9
    10 = 10
    J = 10
    Q = 10
    K = 10
- On Deck has 4 sets of cards (Spades, Hearts, Diamonds, Clubs)
- generate number of decks cards as the settings and put in the shoe
- Counting Score
    - if in a hand (Dealer or Player) total of all cards value consider as a hand value. if it has A in the hand, A can be counted as 11 called Soft value, or 1 called Hard Value.
        - Soft Hand Value: Total score of a hand that has an A and any A count as 11
        - Hard Hand Value: Total score of a hand that has no A, or All A is count as 1
- Before Deal Card
    - if setting is Autoshuffle Machine then shuffle before deal any cards.
    - if setting is Shuffle every show then check if the shoe penetration exceeds the percentage.  If yes, shuffle before deal any cards.
- dealer deal as deal style:
    - American: deal card to player, then dealer (face up), then player, then dealer (face down)
        - if face up card has a value of 10 dealer check the facedown card if its and 'A' if it was a Blackjack, and player doesn't have blackjack then player loose the bet and restart to next game.  If both dealer and player has a blackjack then there is a push then restart to next game.
    - European: deal card to player, then dealer (face up), then player
    - American: deal card to player, then dealer (face up), then player, then dealer (face down)
- Game Flows
    - If dealer faceup card is A, 
        - if Insurance Allowed, 
    - Player plays first, player is allowed to hit as many times as he wants. if on hand hard value exceeds 21, that player hand loses the bet.
        - if there is the next hand move to next hand
        - if there is only one hand then game end
    - After all hands stand, then Dealer Turns
    - Dealer Turns, 
        - On any deal style, if the game
        - dealer take one card to its hand if
            - Dealer Draw to Settings is 'Any 17', then the dealer hand can hit til dealer soft hand value or hard hand value has 17 or more.
            - Dealer Draw to Settings is 'Hard 17', then the dealer hand can hit til dealer hard hand value has 17 or more. (Hit on Soft 17) But dealer hand must stand on all soft and hard hand value of 18 and above.
            - If Dealer hand exceed 21 game stop then move to Decision Phase
    - Decision
        - If any hand that have more value than Dealer hand score, than the dealer value then that hand wins, system pay that hand same amount of bet size and add up to the Session Currenty Money
- After game ends
    - if total in the gameplay, player won money more than losing money in that game, count as won
    - if total in the gameplay, player won money less than losing money in that game, count as lose
    



        
