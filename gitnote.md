# Git Notes — Post Studio

## Branch Strategy

- `main` — production-ready code only
- `dev` — development branch, merged to main on release
- Feature branches: `feature/<name>` — create from `dev`, merge back to `dev`

## Commit Messages

- Use present tense: "fix success message", "add analytics", not "fixed" or "added"
- Prefix with area when helpful: `feat:`, `fix:`, `docs:`, `chore:`
- Example: `fix: success message not showing for mixed service posts`

## Pull Request Process

1. Create feature branch from `dev`
2. Implement changes, test locally
3. Push branch and open PR against `dev`
4. At least one approval required before merge
5. After merge, delete feature branch

## Pre-commit

- Run `php -l` on all modified PHP files
- Ensure no new `TODO` or `FIXME` left without tracking in AGENTS.md
- Verify `.gitignore` excludes `note.md` and OS junk

## Known Git Ignores (already in .gitignore)

- `note.md` — local notes, never commit
- OS junk: `.DS_Store`, `Thumbs.db`
- `config.php` — contains placeholder credentials, filled in deployment

## After Pull/Push

- Run `php -l` on all PHP files to catch syntax errors
- Check that `AGENTS.md` is updated with any new features or changes
- Verify `config.php` placeholders are still present (never commit real credentials)