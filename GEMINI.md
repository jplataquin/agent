# Project Instructions & Guidelines

## Parallel Agent Coordination
You are operating in a multi-agent environment where multiple Gemini sessions run in parallel git worktrees created via `gemini -w <name>`. To prevent merge conflicts and optimize work across concurrent agents, follow this coordination and shipping protocol:

1. **Self-Identification & Setup**:
   - Determine your agent ID from your workspace directory name (e.g., if working in `.gemini/worktrees/task-1`, your ID is `task-1`).
   - Automatically ensure the shared state folder is symlinked by running:
     `mkdir -p ../../.agent-states && ln -sfn ../../.agent-states .agent-states`
   - Your dedicated state file is `.agent-states/<your_agent_id>.md`.

2. **Heartbeat & Broadcasting**:
   - As soon as you define your plan or change intent, write/update your state file with:
     ```markdown
     # Agent: <your_agent_id>
     - **Status**: Active
     - **Updated At**: YYYY-MM-DD HH:MM UTC
     - **Current Intent / Task**: <brief description of what you are doing>
     - **Components Modified**:
       - <file or component 1>
       - <file or component 2>
     ```

3. **Dynamic Conflict Checking**:
   - Before executing code edits (`replace`, `write_file`, etc.), inspect all other files in `.agent-states/`.
   - Ignore your own state file.
   - Ignore state files with `Status: Idle` or with an `Updated At` older than 30 minutes (stale).
   - If an active agent lists overlapping files or components in `Components Modified`, adjust your plan, wait, or ask the user before proceeding.

4. **Automated Completion & Shipping Workflow (Ship to Main)**:
   Once your task is completed and verified, automatically ship your changes to `main`:
   a. **Descriptive Commit**:
      - Stage changed files: `git add <files>` (avoid committing untracked environment or temporary files).
      - Commit with a clear, descriptive message: `git commit -m "<type>(<scope>): <concise description of work>"`.
   b. **Sync with Main**:
      - Fetch latest remote changes: `git fetch origin main`
      - Merge main into your branch: `git merge origin/main`
   c. **Reconcile Conflicts (if any)**:
      - If conflict markers exist (`<<<<<<<`, `=======`, `>>>>>>>`), inspect and intelligently merge the code so neither your changes nor incoming changes are lost.
      - Perform syntax checks on touched files (e.g., `php -l <file>`).
      - Stage and commit the merge: `git commit -m "chore: merge origin/main and resolve conflicts"`
   d. **Direct Push to Main**:
      - Push your branch directly to the remote main branch: `git -c credential.helper=store push origin HEAD:main`
   e. **Clean State**:
      - Mark your state file `.agent-states/<your_agent_id>.md` as `Status: Idle` or remove it.
      - Announce: `🚀 Task successfully combined and shipped directly to main!`

## Testing Guidelines
- **Targeted Testing**: Do not run the entire application test suite (e.g., `php artisan test`) for small code changes. Instead, use targeted testing (e.g., `php artisan test --filter=TestClassName` or specifying the file path) to run only the tests relevant to the code you added or modified. This saves time and resources.

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
