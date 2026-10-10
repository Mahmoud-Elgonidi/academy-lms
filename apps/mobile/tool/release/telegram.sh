#!/usr/bin/env bash
# Hands a build to the testers' Telegram channel: each APK as its own post that
# says everything on its own, the file named after the app and its version, and
# under it the notes (name, version, the changes) with the file's own line under
# their first one, cut at a line to fit Telegram's caption. A file over the Bot
# API's 50 MB ceiling is not sent: its post is a message with the link
# ($RELEASE_URL) instead.
#
#   TELEGRAM_BOT_TOKEN=… TELEGRAM_CHAT_ID=@channel tool/release/telegram.sh notes.txt \
#     "Academy-v1.0.0-build12-arm64-v8a.apk::📲 Most phones" "…-armeabi-v7a.apk::📲 Older phones"
#
# TELEGRAM_CHAT_ID is the channel's @name or its numeric id (-100…), and the bot
# must be an admin there. TELEGRAM_THREAD_ID sends into a topic of a group;
# TELEGRAM_API points at another server (the tests use a fake one);
# TELEGRAM_DRY_RUN=1 prints the posts instead of sending them (no token needed).
# The token reaches curl on stdin, never on its command line or in a log line.
set -euo pipefail
export LC_ALL=C.UTF-8 # lengths in characters, not bytes

dry=${TELEGRAM_DRY_RUN:-}
if [ -z "$dry" ]; then
  : "${TELEGRAM_BOT_TOKEN:?the bot token}" "${TELEGRAM_CHAT_ID:?the channel}"
fi
api="${TELEGRAM_API:-https://api.telegram.org}"
limit=$((50 * 1024 * 1024))
cap=1000 # Telegram's caption is 1024 UTF-16 units; an emoji takes two
notes_file=${1:?the notes file}
shift
release=${RELEASE_URL:-صفحة النسخة على GitHub}

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

# caption LABEL [ROOM]: the notes with LABEL under their first line, cut at a line
# (with where the rest is) so that it stays within ROOM characters.
caption() {
  local label=$1 room=${2:-$cap} whole part="" l more
  local -a lines
  mapfile -t lines < "$notes_file"
  [ -n "$label" ] && lines=("${lines[0]}" "$label" "${lines[@]:1}")
  whole=$(printf '%s\n' "${lines[@]}")
  if [ ${#whole} -le "$room" ]; then
    printf '%s' "$whole"
    return
  fi
  more="… والباقي على ${release}"
  for l in "${lines[@]}"; do
    [ $((${#part} + ${#l} + 1 + ${#more})) -gt "$room" ] && break
    part+="$l"$'\n'
  done
  printf '%s%s' "$part" "$more"
}

sent=0 linked=0
for item in "$@"; do
  file=${item%%::*}
  label=""
  [ "$item" != "$file" ] && label=${item#*::}
  if [ "$(stat -c %s "$file")" -gt "$limit" ]; then
    text="$(caption "$label")"$'\n\n'"الملف ده ($(basename "$file")) أكبر من حد تليجرام (50 ميجا)، نزّله من هنا: ${release}"
    if [ -n "$dry" ]; then
      printf -- '--- message (%s too big to send)\n%s\n\n' "$(basename "$file")" "$text"
    else
      call sendMessage --form-string "text=$text" --form-string "disable_web_page_preview=true"
    fi
    linked=$((linked + 1))
  else
    text=$(caption "$label")
    if [ -n "$dry" ]; then
      printf -- '--- %s (%s bytes), caption of %s characters:\n%s\n\n' "$(basename "$file")" "$(stat -c %s "$file")" "${#text}" "$text"
    else
      call sendDocument --form "document=@$file;filename=$(basename "$file")" --form-string "caption=$text"
    fi
    sent=$((sent + 1))
  fi
done
too_big=""
[ "$linked" -gt 0 ] && too_big=", ${linked} linked (too big)"
echo "${dry:+Dry run: }Telegram: ${sent} file(s) sent with their notes${too_big}."
