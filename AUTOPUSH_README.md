# MicroFin Autopush Documentation

## Overview
The autopush system automatically commits and pushes changes to both GitHub repositories:
- **Origin**: https://github.com/Raaabbb/MicroFinWeb.git
- **Backup**: https://github.com/Raaabbb/MicroFinWebBackup.git

## Quick Start

### Method 1: Using Batch File (Easiest)
```
Double-click: start_autopush.bat
```

### Method 2: Using PowerShell
```powershell
powershell -ExecutionPolicy Bypass -File autopush.ps1
```

### Method 3: Run from Terminal
```powershell
cd C:\xampp\htdocs\admin-draft
.\autopush.ps1
```

## Features
- Monitors file changes every 5 seconds
- Automatically stages and commits changes
- Pushes to both `origin` and `backup` remotes
- Timestamped commit messages
- Includes file change summary in commits
- Color-coded console output for easy monitoring

## Current Setup
- **Project Root**: `C:\xampp\htdocs\admin-draft`
- **Watch Interval**: 5 seconds
- **Remotes Configured**:
  ```
  origin  -> https://github.com/Raaabbb/MicroFinWeb.git
  backup  -> https://github.com/Raaabbb/MicroFinWebBackup.git
  ```

## Backup Repository Secret Scanning
⚠️ **Important**: The backup repository has secret scanning protection enabled. If you see:
```
GITHUB PUSH PROTECTION - Push cannot contain secrets
```

This means database credentials from `db_connect.php` are being detected. To resolve:

1. Visit: https://github.com/Raaabbb/MicroFinWebBackup/security/secret-scanning/unblock-secret/3BAtAkuLIkwPmJ0TMEyFB1jHdJA
2. Click **Allow** to whitelist the secret
3. The script will retry and succeed

## File Changes Applied
✅ **setup_website.php**
- Renamed: `preset1` → `template1`
- Renamed: `preset2` → `template2`
- Renamed: `preset3` → `template3`
- Fixed: Indentation issues in form fields
- Updated: CSS class names (`.template-preset*` → `.template-template*`)
- Updated: Form labels ("Preset 1" → "Template 1", etc.)

## Stopping the Script
- Press `Ctrl+C` in the terminal/batch window
- Or close the terminal window

## Troubleshooting

### Script Won't Start
Ensure PowerShell execution policy allows the script:
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

### Git Authentication Issues
If you're prompted for credentials, ensure:
- GitHub credentials are saved in Windows Credential Manager
- Or set up SSH keys for Git
- Or use GitHub Personal Access Tokens

### Slow Push Times
The script will work fine, just may take longer:
- First push after long idle sets up connection
- Large file changes take longer to compress
- Internet speed affects transfer time

## Verification

After running the script, verify both repos received your changes:

**Origin Repo**:
```
https://github.com/Raaabbb/MicroFinWeb/commits/main
```

**Backup Repo** (after secret is whitelisted):
```
https://github.com/Raaabbb/MicroFinWebBackup/commits/main
```
