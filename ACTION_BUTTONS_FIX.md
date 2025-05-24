# Action Buttons Fix - Manual Test Instructions

## Summary of Changes Made

### 1. Session Consistency Fix
- **Problem**: JavaScript was calling `./api/game_api.php` then reloading `game.php`, causing session inconsistency
- **Fix**: Changed JavaScript to call `game.php` directly for all actions

### 2. JavaScript Path Fix  
- **Problem**: Footer was loading `../assets/js/game.js` (wrong path)
- **Fix**: Changed to `assets/js/game.js` (correct path from root)

### 3. Duplicate Event Handler Fix
- **Problem**: Two different bet form handlers were conflicting
- **Fix**: Removed old debug handler, now only uses BlackjackUI class

### 4. AJAX Parameter Fix
- **Problem**: Inconsistent AJAX handling
- **Fix**: All game actions now include `ajax=1` parameter

## Manual Test Steps

1. **Open Browser**: Navigate to http://bj.home/game.php

2. **Check Initial State**: 
   - Should see betting form with "Deal Cards" button
   - Current money should be displayed
   - No action buttons should be visible

3. **Place Bet**:
   - Enter bet amount (e.g., 100)
   - Click "Deal Cards" 
   - Should see success message
   - Page should reload after 1 second

4. **Verify After Reload**:
   - Money should be deducted (e.g., 10,000 → 9,900)
   - Cards should be displayed for both player and dealer
   - **ACTION BUTTONS SHOULD NOW APPEAR**: Hit, Stand, Double, etc.
   - Current Game Bet should show $100.00

## Expected Behavior

### ✅ Success Indicators:
- Money is properly deducted when bet is placed
- Cards are dealt and visible  
- Action buttons (Hit, Stand, Double, Split, Surrender) appear
- Game state shows as "player_turn"
- All actions work without 404 errors

### ❌ Failure Indicators:
- Money not deducted
- No action buttons appear
- 404 errors in browser console
- Cards not visible or too faded

## Technical Details

The fix ensures that:
1. Game state is saved to database by `game.php` 
2. Session persists through the same endpoint
3. Game state is properly restored after page reload
4. All action flags (canHit, canStand, etc.) are correctly calculated

This should resolve the remaining issue where action buttons were not appearing after cards were dealt.
