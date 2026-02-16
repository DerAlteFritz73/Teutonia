# Dropbox Token Setup Guide

## The Problem

Dropbox stopped supporting long-lived access tokens on September 30, 2021. All access tokens now expire after 4 hours (14,400 seconds). This means you need to use **refresh tokens** to automatically get new access tokens.

## The Solution

Your application already has the refresh token logic implemented in `DropboxService.php`. You just need to configure all 4 required environment variables:

1. `DROPBOX_APP_KEY` - Your Dropbox app key
2. `DROPBOX_APP_SECRET` - Your Dropbox app secret
3. `DROPBOX_ACCESS_TOKEN` - Short-lived token (expires in 4 hours)
4. `DROPBOX_REFRESH_TOKEN` - Long-lived token (never expires)

## How to Get the Tokens

### Step 1: Run the Token Generator

```bash
php generate_dropbox_token.php
```

### Step 2: Follow the Prompts

The script will:
1. Ask for your App Key and App Secret (from https://www.dropbox.com/developers/apps)
2. Generate an authorization URL with `token_access_type=offline`
3. You'll authorize the app in your browser
4. Exchange the authorization code for BOTH access and refresh tokens
5. Display all 4 values to add to your `.env.local` file

### Step 3: Update .env.local

Add all 4 values to your `.env.local` file:

```env
# Dropbox App Credentials
DROPBOX_APP_KEY=your_app_key_here
DROPBOX_APP_SECRET=your_app_secret_here

# Dropbox Tokens
DROPBOX_ACCESS_TOKEN=your_access_token_here
DROPBOX_REFRESH_TOKEN=your_refresh_token_here
```

## How It Works

The `DropboxService` automatically:

1. **Uses cached tokens**: First checks if there's a valid cached access token
2. **Auto-refreshes**: When the access token expires, it automatically uses the refresh token to get a new one
3. **Retries on auth errors**: If an API call fails with authentication error, it refreshes and retries
4. **Caches tokens**: Saves tokens to `/tmp/dropbox_token_cache.json` to avoid unnecessary refreshes

## Important Notes

- ⚠️ The refresh token **never expires** - keep it safe!
- ✅ The access token expires after 4 hours - this is normal
- 🔄 The application automatically refreshes tokens when needed
- 🚫 Never commit tokens to git (they're in `.env.local` which is gitignored)
- 📝 Token refresh is logged in error_log for debugging

## Reference

For more details about Dropbox OAuth 2.0 flow:
https://peterbanigo.com/how-to-get-a-long-lived-access-token-refresh-token-for-dropbox/
