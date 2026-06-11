#!/usr/bin/env bash
# QA: dark-mode + host-theme token contract for a Wbcom plugin frontend.
# Usage: bin/qa-dark-mode.sh <token-css-file> [<token-prefix>]
# Verifies the frontend token foundation chains through the host themes
# (BuddyX --bx-color-*, Reign --reign-*) and ships a root dark-override block
# keyed on the real host-theme dark triggers that flips the main surface/text
# tokens with EXPLICIT values (not theme-chained — those stay light on a
# generic toggle). Exits non-zero on any failure.
set -u
F="${1:?token css file}"; P="${2:-polls}"
fail=0; ok(){ printf '  PASS  %s\n' "$1"; }; no(){ printf '  FAIL  %s\n' "$1"; fail=1; }
echo "== dark-mode QA: $F =="
[ -f "$F" ] || { echo "  FAIL  file missing"; exit 2; }
grep -q ":root" "$F" && ok ":root token block present" || no ":root token block present"
[ "$(grep -c 'var(--bx-color-' "$F")" -ge 5 ] && ok "chains through BuddyX --bx-color-*" || no "chains through BuddyX --bx-color-*"
[ "$(grep -c 'var(--reign-' "$F")" -ge 5 ] && ok "chains through Reign --reign-*" || no "chains through Reign --reign-*"
grep -qE 'buddyx-dark-mode|\[data-theme="dark"\]' "$F" && ok "dark block triggers on host themes" || no "dark block triggers on host themes"
# dark block must flip main surface + text tokens
db=$(awk '/dark-mode|dark-scheme|data-theme="dark"/{f=1} f{print} /^}/{if(f)exit}' "$F")
echo "$db" | grep -qE -- "--${P}-(card-bg|bg)\s*:" && ok "dark flips a surface token" || no "dark flips a surface token"
echo "$db" | grep -qE -- "--${P}-text\s*:" && ok "dark flips the text token" || no "dark flips the text token"
# dark values must be explicit (a literal hex), not theme-chained (would stay light)
echo "$db" | grep -E -- "--${P}-card-bg\s*:" | grep -q "#" && ok "dark surface uses an explicit value" || no "dark surface uses an explicit value"
# brace balance
o=$(grep -o '{' "$F"|wc -l); c=$(grep -o '}' "$F"|wc -l); [ "$o" = "$c" ] && ok "braces balanced ($o)" || no "braces balanced ($o/$c)"
[ "$fail" = 0 ] && { echo "== ALL PASS =="; exit 0; } || { echo "== FAILURES =="; exit 1; }
