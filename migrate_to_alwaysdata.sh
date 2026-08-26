#!/bin/bash
set -e

MYSQL="/c/xampp/mysql/bin/mysql"
MYSQLDUMP="/c/xampp/mysql/bin/mysqldump"
AD_HOST="mysql-nexled.alwaysdata.net"
AD_USER="nexled"
AD_PASS='je479WATm%bbLTP'
OUT="$HOME/Downloads/nexled_migration"

mkdir -p "$OUT"

echo ""
echo "=== STAGE 1: Dumping databases ==="

echo "  -> tecit_Referencias from tecit.pt..."
"$MYSQLDUMP" -h tecit.pt -u ref -pref01 tecit_Referencias > "$OUT/referencias.sql"
echo "     done ($(wc -l < "$OUT/referencias.sql") lines)"

echo "  -> tecit_lampadas from tecit.pt..."
"$MYSQLDUMP" -h tecit.pt -u lampadas -pledlamp74621 tecit_lampadas > "$OUT/lampadas.sql"
echo "     done ($(wc -l < "$OUT/lampadas.sql") lines)"

echo "  -> info_nexled_2024 from local XAMPP..."
"$MYSQLDUMP" -h localhost -u root info_nexled_2024 > "$OUT/info.sql"
echo "     done ($(wc -l < "$OUT/info.sql") lines)"

echo ""
echo "=== STAGE 2: Stripping DEFINER ==="
sed -i 's/DEFINER=[^ ]* //g' "$OUT/referencias.sql" "$OUT/lampadas.sql" "$OUT/info.sql"
echo "  -> done"

echo ""
echo "=== STAGE 3: Creating databases on alwaysdata ==="
"$MYSQL" -h "$AD_HOST" -u "$AD_USER" "-p$AD_PASS" -e "CREATE DATABASE IF NOT EXISTS nexled_referencias CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
echo "  -> nexled_referencias created"
"$MYSQL" -h "$AD_HOST" -u "$AD_USER" "-p$AD_PASS" -e "CREATE DATABASE IF NOT EXISTS nexled_lampadas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
echo "  -> nexled_lampadas created"
"$MYSQL" -h "$AD_HOST" -u "$AD_USER" "-p$AD_PASS" -e "CREATE DATABASE IF NOT EXISTS nexled_info CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
echo "  -> nexled_info created"

echo ""
echo "=== STAGE 4: Importing to alwaysdata ==="
echo "  -> importing referencias (may take a moment)..."
"$MYSQL" -h "$AD_HOST" -u "$AD_USER" "-p$AD_PASS" nexled_referencias < "$OUT/referencias.sql"
echo "     done"
echo "  -> importing lampadas (may take a moment)..."
"$MYSQL" -h "$AD_HOST" -u "$AD_USER" "-p$AD_PASS" nexled_lampadas < "$OUT/lampadas.sql"
echo "     done"
echo "  -> importing info..."
"$MYSQL" -h "$AD_HOST" -u "$AD_USER" "-p$AD_PASS" nexled_info < "$OUT/info.sql"
echo "     done"

echo ""
echo "=== ALL DONE ==="
echo "SQL files saved to: $OUT"
echo "Next step: update .env.local on the alwaysdata server (I will give you the commands)."
