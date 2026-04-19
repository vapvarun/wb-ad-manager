#!/usr/bin/env bash
# Advisory lint for docs/website/*.md: charter frontmatter, broken relative links, and banned phrases.

set -u

STRICT=0
for arg in "$@"; do
    case "$arg" in
        --strict) STRICT=1 ;;
        -h|--help)
            cat <<EOF
verify-docs.sh — advisory docs linter for wb-ads-rotator-with-split-test/docs/website

Checks:
  1. Frontmatter keys (title, persona, tier, one_job, outcome) on every .md
  2. Broken relative Markdown links
  3. Banned marketing phrases

Usage:
  bin/verify-docs.sh           # advisory, always exit 0
  bin/verify-docs.sh --strict  # exit 1 if any warning is printed
EOF
            exit 0
            ;;
    esac
done

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd -- "${SCRIPT_DIR}/.." && pwd)"
DOCS_DIR="${PLUGIN_ROOT}/docs/website"

if [ ! -d "${DOCS_DIR}" ]; then
    echo "verify-docs: docs/website not found at ${DOCS_DIR}" >&2
    exit 0
fi

WARN_COUNT=0
warn() {
    WARN_COUNT=$((WARN_COUNT + 1))
    printf '  %s\n' "$1"
}

# Collect every .md under docs/website.
MD_FILES=()
while IFS= read -r -d '' f; do
    MD_FILES+=("$f")
done < <(find "${DOCS_DIR}" -type f -name '*.md' -print0 | sort -z)

TOTAL=${#MD_FILES[@]}
echo "verify-docs: scanning ${TOTAL} markdown file(s) under docs/website"
echo

# --------------------------------------------------------------------------
# Check 1: Frontmatter keys
# --------------------------------------------------------------------------
echo "== Check 1: Charter frontmatter (title, persona, tier, one_job, outcome) =="
REQUIRED_KEYS=(title persona tier one_job outcome)
C1_WARN=0

for f in "${MD_FILES[@]}"; do
    rel="${f#${PLUGIN_ROOT}/}"

    # Read until we find the frontmatter block.
    first_line="$(sed -n '1p' "$f")"
    if [ "${first_line}" != "---" ]; then
        warn "${rel}:1 missing frontmatter block (file does not start with \"---\")"
        C1_WARN=$((C1_WARN + 1))
        continue
    fi

    # Extract frontmatter lines (between first --- and next ---).
    fm="$(awk 'NR==1 && /^---$/ {flag=1; next} flag && /^---$/ {exit} flag {print}' "$f")"

    for key in "${REQUIRED_KEYS[@]}"; do
        if ! printf '%s\n' "$fm" | grep -Eq "^${key}:[[:space:]]+.+"; then
            warn "${rel} missing frontmatter key: ${key}"
            C1_WARN=$((C1_WARN + 1))
        fi
    done
done

if [ "${C1_WARN}" -eq 0 ]; then
    echo "  OK — all files have the required frontmatter keys"
fi
echo

# --------------------------------------------------------------------------
# Check 2: Broken relative Markdown links
# --------------------------------------------------------------------------
echo "== Check 2: Broken relative links =="
C2_WARN=0

# Regex for Markdown links: ](target)  — we match the target only.
# We skip images embedded via ![alt](src) too? No — the syntax is shared and we want to verify images exist as well.
for f in "${MD_FILES[@]}"; do
    rel="${f#${PLUGIN_ROOT}/}"
    dir="$(dirname "$f")"
    line_no=0

    while IFS= read -r line || [ -n "$line" ]; do
        line_no=$((line_no + 1))

        # Extract each "](target)" occurrence on the line.
        remainder="$line"
        while [[ "$remainder" =~ \]\(([^\)]+)\) ]]; do
            target="${BASH_REMATCH[1]}"
            # Move past this match in remainder.
            remainder="${remainder#*${BASH_REMATCH[0]}}"

            # Skip external / in-page / mailto / tel / data links.
            case "$target" in
                http://*|https://*) continue ;;
                \#*) continue ;;
                mailto:*) continue ;;
                tel:*) continue ;;
                data:*) continue ;;
                '') continue ;;
            esac

            # Trim surrounding whitespace.
            target="${target#"${target%%[![:space:]]*}"}"
            target="${target%"${target##*[![:space:]]}"}"

            # Strip anchor / query.
            path_only="${target%%#*}"
            path_only="${path_only%%\?*}"

            # Skip if nothing left after stripping (pure anchor).
            [ -z "${path_only}" ] && continue

            # Resolve relative paths.
            case "${path_only}" in
                /*)   resolved="${path_only}" ;;
                *)    resolved="${dir}/${path_only}" ;;
            esac

            if [ ! -e "${resolved}" ]; then
                warn "${rel}:${line_no} broken link -> ${target}"
                C2_WARN=$((C2_WARN + 1))
            fi
        done
    done < "$f"
done

if [ "${C2_WARN}" -eq 0 ]; then
    echo "  OK — every relative link resolves on disk"
fi
echo

# --------------------------------------------------------------------------
# Check 3: Banned phrases
# --------------------------------------------------------------------------
echo "== Check 3: Banned phrases =="
C3_WARN=0

BANNED=(
    "take your ads to the next level"
    "no coding required"
    "in 10 minutes"
    "Get Ads Running in 10 Minutes"
    "Stripe, PayPal, Razorpay"
)

for f in "${MD_FILES[@]}"; do
    rel="${f#${PLUGIN_ROOT}/}"
    for phrase in "${BANNED[@]}"; do
        # grep -F for literal substring, case-insensitive, with line numbers.
        while IFS= read -r hit; do
            [ -z "${hit}" ] && continue
            ln="${hit%%:*}"
            warn "${rel}:${ln} contains banned phrase: \"${phrase}\""
            C3_WARN=$((C3_WARN + 1))
        done < <(grep -niF -- "${phrase}" "$f" 2>/dev/null || true)
    done
done

if [ "${C3_WARN}" -eq 0 ]; then
    echo "  OK — no banned phrases detected"
fi
echo

# --------------------------------------------------------------------------
# Summary
# --------------------------------------------------------------------------
echo "== Summary =="
echo "  Files scanned       : ${TOTAL}"
echo "  Frontmatter warnings: ${C1_WARN}"
echo "  Link warnings       : ${C2_WARN}"
echo "  Banned-phrase hits  : ${C3_WARN}"
echo "  Total warnings      : ${WARN_COUNT}"

if [ "${STRICT}" -eq 1 ] && [ "${WARN_COUNT}" -gt 0 ]; then
    echo
    echo "verify-docs: strict mode — exiting 1 because warnings were found"
    exit 1
fi

exit 0
