#!/usr/bin/env bash
# The platform team's steps on this server, from GitHub (.github/workflows/console-owner.yml,
# .github/workflows/academy-new.yml) or by hand from this folder. It runs the deployed
# version's own commands, nothing else:
#
#   bash console.sh owner    the email on stdin: that console admin becomes an owner (the
#                            platform settings are theirs to change); audited, as
#                            `php artisan platform:admin <email> --owner` is. With a name
#                            and a password on the next two lines, someone not on the team
#                            yet is added as an owner (they set up the authenticator app on
#                            first sign-in); the password goes to the command on its stdin,
#                            never on a command line, and is never printed.
#   bash console.sh academy  one JSON object on stdin (infra/deploy/RUNBOOKS.md, "Open a client
#                            academy from GitHub"): `php artisan platform:onboard` opens the
#                            academy with its owner, plan, areas off and hidden, and emails the
#                            owner their welcome link (never printed). Checked whole first: a
#                            refusal makes nothing.
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
PROJECT=academy-lms
cd "$HERE"

fail() { printf '\n!! %s\n' "$*" >&2; exit 1; }

export DOCKER_CONFIG="$HERE/.docker"
if docker info >/dev/null 2>&1; then
    dk() { docker "$@"; }
elif sudo -n docker info >/dev/null 2>&1; then
    dk() { sudo -n env DOCKER_CONFIG="$DOCKER_CONFIG" docker "$@"; }
else
    fail "This user cannot run Docker."
fi
compose() { dk compose -p "$PROJECT" -f "$HERE/compose.yml" "$@"; }
[ -f "$HERE/.env" ] || fail "No .env here: deploy.sh has not set this server up yet."

case "${1:-}" in
    owner)
        IFS= read -r email || true
        IFS= read -r name || true
        IFS= read -r password || true
        [[ "$email" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]] || fail "An email on stdin, please."
        if [ -z "$name" ]; then
            compose run --rm --no-deps -T web php artisan platform:admin "$email" --owner
        else
            [ "${#name}" -le 120 ] && [[ ! "$name" =~ [[:cntrl:]] ]] || fail "A name of 120 characters at most, on one line."
            [ "${#password}" -ge 12 ] || fail "A password of 12 characters or more on the third line, please."
            printf '%s\n' "$password" | compose run --rm --no-deps -T web php artisan platform:admin "$email" "$name" --owner --password-stdin
        fi
        ;;
    academy)
        json="$(head -c 16385)"
        [ "${#json}" -le 16384 ] || fail "The JSON is too long (16 KB at most)."
        [[ "$json" =~ ^[[:space:]]*\{ ]] || fail "One JSON object on stdin, please."
        printf '%s' "$json" | compose run --rm --no-deps -T web php artisan platform:onboard
        ;;
    https)
        # An academy's address on the server's certificate (nginx and certbot hold HTTPS; README.md): the
        # certificate that already covers this platform's names is expanded with the new one, keeping
        # every name it had. Needs certbot through sudo without a password; otherwise it says what to type.
        IFS= read -r host || true
        host="$(printf '%s' "$host" | tr 'A-Z' 'a-z')"
        domain="$(grep -E '^PLATFORM_DOMAIN=' "$HERE/.env" | tail -n1 | cut -d= -f2- | tr -d '"'"'"' ')"
        [ -n "$domain" ] || fail "No PLATFORM_DOMAIN in .env."
        [[ "$host" =~ ^[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$ ]] && host="$host.$domain"
        [[ "$host" =~ ^[a-z0-9]([a-z0-9-]*[a-z0-9])?\.$(printf '%s' "$domain" | sed 's/\./\\./g')$ ]] \
            || fail "An academy's name under $domain on stdin (its slug, or slug.$domain)."
        manual="sudo certbot certificates   # then: sudo certbot --nginx --cert-name <name> --expand -d <every name it has> -d $host"
        sudo -n certbot --version >/dev/null 2>&1 || fail "This user cannot run certbot through sudo without a password. On the server: $manual"
        listing="$(sudo -n certbot certificates 2>/dev/null)"
        # Each certificate as "name<TAB>its names"; the one that holds this platform's names.
        certs="$(printf '%s\n' "$listing" | awk '/Certificate Name:/{n=$3} /Domains:/{sub(/^ *Domains: */,""); print n "\t" $0}')"
        line="$(printf '%s\n' "$certs" | awk -F'\t' -v d="$domain" '{split($2,a," "); for(i in a){ if(a[i]==d || a[i]=="*." d || a[i] ~ ("\\." d "$")){print; exit} }}')"
        [ -n "$line" ] || fail "No certificate here covers $domain yet. On the server: $manual"
        name="${line%%$'\t'*}"; names="${line#*$'\t'}"
        for n in $names; do
            if [ "$n" = "$host" ] || [ "$n" = "*.$domain" ]; then echo "Already covered: https://$host (certificate $name)"; exit 0; fi
        done
        args=(); for n in $names; do args+=(-d "$n"); done
        sudo -n certbot --nginx --non-interactive --cert-name "$name" --expand "${args[@]}" -d "$host" \
            || fail "certbot did not add $host (does its DNS point at this server?). Nothing else changed."
        echo "Added to the certificate $name: https://$host"
        ;;
    *)
        fail "Usage: bash console.sh owner     (the console admin's email on stdin; to add someone, their name and password on the next lines)
       bash console.sh academy   (one JSON object on stdin: the academy, its owner and plan; see RUNBOOKS.md)
       bash console.sh https     (an academy's slug or address on stdin: added to the server's certificate)"
        ;;
esac
