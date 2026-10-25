#!/bin/sh
###############################################################################
# Renders the NGINX map behind Access-Control-Allow-Origin on the public
# asset paths /storage/*, /tenants/*, /images/* (docker/nginx/public-assets.conf)
# from CORS_ALLOWED_ORIGINS, the variable config/cors.php uses for the API:
#   empty or containing "*"       -> Access-Control-Allow-Origin: *
#   "https://a.example,https://b" -> the request's Origin when it is listed,
#                                    no CORS header otherwise
# Runs on every container start (serversideup /etc/entrypoint.d, sourced in a
# subshell); worker / scheduler containers render it too, harmlessly.
###############################################################################
set -eu

[ -d /etc/nginx/conf.d ] || exit 0

out=/etc/nginx/conf.d/public-cors-map.conf
origins=$(printf '%s' "${CORS_ALLOWED_ORIGINS:-*}" | tr -d ' \t\r\n')

{
    echo '# Generated at start by /etc/entrypoint.d/20-public-cors.sh from CORS_ALLOWED_ORIGINS.'
    echo 'map $http_origin $public_cors_origin {'
    case "$origins" in
        '' | *'*'*)
            echo '    default "*";'
            ;;
        *)
            echo '    default "";'
            for origin in $(printf '%s' "$origins" | tr ',' ' '); do
                origin=${origin%/}
                case "$origin" in
                    *[!A-Za-z0-9.:/-]*)
                        echo "public-cors: ignoring invalid origin '$origin'" >&2
                        ;;
                    http://?* | https://?*)
                        printf '    "%s" $http_origin;\n' "$origin"
                        ;;
                    *)
                        echo "public-cors: ignoring origin without http(s)://: '$origin'" >&2
                        ;;
                esac
            done
            ;;
    esac
    echo '}'
} > "$out"
