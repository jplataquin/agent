<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Styling Rules
- **Dark Theme Mode Compatibility**: Do not use hardcoded `bg-white` classes in HTML/Blade views, as they break contrast in dark mode. Instead, use contextual classes, theme-aware Bootstrap classes (e.g., `bg-body`, `bg-body-secondary`), or rely on default component styling (e.g., standard `.card` or `.card-header` backgrounds).

## Git Workflow & Shipping to Main
When instructed to merge and push changes to `main`:

1. **Commit Changes**:
   Stage only the specific files changed and commit with a concise, descriptive message:
   ```bash
   git add <file1> <file2>
   git commit -m "<Descriptive message>"
   ```

2. **Merge into Main**:
   If working within a git worktree, merge the worktree branch into `main` in the primary workspace:
   ```bash
   git -C /mnt/c/Users/jplataquin/OneDrive/Desktop/dev/ai/agent merge <branch-name>
   ```

3. **Push to Remote (`origin/main`)**:
   In non-interactive CLI environments where terminal prompts are disabled, GUI credential managers may hang or fail. Always explicitly pass `-c credential.helper=store` to authenticate using stored credentials:
   ```bash
   # From main repository:
   git -C /mnt/c/Users/jplataquin/OneDrive/Desktop/dev/ai/agent -c credential.helper=store push origin main

   # Or directly push current worktree HEAD to remote main:
   git -c credential.helper=store push origin HEAD:main
   ```


