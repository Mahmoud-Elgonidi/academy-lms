#!/usr/bin/env bash
# The notes testers read with a build: the app's name and version, the server it
# talks to, how it is signed, and what changed since the last build that went
# out (the previous app-v* tag).
#
# A change is a commit's "App-Change:" lines (Modern Standard Arabic, one per change,
# in the commit's last paragraph with the other trailers; "App-Change: -" leaves
# the commit out). A commit without them that changes what goes into the app
# (apps/mobile without its tests, tools and docs, or the OpenAPI document) is
# listed by its subject. The first build lists only App-Change lines: what the
# app holds, as its commits told testers.
#
#   tool/release/notes.sh Academy 1.0.0 12 release lms.progma.store "جرّبوا صفحة بياناتي"
set -euo pipefail

app=${1:?app name} name=${2:?version name} build=${3:?build number} signing=${4:?release or debug} host=${5:?server}
line=${6:-}
max=15

# The last build before this one (a re-run of this build already has its own tag).
prev=$(git describe --tags --abbrev=0 --match 'app-v*' --exclude "app-v${name}-b${build}" HEAD 2>/dev/null || true)
paths=(':(top)apps/mobile' ':(top)packages/openapi'
  ':(top,exclude)apps/mobile/test' ':(top,exclude)apps/mobile/tool' ':(top,exclude)apps/mobile/*.md')
said() { git log --no-merges --format='%(trailers:key=App-Change,valueonly,separator=%x0A)' "$@" | sed '/^[[:space:]]*$/d'; }

changes=()
if [ -n "$prev" ]; then
  declare -A touched=()
  for sha in $(git rev-list --no-merges "$prev..HEAD" -- "${paths[@]}"); do touched[$sha]=1; done
  for sha in $(git rev-list --no-merges "$prev..HEAD"); do
    mapfile -t lines < <(said -1 "$sha")
    if [ ${#lines[@]} -gt 0 ]; then
      for c in "${lines[@]}"; do [ "$c" = - ] || changes+=("$c"); done
    elif [ -n "${touched[$sha]:-}" ]; then
      changes+=("$(git log -1 --format=%s "$sha")")
    fi
  done
else
  mapfile -t changes < <(said HEAD | grep -vx -- '-' || true)
fi

echo "📱 ${app} — الإصدار ${name} (البناء ${build})"
[ -n "$line" ] && echo "$line"
echo "الخادم: ${host}"
if [ "$signing" = debug ]; then
  echo "⚠️ هذا الإصدار موقّع بمفتاح مؤقت: احذف الإصدار القديم من الهاتف قبل أن تثبّته."
fi
echo
if [ -z "$prev" ]; then
  [ ${#changes[@]} -gt 0 ] && echo "أول إصدار على القناة، وفيه:" || echo "أول إصدار على القناة."
else
  was=${prev#app-v}
  echo "الجديد منذ الإصدار ${was%-b*} (البناء ${was##*-b}):"
  [ ${#changes[@]} -eq 0 ] && echo "لا توجد تغييرات في التطبيق نفسه منذ آخر إصدار."
fi
if [ ${#changes[@]} -gt 0 ]; then
  printf '• %s\n' "${changes[@]:0:max}"
  [ ${#changes[@]} -gt $max ] && echo "… و$((${#changes[@]} - max)) أخرى."
fi
echo
echo "الإيداع (commit): $(git rev-parse --short HEAD)"
