# Branch Protection Setup for wb-ads-rotator-with-split-test

## How to Protect main/master Branch

Go to: **GitHub repo → Settings → Branches → Add branch protection rule**

### Settings to Enable

| Setting | Value |
|---------|-------|
| **Branch name pattern** | `main` (or `master`) |
| **Require a pull request before merging** | Yes |
| **Require approvals** | 1 (minimum) |
| **Require status checks to pass** | Yes |
| **Status checks that are required:** | `PHPCS`, `PHPStan`, `PHP Syntax Check`, `PHP Compatibility` |
| **Require branches to be up to date** | Yes |
| **Do not allow bypassing** | Yes (even admins) |

### Required Status Checks

These come from `.github/workflows/quality.yml`:
- `phpcs` — WordPress Coding Standards
- `phpstan` — Static type analysis
- `php-lint` — Syntax check across PHP 8.1-8.4
- `php-compatibility` — PHP version compatibility
- `security` — Composer dependency vulnerabilities

### Workflow

1. Developer creates feature branch from `develop`
2. Makes changes, commits, pushes branch
3. Opens PR to `develop` (or `main`)
4. CI runs all quality checks automatically
5. If any check fails → PR cannot be merged
6. Developer fixes issues, pushes again
7. All checks pass → PR ready for review
8. Reviewer approves → merge

### Quick Setup via GitHub CLI

```bash
gh api repos/{owner}/wb-ads-rotator-with-split-test/branches/main/protection \
  --method PUT \
  --field required_status_checks='{"strict":true,"contexts":["phpcs","phpstan","php-lint (8.2)","php-compatibility","security"]}' \
  --field enforce_admins=true \
  --field required_pull_request_reviews='{"required_approving_review_count":1}'
```
