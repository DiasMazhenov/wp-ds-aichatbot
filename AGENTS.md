# OpenCode rules for WP DS AI Chatbot

These instructions are mandatory for every agent working in this repository.

## Read before editing

1. Read `Context.md` and the current-status section of `Plan.md` before changing code.
2. Inspect `git status` and preserve all unrelated and untracked user files.
3. Do not add `.playwright-mcp/`, screenshots, snapshots, local archives, secrets, API keys, `node_modules/`, or unrelated artifacts to a commit.

## Scope and architecture

- Follow WordPress Coding Standards, PHP 7.4 compatibility, OOP modules, sanitization on input, and escaping on output.
- Keep public REST errors free of credentials, SQL, filesystem paths, exception messages, and private diagnostics.
- Do not weaken security, rate limits, request locks, nonces, session validation, privacy controls, or lead consent to make a test pass.
- Contextual reply buttons belong only in `[data-wpdsac-context-actions]`, inside `[data-wpdsac-actions]`, above the message form and outside `[data-wpdsac-messages]`.
- Do not implement trusted server instructions as fake visitor messages.

## Frontend CSS isolation

All component styles must be rooted under `.wpdsac-chat` or `[data-wpdsac-chat]`.

The only permitted selectors outside that root are:

- `body > .wpdsac-chat...` for global fixed positioning;
- `body.wpdsac-modal-open` for temporary scroll locking while the lead modal is open;
- `[data-wpdsac-navigation-highlight]` for the temporary, user-confirmed navigation highlight.

Never add unscoped `:root`, `html`, `body`, `header`, `main`, `footer`, `section`, `button`, `input`, `textarea`, `img`, `svg`, `*`, `.elementor-*`, or `.wpdsac-chat__*` selectors. Never solve a CI failure by broadening the CSS allowlist beyond the three exceptions above.

## Tests before commit

Do not commit or push until all applicable local checks pass:

```sh
composer lint
composer test:unit
composer validate --strict
node --check assets/build/chat.js
node --check assets/build/admin.js
node --check tests/Integration/playground-smoke.mjs
git diff --check
```

Run `npm run test:integration` once after the final change when frontend runtime, REST, lifecycle, templates, or integration behavior changed. Run `npm run test:elementor` once after the final change when Elementor behavior changed or when the integration job failed. Do not repeatedly run Playground for documentation-only work.

When a test fails:

1. Read the first real `AssertionError`, PHP fatal, or failing step.
2. Explain which product contract the assertion represents.
3. Fix the implementation or a genuinely stale assertion.
4. Never replace behavioral coverage with a simple source-text presence check.
5. Never use `continue-on-error`, `if: always()`, delete a useful assertion, or broadly relax a selector/security check merely to turn CI green.

## Version, documentation, and push gate

- Change only the third version number: `0.5.x`.
- Before every push, synchronize the plugin header, `WPDSAC_VERSION`, `tests/Unit/bootstrap.php`, `readme.txt` stable tag and changelog, `Context.md`, and `Plan.md`.
- Make all fixes and local test corrections before the single release-version commit. Do not create one pushed version per failed local attempt.
- Build the ZIP only from the committed `HEAD`, verify it with `unzip -t`, and verify the version and required `assets/build` files inside it.
- Push only after local checks are green. Then wait for every GitHub Actions job. If CI fails, inspect the first failed job before editing anything.
- Report the commit hash and commit time in `Asia/Almaty`.

