#!/bin/bash
# Lance l'application CTA en apercu local (PHP + SQLite), sans XAMPP :
#   bash app/apercu.sh   puis   http://localhost:3000/
cd "$(dirname "$0")/cta"
export CTA_SQLITE="$PWD/data/cta.sqlite"
export CTA_APERCU=1
exec ~/phpbin/php -d display_errors=0 -S 0.0.0.0:3000 -t .
