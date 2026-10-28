@echo off
setlocal EnableDelayedExpansion
title YBT Management Software - Interactive Git Push
cd /d "%~dp0"

echo ================================================================
echo       YBT Management Software - Git Workflow Assistant
echo ================================================================
echo.

:: 1. Verify Git installation
where git >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Git is not installed or not found in system PATH.
    echo Please install Git from https://git-scm.com/ and try again.
    goto :end
)

:: 2. Verify Git repository
git rev-parse --is-inside-work-tree >nul 2>&1 <nul
if %errorlevel% neq 0 (
    echo [ERROR] Current directory is not a Git repository.
    goto :end
)

:: 3. Detect current branch
set "CURRENT_BRANCH=main"
git branch --show-current > "%TEMP%\ybt_branch.tmp" 2>nul <nul
if exist "%TEMP%\ybt_branch.tmp" (
    set /p CURRENT_BRANCH=<"%TEMP%\ybt_branch.tmp"
    del "%TEMP%\ybt_branch.tmp" 2>nul
)
if "!CURRENT_BRANCH!"=="" set "CURRENT_BRANCH=main"

:: 4. Detect configured remotes
set "REMOTE_NAME=origin"
git remote > "%TEMP%\ybt_remote.tmp" 2>nul <nul
if exist "%TEMP%\ybt_remote.tmp" (
    set /p REMOTE_NAME=<"%TEMP%\ybt_remote.tmp"
    del "%TEMP%\ybt_remote.tmp" 2>nul
)
if "!REMOTE_NAME!"=="" set "REMOTE_NAME=origin"

set "REMOTE_URL="
git remote get-url !REMOTE_NAME! > "%TEMP%\ybt_url.tmp" 2>nul <nul
if exist "%TEMP%\ybt_url.tmp" (
    set /p REMOTE_URL=<"%TEMP%\ybt_url.tmp"
    del "%TEMP%\ybt_url.tmp" 2>nul
)

echo [Git Status Overview]
echo  - Repository Path: %~dp0
echo  - Current Branch:  !CURRENT_BRANCH!
echo  - Active Remote:   !REMOTE_NAME! (!REMOTE_URL!)
echo.

if "!REMOTE_URL!"=="" goto :prompt_remote
echo Remote is currently: !REMOTE_URL!
set "CHANGE_REMOTE=n"
set /p "CHANGE_REMOTE=Do you want to switch or update your remote URL? (y/N): "
if /i not "!CHANGE_REMOTE!"=="y" goto :skip_remote_change

set /p "NEW_URL=Enter new/fork remote URL: "
if not "!NEW_URL!"=="" (
    git remote set-url !REMOTE_NAME! "!NEW_URL!" <nul
    git remote get-url !REMOTE_NAME! > "%TEMP%\ybt_url.tmp" 2>nul <nul
    set /p REMOTE_URL=<"%TEMP%\ybt_url.tmp"
    del "%TEMP%\ybt_url.tmp" 2>nul
    echo [*] Updated remote to: !REMOTE_URL!
    echo.
)
goto :skip_remote_change

:prompt_remote
echo [NOTE] No remote URL configured for '!REMOTE_NAME!'.
set /p "NEW_URL=Enter your GitHub fork URL (e.g. https://github.com/username/repo.git): "
if not "!NEW_URL!"=="" (
    git remote add !REMOTE_NAME! "!NEW_URL!" 2>nul <nul || git remote set-url !REMOTE_NAME! "!NEW_URL!" <nul
    git remote get-url !REMOTE_NAME! > "%TEMP%\ybt_url.tmp" 2>nul <nul
    set /p REMOTE_URL=<"%TEMP%\ybt_url.tmp"
    del "%TEMP%\ybt_url.tmp" 2>nul
    echo [*] Remote configured: !REMOTE_URL!
    echo.
)

:skip_remote_change

:: 5. Inspect changed files
echo ----------------------------------------------------------------
echo Changed / Untracked Files:
echo ----------------------------------------------------------------
git status -s <nul
echo.

set "HAS_CHANGES="
git status --porcelain > "%TEMP%\ybt_status.tmp" 2>nul <nul
if exist "%TEMP%\ybt_status.tmp" (
    for /f "usebackq tokens=*" %%s in ("%TEMP%\ybt_status.tmp") do set "HAS_CHANGES=1"
    del "%TEMP%\ybt_status.tmp" 2>nul
)

if not defined HAS_CHANGES (
    echo [OK] Working tree is clean. No uncommitted modifications.
    echo.
    set "UNPUSHED="
    git rev-parse --verify !REMOTE_NAME!/!CURRENT_BRANCH! >nul 2>&1 <nul
    if !errorlevel! equ 0 (
        git log !REMOTE_NAME!/!CURRENT_BRANCH!..HEAD --oneline > "%TEMP%\ybt_unpushed.tmp" 2>nul <nul
    ) else (
        git log -n 5 --oneline > "%TEMP%\ybt_unpushed.tmp" 2>nul <nul
    )
    if exist "%TEMP%\ybt_unpushed.tmp" (
        for /f "usebackq tokens=*" %%c in ("%TEMP%\ybt_unpushed.tmp") do set "UNPUSHED=1"
        del "%TEMP%\ybt_unpushed.tmp" 2>nul
    )
    if defined UNPUSHED (
        echo Unpushed commits detected:
        git rev-parse --verify !REMOTE_NAME!/!CURRENT_BRANCH! >nul 2>&1 <nul
        if !errorlevel! equ 0 (
            git log !REMOTE_NAME!/!CURRENT_BRANCH!..HEAD --oneline <nul
        ) else (
            git log -n 5 --oneline <nul
        )
        echo.
        goto :confirm_push
    )
    echo All commits are up to date with remote !REMOTE_NAME!/!CURRENT_BRANCH!.
    goto :end
)

:: 6. Prompt for number of commits
echo How many commits do you want to create?
set "NUM_COMMITS=1"
set /p "NUM_COMMITS=Enter number of commits [Default 1]: "
if "!NUM_COMMITS!"=="" set "NUM_COMMITS=1"

:: Arithmetic validation (no pipe)
2>nul set /a "TEST_NUM=!NUM_COMMITS! * 1"
if !TEST_NUM! lss 1 set "TEST_NUM=1"
if !TEST_NUM! gtr 9 set "TEST_NUM=9"
set "NUM_COMMITS=!TEST_NUM!"

echo.
echo [*] Preparing to create !NUM_COMMITS! commit(s)...
echo.

set "CURRENT_COMMIT_IDX=1"

:commit_loop
if !CURRENT_COMMIT_IDX! gtr !NUM_COMMITS! goto :after_commits

echo ================================================================
echo Commit !CURRENT_COMMIT_IDX! of !NUM_COMMITS!
echo ================================================================

:: Check if there are any remaining changes left
set "REMAINING_CHANGES="
git status --porcelain > "%TEMP%\ybt_status.tmp" 2>nul <nul
if exist "%TEMP%\ybt_status.tmp" (
    for /f "usebackq tokens=*" %%s in ("%TEMP%\ybt_status.tmp") do set "REMAINING_CHANGES=1"
    del "%TEMP%\ybt_status.tmp" 2>nul
)

if not defined REMAINING_CHANGES (
    echo [INFO] No more uncommitted changes remaining. Ending commit sequence early.
    goto :after_commits
)

echo Current status:
git status -s <nul
echo.

echo Enter file(s) to stage for Commit !CURRENT_COMMIT_IDX!:
echo (Press Enter or enter '.' to stage all remaining changes, or type filename)
set "FILES_TO_STAGE=."
set /p "FILES_TO_STAGE=Files to stage [Default .]: "
if "!FILES_TO_STAGE!"=="" set "FILES_TO_STAGE=."

if "!FILES_TO_STAGE!"=="." (
    git add -A <nul
) else (
    git add !FILES_TO_STAGE! <nul
)

:: Check if anything is actually staged
git diff --cached --quiet <nul
if !errorlevel! equ 0 (
    echo [WARN] Nothing staged for Commit !CURRENT_COMMIT_IDX!. Skipping to prevent empty commit.
    echo.
    goto :next_commit_iteration
)

echo.
echo Staged changes for Commit !CURRENT_COMMIT_IDX!:
git diff --cached --stat <nul
echo.

set "COMMIT_MSG="
set /p "COMMIT_MSG=Enter commit message for Commit !CURRENT_COMMIT_IDX!: "
if "!COMMIT_MSG!"=="" (
    echo [WARN] Empty commit message provided. Commit !CURRENT_COMMIT_IDX! aborted.
    git reset HEAD >nul 2>&1 <nul
    echo.
    goto :next_commit_iteration
)

git commit -m "!COMMIT_MSG!" <nul
if !errorlevel! equ 0 (
    echo [OK] Commit !CURRENT_COMMIT_IDX! created successfully.
) else (
    echo [ERROR] Commit !CURRENT_COMMIT_IDX! failed.
)
echo.

:next_commit_iteration
set /a CURRENT_COMMIT_IDX+=1
goto :commit_loop

:after_commits

:: 8. Final Status & Summary
echo ================================================================
echo                     COMMIT SUMMARY
echo ================================================================
echo Recent commit(s):
git log -n !NUM_COMMITS! --oneline <nul
echo.
echo Remaining uncommitted status:
git status -s <nul
echo.

:confirm_push
:: 9. Push Confirmation
echo ================================================================
echo                  READY FOR REMOTE PUSH
echo ================================================================
echo  Target Remote: !REMOTE_NAME!
echo  Remote URL:    !REMOTE_URL!
echo  Target Branch: !CURRENT_BRANCH!
echo ================================================================
echo.
set "CONFIRM_PUSH=n"
set /p "CONFIRM_PUSH=Confirm push to '!REMOTE_NAME! !CURRENT_BRANCH!'? (y/N): "
if /i not "!CONFIRM_PUSH!"=="y" (
    echo.
    echo [*] Push cancelled by user.
    echo [*] All local commits are safely preserved in Git history.
    goto :end
)

echo.
echo [*] Pushing to !REMOTE_NAME! !CURRENT_BRANCH!...
git push !REMOTE_NAME! !CURRENT_BRANCH!
if %errorlevel% equ 0 (
    echo.
    echo ================================================================
    echo [SUCCESS] Changes successfully pushed to !REMOTE_NAME!/!CURRENT_BRANCH!!
    echo ================================================================
) else (
    echo.
    echo ================================================================
    echo [ERROR] Git push encountered an error (Code: %errorlevel%).
    echo Common remedies:
    echo  1. If remote has new commits: run 'git pull --rebase !REMOTE_NAME! !CURRENT_BRANCH!'
    echo  2. If permission error: verify access rights to your fork URL.
    echo ================================================================
)

:end
echo.
echo Press any key to exit...
pause >nul
