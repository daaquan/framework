# Repository Guidelines

## Project Structure & Module Organization

- `src/Phare/`: Framework source code organized by domain (e.g., `Http/`, `Routing/`, `Database/`, `Support/`). Class names follow namespaces that mirror folders.
- `tests/`: Pest tests grouped by area (e.g., `Unit/`, `Http/`, `Database/`). See `Pest.php` and `TestCase.php` for bootstrap.
- `config/`: Framework configuration and defaults. Keep changes minimal and documented.
- `database/`: Test fixtures or migration samples used by tests.
- `bin/`: Local tooling (e.g., `bin/pest`).
- `vendor/`: Composer dependencies (do not edit).

## Build, Test, and Development Commands

- `composer install`: Install PHP dependencies.
- `bin/pest`: Run the full test suite.
- `bin/pest tests/Http`: Run a directory-focused subset.
- `./vendor/bin/pint`: Format PHP code to the project preset.
- `./vendor/bin/phpstan analyse`: Static analysis using `phpstan.neon.dist`.

## Coding Style & Naming Conventions

- Style: PSR-12/Laravel Pint preset. Use 4-space indentation; Unix line endings; one class per file.
- Names: `StudlyCase` for classes, `camelCase` for methods/properties, UPPER_CASE for constants. File path and namespace must match (e.g., `src/Phare/Http/Request.php` → `Phare\Http\Request`).
- Imports: Prefer explicit imports over fully-qualified names inside methods.

## Testing Guidelines

- Framework uses Pest. Co-locate tests under `tests/` mirroring `src/` structure (e.g., `tests/Http/*Test.php`).
- Write tests for new features and bug fixes; include edge cases and failure paths.
- Fast iteration: run `bin/pest -d` for fail-fast; add `--coverage` if configured locally.

## Commit & Pull Request Guidelines

- Commits: Imperative present tense (e.g., "Add router middleware"); group related changes; keep diffs focused.
- PRs must include:
  - **Summary**: What changed and why (scope, context, alternatives).
  - **Testing**: How you validated (commands run, results, screenshots/logs when relevant).
  - Links to related issues/PRs (`#123`) and breaking-change callouts.

## Security & Configuration Tips

- Do not commit secrets. Use environment variables and local `.env` files ignored by Git.
- Prefer config-driven behavior in `config/` over hardcoded constants.
- Validate untrusted input at boundaries (`Http`, `Validation`, `Security`).
