#!/usr/bin/env bash
# Publish a new version to every WordPress site: ./release.sh 1.2.0
# Checks every PHP file, sets the version in leads-collector.php, commits all
# changes, tags, pushes and publishes leads-collector.zip as a GitHub release.
set -euo pipefail
cd "$(dirname "$0")"

version="${1:-}"
if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Usage: ./release.sh 1.2.0"
  exit 1
fi

current=$(grep -m1 "LEADS_COLLECTOR_VERSION'," leads-collector.php | sed -E "s/.*'([0-9.]+)'.*/\1/")
if ! php -r 'exit(version_compare($argv[1], $argv[2], ">") ? 0 : 1);' "$version" "$current"; then
  echo "The new version must be higher than $current."
  exit 1
fi

for file in $(find . -name '*.php' -not -path './.git/*'); do
  php -l "$file" > /dev/null
done

php -r '
  $file = "leads-collector.php";
  $code = file_get_contents($file);
  $code = preg_replace("/(\* Version:\s+)\d+\.\d+\.\d+/", "\${1}".$argv[1], $code, 1);
  $code = preg_replace("/(LEADS_COLLECTOR_VERSION\x27, \x27)\d+\.\d+\.\d+/", "\${1}".$argv[1], $code, 1);
  file_put_contents($file, $code);
' "$version"

git add -A
git commit -m "Release v$version"
git tag "v$version"
git push origin HEAD "v$version"

build_dir=$(mktemp -d)
git archive --format=zip --prefix=leads-collector/ -o "$build_dir/leads-collector.zip" "v$version"
gh release create "v$version" "$build_dir/leads-collector.zip" --verify-tag --title "Leads Collector v$version" --generate-notes
rm -rf "$build_dir"

echo "Released v$version. Sites see it within 6 hours, or at once under Dashboard → Updates → Check again."
