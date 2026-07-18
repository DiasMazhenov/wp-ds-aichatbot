#!/usr/bin/env bash

set -eu

project_root="$(git rev-parse --show-toplevel)"
archive_dir="${project_root}/dist"
archive_path="${archive_dir}/wp-ds-aichatbot.zip"

mkdir -p "${archive_dir}"

git -C "${project_root}" archive \
	--worktree-attributes \
	--format=zip \
	--prefix=wp-ds-aichatbot/ \
	--output="${archive_path}" \
	HEAD

archive_entries="$(unzip -Z1 "${archive_path}")"

if ! grep -qx 'wp-ds-aichatbot/wp-ds-aichatbot.php' <<< "${archive_entries}"; then
	echo 'Package validation failed: plugin entrypoint is missing.' >&2
	exit 1
fi

if ! grep -qx 'wp-ds-aichatbot/readme.txt' <<< "${archive_entries}"; then
	echo 'Package validation failed: WordPress readme.txt is missing.' >&2
	exit 1
fi

if grep -Eq '^wp-ds-aichatbot/(\.github|scripts|Context\.md|Plan\.md|composer\.json|phpcs\.xml\.dist)' <<< "${archive_entries}"; then
	echo 'Package validation failed: development files are present.' >&2
	exit 1
fi

echo "Built ${archive_path}"
