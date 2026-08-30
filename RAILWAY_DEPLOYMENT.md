# Railway Deployment Guide for NexGen

## Current Configuration

Your `config.php` is already configured to read database credentials from environment variables:

- `NEXGEN_DB_HOST` (defaults to `localhost`)
- `NEXGEN_DB_NAME` (defaults to `nexgen_db`)
- `NEXGEN_DB_USER` (defaults to `root`)
- `NEXGEN_DB_PASSWORD` (no default, required)

## Railway Setup Steps

### Step 1: Push Configuration Files to GitHub

First, commit and push the new Railway configuration files:
- `railway.json` - Railway service configuration
- `nixpacks.toml` - Build configuration for PHP + Node.js
- `Procfile` - Process file configuration
- Updated `package.json` - Added "start" script

```bash
git add railway.json nixpacks.toml Procfile package.json
git commit -m "Add Railway deployment configuration for PHP with Node.js build"
git push origin main
```

### Step 2: Add MySQL Database to Railway

1. Go to your Railway project dashboard: https://railway.app
2. In your project, click **"+ Create"** → Select **"Database"** → **"MySQL"**
3. Wait for MySQL to be provisioned (takes ~1-2 minutes)
4. Click on the MySQL service to view connection details

### Step 3: Set Environment Variables in Railway

In your Railway project dashboard:

1. Click on your **web service** (the PHP app deployment)
2. Go to **"Variables"** tab
3. Add the following environment variables from your MySQL connection:

```
NEXGEN_DB_HOST=      (copy from Railway MySQL)
NEXGEN_DB_USER=      (copy from Railway MySQL, usually "root")
NEXGEN_DB_PASSWORD=  (copy from Railway MySQL)
NEXGEN_DB_NAME=nexgen_db
PORT=8080
```

**To get Railway MySQL credentials:**
- Click on the MySQL service in your Railway project
- Go to **"Connect"** tab
- Copy the host, username, and password from the connection string

Example Railway MySQL connection string:
```
mysql://root:password@mysql.railway.internal:3306/railway
```

Extract:
- Host: `mysql.railway.internal`
- User: `root`
- Password: `password`
- Database: Keep as `nexgen_db` (will be created)

### Step 4: Upload Database Schema

After MySQL is running in Railway, you need to import the database schema:

**Option A: From Railway Dashboard**
1. In Railway MySQL service, go to **"Connect"** tab
2. Use a tool like MySQL Workbench or phpMyAdmin
3. Connect using the provided credentials
4. Import `CODE/BACKEND/nexgen_db.sql`

**Option B: Using Command Line** (if you have MySQL client installed)
```bash
mysql -h <railway-host> -u <user> -p<password> < CODE/BACKEND/nexgen_db.sql
```

### Step 5: Trigger Deployment

1. Your Railway project should auto-deploy when you pushed the config files
2. If not, manually trigger a redeploy:
   - Click **"Deploy"** button in Railway dashboard
   - Or push a new commit to GitHub

### Step 6: Verify Deployment

1. In Railway, copy your web service **"Public URL"**
2. Visit `https://your-railway-url.up.railway.app/` in your browser
3. You should see the NexGen login page (if login.php/index.php is your entry point)
4. Check the browser console for any errors

## Troubleshooting

### "502 Bad Gateway" or "503 Service Unavailable"
- Check Railway build logs for PHP/Node.js errors
- Verify all environment variables are set correctly
- Ensure MySQL database is running and accessible

### "Connection refused" or "Cannot connect to database"
- Verify `NEXGEN_DB_HOST`, `NEXGEN_DB_USER`, `NEXGEN_DB_PASSWORD` are correct
- Make sure MySQL service is running in Railway
- Check that the database `nexgen_db` exists

### "File not found" errors
- The PHP app is served from the `CODE/PHP` directory (specified in `php -S ... -t CODE/PHP`)
- Make sure your entry file is `CODE/PHP/index.php` or similar

### Build fails with "No start command detected"
- This is fixed by the new `package.json` "start" script and `Procfile`
- Redeploy after pushing the new files

## Environment Variables Checklist

Before deployment, ensure these are set in Railway:

- [ ] `NEXGEN_DB_HOST` = Your Railway MySQL host
- [ ] `NEXGEN_DB_USER` = Your Railway MySQL user (usually `root`)
- [ ] `NEXGEN_DB_PASSWORD` = Your Railway MySQL password
- [ ] `NEXGEN_DB_NAME` = `nexgen_db`
- [ ] `PORT` = `8080` (or Railway's default)

## What Was Changed

**New files for Railway deployment:**
- `railway.json` - Service configuration
- `nixpacks.toml` - Multi-provider build config (PHP + Node.js)
- `Procfile` - Process startup command

**Updated:**
- `package.json` - Added "start" script to run PHP server

**What it does:**
1. Installs Node.js dependencies (Bootstrap via npm)
2. Starts a PHP built-in server on port 8080
3. Serves PHP files from the `CODE/PHP` directory
4. Reads database credentials from environment variables

## Notes

- PHP built-in server is suitable for small-to-medium traffic
- For production at scale, consider using Railway's Nginx/PHP option
- Make sure MySQL backups are configured in Railway
- Review security settings and firewall rules in Railway
