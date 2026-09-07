#!/usr/bin/env bash
#
# 部署到正式站。用法：./deploy/deploy.sh
#
# 需先在 ~/.ssh/config 设定好 SSH_HOST 对应的主机。
set -euo pipefail

SSH_HOST="${SSH_HOST:-aipod-prod}"
REMOTE_ROOT="${REMOTE_ROOT:-/www/wwwroot/wanghui.aipod.works}"
PHP="${REMOTE_PHP:-/www/server/php/83/bin/php}"

cd "$(dirname "$0")/.."

echo "==> 建置前端"
(cd web && npm run build)

echo "==> 同步后端程式码"
# 注意 bootstrap/cache：本机的快取内含 dev 套件清单，
# 传上去会让正式站找不到 Pail 之类的开发套件而 500。
rsync -az --delete \
  --exclude 'vendor/' \
  --exclude 'node_modules/' \
  --exclude '.env' \
  --exclude '.env.*' \
  --exclude 'bootstrap/cache/' \
  --exclude 'database/database.sqlite' \
  --exclude 'storage/logs/*' \
  --exclude 'storage/framework/cache/data/*' \
  --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*' \
  --exclude '.git/' \
  --exclude 'public/index.html' \
  --exclude 'public/assets/' \
  --exclude 'public/storage' \
  api/ "$SSH_HOST:$REMOTE_ROOT/"

echo "==> 同步前端产物"
# 先清掉旧的 hash 档案，否则会一直累积
ssh "$SSH_HOST" "rm -rf $REMOTE_ROOT/public/assets"
rsync -az web/dist/ "$SSH_HOST:$REMOTE_ROOT/public/"

echo "==> 安装相依套件并重建快取"
ssh "$SSH_HOST" "set -e
  cd $REMOTE_ROOT
  $PHP /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
  rm -f bootstrap/cache/*.php
  $PHP artisan package:discover
  $PHP artisan migrate --force
  $PHP artisan storage:link 2>/dev/null || true
  $PHP artisan config:cache
  $PHP artisan route:cache
  $PHP artisan view:cache
  chown -R www:www storage bootstrap/cache public
"

echo "==> 验证"
ssh "$SSH_HOST" "
  curl -s -o /dev/null -w '  首页  %{http_code}\n' http://127.0.0.1:9999/
  curl -s -o /dev/null -w '  健康  %{http_code}\n' http://127.0.0.1:9999/up
  curl -s -o /dev/null -w '  API   %{http_code}（预期 401）\n' -H 'Accept: application/json' http://127.0.0.1:9999/api/me
"

echo "==> 部署完成"
