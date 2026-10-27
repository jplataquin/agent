# Project Instructions & Guidelines

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
