#!/usr/bin/env bash

set -eu

project_root="$(git rev-parse --show-toplevel)"
archive_dir="${project_root}/dist"
archive_path="${archive_dir}/wp-ds-aichatbot.zip"
build_root="$(mktemp -d "${TMPDIR:-/tmp}/wpdsac-build.XXXXXX")"
plugin_root="${build_root}/wp-ds-aichatbot"

cleanup() {
	rm -rf "${build_root}"
}

trap cleanup EXIT

mkdir -p "${archive_dir}"
mkdir -p "${plugin_root}"

git -C "${project_root}" archive \
	--worktree-attributes \
	--format=tar \
	HEAD | tar -xf - -C "${plugin_root}"

cp "${project_root}/composer.json" "${project_root}/composer.lock" "${plugin_root}/"
composer install \
	--working-dir="${plugin_root}" \
	--no-dev \
	--classmap-authoritative \
	--no-interaction \
	--no-progress \
	--prefer-dist
rm "${plugin_root}/composer.json" "${plugin_root}/composer.lock"
rm -f "${archive_path}"

(
	cd "${build_root}"
	zip -qr "${archive_path}" wp-ds-aichatbot
)

archive_entries="$(unzip -Z1 "${archive_path}")"

if ! grep -qx 'wp-ds-aichatbot/wp-ds-aichatbot.php' <<< "${archive_entries}"; then
	echo 'Package validation failed: plugin entrypoint is missing.' >&2
	exit 1
fi

if ! grep -qx 'wp-ds-aichatbot/readme.txt' <<< "${archive_entries}"; then
	echo 'Package validation failed: WordPress readme.txt is missing.' >&2
	exit 1
fi

if grep -Eq '^wp-ds-aichatbot/(\.github|scripts|tests|Context\.md|Plan\.md|composer\.(json|lock)|package(-lock)?\.json|php(cs|unit)\.xml\.dist|vendor/bin)' <<< "${archive_entries}"; then
	echo 'Package validation failed: development files are present.' >&2
	exit 1
fi

if ! grep -qx 'wp-ds-aichatbot/vendor/autoload.php' <<< "${archive_entries}"; then
	echo 'Package validation failed: production autoloader is missing.' >&2
	exit 1
fi

echo "Built ${archive_path}"
