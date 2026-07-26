#!/usr/bin/env bash

set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
test_root="$(mktemp -d)"
trap 'rm -rf "${test_root}"' EXIT

cp "${project_root}/scripts/agent-change-guard.sh" "${test_root}/guard.sh"
cd "${test_root}"

git init -q
git config user.email "agent-guard@example.invalid"
git config user.name "Agent guard test"
mkdir -p .github/workflows scripts src tests/Integration tests/Unit tests/Scripts
cp guard.sh scripts/agent-change-guard.sh
cp "${project_root}/tests/Scripts/agent-change-guard-test.sh" tests/Scripts/agent-change-guard-test.sh
printf 'name: CI\n' > .github/workflows/ci.yml
printf 'name: Agent safety\n' > .github/workflows/agent-safety.yml
printf '* @DiasMazhenov\n' > .github/CODEOWNERS
printf '# Protection\n' > .github/BRANCH_PROTECTION.md
printf '# Rules\n' > AGENTS.md
printf '<?php\n%s\n' "$(printf 'echo true;\n%.0s' {1..100})" > src/Runtime.php
printf '<?php\nfunction test_contract() {}\n' > tests/Unit/CoreSecurityTest.php
printf '<?php\nfunction test_qa() {}\n' > tests/Unit/QaAndReengageTest.php
printf '<?php\nfunction test_sse() {}\n' > tests/Unit/SseFrameParserTest.php
printf 'assert.equal(true, true);\n' > tests/Integration/playground-smoke.mjs
git add .
git commit -qm "baseline"
base_sha="$(git rev-parse HEAD)"

printf '<?php\n' > src/NewModule.php
git add src/NewModule.php
git commit -qm "safe addition"
bash guard.sh "${base_sha}" HEAD

git reset -q --hard "${base_sha}"
printf 'name: weakened\n' > .github/workflows/ci.yml
git add .github/workflows/ci.yml
git commit -qm "weaken workflow"
if bash guard.sh "${base_sha}" HEAD >/dev/null 2>&1; then
	echo "Expected protected workflow change to fail." >&2
	exit 1
fi

git reset -q --hard "${base_sha}"
git rm -q src/Runtime.php
git commit -qm "delete runtime"
if bash guard.sh "${base_sha}" HEAD >/dev/null 2>&1; then
	echo "Expected runtime deletion to fail." >&2
	exit 1
fi

git reset -q --hard "${base_sha}"
printf '<?php\n' > tests/Unit/CoreSecurityTest.php
git add tests/Unit/CoreSecurityTest.php
git commit -qm "remove test"
if bash guard.sh "${base_sha}" HEAD >/dev/null 2>&1; then
	echo "Expected protected test change to fail." >&2
	exit 1
fi

printf 'agent-change-guard tests passed\n'
