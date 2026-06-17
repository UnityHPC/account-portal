set -euo pipefail
trap 's=$?; echo "$0: Error on line "$LINENO": $BASH_COMMAND"; exit $s' ERR
if [[ $# -lt 1 ]]; then
    echo "at least one argument required"
    exit 1
fi

rc=0
for file in "$@"; do
    grep_rc=0; grep -q UnityHTTPD::forbidden "$file" || grep_rc=$?
    case "$grep_rc" in
        0)
            : ;; # code is good, do nothing
        1)
            echo "HTTPForbidden() was not called in file '$file'!"; rc=1 ;;
        *)
            echo "grep failed!";  rc=1 ;;
    esac
done
exit "$rc"
