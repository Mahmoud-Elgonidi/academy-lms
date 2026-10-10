#!/usr/bin/env bash
# Hands a build to the testers' Telegram channel: a message with the notes, then
# each APK as a file with its own line under it. A file over the Bot API's 50 MB
# ceiling is not sent; the message says where to get it instead ($RELEASE_URL).
#
#   TELEGRAM_BOT_TOKEN=… TELEGRAM_CHAT_ID=@channel tool/release/telegram.sh notes.txt \
#     "app-arm64-v8a.apk::Most phones" "app-armeabi-v7a.apk::Older phones"
#
# TELEGRAM_CHAT_ID is the channel's @name or its numeric id (-100…), and the bot
# must be an admin there. TELEGRAM_THREAD_ID sends into a topic of a group;
# TELEGRAM_API points at another server (the tests use a fake one). The token
# reaches curl on stdin, never on its command line or in a log line.
set -euo pipefail

: "${TELEGRAM_BOT_TOKEN:?the bot token}" "${TELEGRAM_CHAT_ID:?the channel}"
api="${TELEGRAM_API:-https://api.telegram.org}"
limit=$((50 * 1024 * 1024))
notes_file=${1:?the notes file}
shift

# call METHOD [curl -F fields…]: one Bot API request; fails with Telegram's own reason.
call() {
  local method=$1 answer status
  shift
  answer=$(mktemp)
  status=$(printf 'url = "%s/bot%s/%s"\n' "$api" "$TELEGRAM_BOT_TOKEN" "$method" |
    curl --silent --show-error --config - --output "$answer" --write-out '%{http_code}' \
      --retry 3 --retry-delay 5 --max-time 300 \
      --form-string "chat_id=$TELEGRAM_CHAT_ID" \
      ${TELEGRAM_THREAD_ID:+--form-string "message_thread_id=$TELEGRAM_THREAD_ID"} \
      "$@") || status=000
  if [ "$status" != 200 ]; then
    echo "Telegram $method answered $status: $(head -c 300 "$answer")" >&2
    rm -f "$answer"
    return 1
  fi
  rm -f "$answer"
}

notes=$(head -c 3800 "$notes_file")
too_big=()
sendable=()
for item in "$@"; do
  file=${item%%::*}
  if [ "$(stat -c %s "$file")" -gt "$limit" ]; then too_big+=("$(basename "$file")"); else sendable+=("$item"); fi
done
if [ ${#too_big[@]} -gt 0 ]; then
  notes+=$'\n\n'"أكبر من حد تليجرام (50 ميجا)، نزّلهم من هنا: ${RELEASE_URL:-صفحة الـ build على GitHub}"$'\n'"$(printf '• %s\n' "${too_big[@]}")"
fi

call sendMessage --form-string "text=$notes" --form-string "disable_web_page_preview=true"
for item in "${sendable[@]}"; do
  file=${item%%::*}
  caption=""
  [ "$item" != "$file" ] && caption=${item#*::}
  call sendDocument --form "document=@$file;filename=$(basename "$file")" --form-string "caption=$caption"
done
echo "Sent to Telegram: the notes and ${#sendable[@]} file(s)${too_big:+, ${#too_big[@]} too big}."
