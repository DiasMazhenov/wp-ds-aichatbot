#!/usr/bin/env bash

set -euo pipefail

base_sha="${1:?Base commit is required.}"
head_sha="${2:?Head commit is required.}"
failed=0

fail() {
	printf 'agent-safety: %s\n' "$1" >&2
	failed=1
}

is_protected_file() {
	case "$1" in
		.github/workflows/agent-safety.yml|\
		.github/workflows/ci.yml|\
		.github/CODEOWNERS|\
		.github/BRANCH_PROTECTION.md|\
		AGENTS.md|\
		opencode.json|\
		composer.json|\
		package.json|\
		phpcs.xml.dist|\
		phpunit.xml.dist|\
		scripts/agent-change-guard.sh|\
		scripts/build-zip.sh|\
		tests/Scripts/agent-change-guard-test.sh|\
		tests/Integration/blueprint-elementor.json|\
		tests/Integration/fixtures/mu-plugins/wpdsac-smoke-probe.php|\
		tests/Integration/playground-smoke.mjs|\
		tests/Unit/CoreSecurityTest.php|\
		tests/Unit/QaAndReengageTest.php|\
		tests/Unit/SseFrameParserTest.php|\
		tests/Unit/UrlSecurityTest.php)
			return 0
			;;
	esac

	return 1
}

is_runtime_file() {
	case "$1" in
		src/*.php|src/*/*.php|src/*/*/*.php|assets/build/*|templates/*|wp-ds-aichatbot.php|uninstall.php)
			return 0
			;;
	esac

	return 1
}

git cat-file -e "${base_sha}^{commit}"
git cat-file -e "${head_sha}^{commit}"

while IFS=$'\t' read -r status old_path new_path; do
	[ -n "${status}" ] || continue

	if is_protected_file "${old_path}" || { [ -n "${new_path:-}" ] && is_protected_file "${new_path}"; }; then
		fail "protected guardrail or regression contract changed: ${old_path}${new_path:+ -> ${new_path}}"
	fi

	case "${status}" in
		D*|R*)
			if is_runtime_file "${old_path}" || [[ "${old_path}" == tests/* ]]; then
				fail "runtime or test file deleted/renamed: ${old_path}${new_path:+ -> ${new_path}}"
			fi
			;;
	esac

	if [[ "${status}" == M* ]] && is_runtime_file "${old_path}"; then
		base_size="$(git cat-file -s "${base_sha}:${old_path}")"
		head_size="$(git cat-file -s "${head_sha}:${old_path}")"

		if [ "${base_size}" -gt 400 ] && [ $(( head_size * 100 )) -lt $(( base_size * 60 )) ]; then
			fail "runtime file shrank by more than 40%: ${old_path}"
		fi
	fi
done < <(git diff --name-status --find-renames "${base_sha}" "${head_sha}")

runtime_deletions="$(
	git diff --numstat "${base_sha}" "${head_sha}" -- src assets/build templates wp-ds-aichatbot.php uninstall.php |
		awk '$2 ~ /^[0-9]+$/ { deleted += $2 } END { print deleted + 0 }'
)"

if [ "${runtime_deletions}" -gt 500 ]; then
	fail "runtime diff deletes ${runtime_deletions} lines; limit is 500"
fi

base_test_signals="$(
	git grep -E -c 'function test_|assert\.' "${base_sha}" -- tests 2>/dev/null |
		awk -F: '{ count += $NF } END { print count + 0 }'
)"
head_test_signals="$(
	git grep -E -c 'function test_|assert\.' "${head_sha}" -- tests 2>/dev/null |
		awk -F: '{ count += $NF } END { print count + 0 }'
)"

if [ "${head_test_signals}" -lt "${base_test_signals}" ]; then
	fail "test signals decreased from ${base_test_signals} to ${head_test_signals}"
fi

if [ "${failed}" -ne 0 ]; then
	exit 1
fi

printf 'agent-safety: protected contracts preserved\n'
