# Dropbox Token Auto-Refresh Setup

## Summary
Your Dropbox integration now automatically refreshes access tokens - no more manual updates every 4 hours!

## What Changed

### 1. Updated `src/Service/DropboxService.php`
- Added automatic token refresh using your refresh token
- Tokens are cached with expiry tracking
- When a token expires, it automatically gets a new one from Dropbox
- Added methods:
  - `getValidAccessToken()` - checks and refreshes tokens as needed
  - `refreshAccessToken()` - calls Dropbox API to get new tokens
  - `getTokenFromCache()` - retrieves cached tokens
  - `saveTokenToCache()` - stores tokens with expiry time

### 2. Updated `config/services.yaml`
- Now passes all required credentials to DropboxService:
  - Access token (short-lived, 4 hours)
  - Refresh token (long-lived, used to get new access tokens)
  - App key and secret

## How It Works

1. **On first use**: Your current access token is cached with a 4-hour expiry
2. **Before expiry**: The cached token is used for all API calls
3. **When expired**: The service automatically:
   - Uses your refresh token to get a new access token from Dropbox
   - Caches the new token with expiry time
   - Continues working seamlessly

## Token Cache Location
`/tmp/dropbox_token_cache.json`

This file contains:
- Current access token
- Expiry timestamp
- Last refresh time

## Environment Variables Used
```env
DROPBOX_ACCESS_TOKEN=...      # Initial token (can expire)
DROPBOX_REFRESH_TOKEN=...     # Never expires, gets new access tokens
DROPBOX_APP_KEY=...           # Your app credentials
DROPBOX_APP_SECRET=...        # Your app secret
```

## Testing
Run `php test_auto_refresh.php` to verify the setup.

## Benefits
✅ No more manual token updates
✅ Automatic renewal every 4 hours
✅ Seamless operation - your site won't break
✅ Tokens cached for performance
✅ 5-minute buffer before expiry for safety

## Logs
Token refresh events are logged with `error_log()` for debugging.
Check your PHP error log for messages like:
- "Dropbox: Using cached access token"
- "Dropbox: Refreshing access token..."
- "Dropbox: Successfully refreshed access token"
