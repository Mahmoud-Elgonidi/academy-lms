#!/usr/bin/env bash
# The message testers read with a build: the version, the server it talks to,
# how it is signed, and the app's changes since the last build that went out
# (the previous app-v* tag; the first one lists the latest).
#
#   tool/release/notes.sh 1.0.0 12 release progma.store "Try the new My data page"
set -euo pipefail

name=${1:?version name} build=${2:?build number} signing=${3:?release or debug} host=${4:?server}
line=${5:-}
max=15

prev=$(git describe --tags --abbrev=0 --match 'app-v*' HEAD 2>/dev/null || true)
range=HEAD
[ -n "$prev" ] && range="$prev..HEAD"
paths=(':/apps/mobile' ':/packages/openapi')
total=$(git rev-list --no-merges --count "$range" -- "${paths[@]}")

echo "📱 تطبيق الأكاديمية ${name} (رقم ${build})"
[ -n "$line" ] && echo "$line"
echo "السيرفر: ${host}"
if [ "$signing" = debug ]; then
  echo "⚠️ النسخة دي موقّعة بمفتاح مؤقت: امسح النسخة القديمة من الموبايل قبل ما تنزّلها."
fi
echo
if [ -z "$prev" ]; then echo "آخر التغييرات في التطبيق:"; else echo "الجديد من آخر نسخة (${prev#app-v}):"; fi
if [ "$total" -eq 0 ]; then
  echo "مفيش تغييرات في التطبيق من آخر نسخة."
else
  git log --no-merges --format='• %s' -n "$max" "$range" -- "${paths[@]}"
  [ -n "$prev" ] && [ "$total" -gt "$max" ] && echo "… و$((total - max)) تغيير كمان."
fi
echo
echo "الـ commit: $(git rev-parse --short HEAD)"
echo "أغلب الموبايلات: ملف arm64-v8a. الموبايلات القديمة: armeabi-v7a."
