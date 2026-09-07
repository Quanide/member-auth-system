# 會員登入系統

會員身份管理系統，涵蓋**會員註冊、會員登入、會員資料修改**三項核心功能，
並補齊生產環境實際需要的部分：信箱驗證、忘記密碼、變更登入信箱、頭像上傳、
雙因素認證、登入裝置管理、操作稽核，以及一套管理後臺。

- 線上展示：https://wanghui.aipod.works
- 示範帳號：`demo@wanghui.aipod.works` / `Demo12345`（登入頁有「一鍵填入」）

> 示範站未接 SMTP，郵件以日誌方式記錄，點擊「重寄驗證信」不會實際收到信件。

---

## 功能

**會員端**

| 模塊 | 內容 |
| --- | --- |
| 註冊 | 密碼強度即時提示、服務條款確認、註冊即建立會話、自動寄送驗證信 |
| 登入 | 記住我、失敗鎖定、IP 限流、雙因素第二關 |
| 會員資料 | 姓名／暱稱／手機／生日／性別／簡介、頭像上傳與裁切、完整度提示 |
| 帳號安全 | 修改密碼、變更登入信箱、2FA 綁定與恢復碼、登入裝置管理 |
| 操作紀錄 | 個人稽核軌跡，可依型別篩選與分頁 |

**管理端**（需 admin 角色）

| 模塊 | 內容 |
| --- | --- |
| 數據看板 | 註冊趨勢、登入成功失敗曲線、狀態與裝置分布 |
| 會員管理 | 搜尋篩選、停權／恢復、角色調整、解除鎖定、強制登出、軟刪除 |
| 全站稽核 | 跨會員檢索操作紀錄 |

---

## 技術棧

| 層 | 選型 |
| --- | --- |
| 後端 | PHP 8.3 · Laravel 13 · Sanctum（SPA 模式） |
| 前端 | Vue 3.5 · TypeScript strict · Element Plus · Pinia · ECharts · Vite 6 |
| 資料庫 | MySQL 8.4（測試使用 SQLite in-memory） |
| 測試 | PHPUnit 12 — 105 個測試 / 391 項斷言 |
| CI | GitHub Actions — 測試、Pint 風格檢查、前端型別檢查與建置 |

---

## 專案結構

```
api/                        Laravel 後端
├── app/
│   ├── Enums/              UserRole / UserStatus / AuditAction
│   ├── Exceptions/         DomainException 與具名業務例外
│   ├── Http/
│   │   ├── Controllers/    只負責組裝請求與回應
│   │   ├── Middleware/     EnsureUserIsAdmin
│   │   ├── Requests/       驗證規則與中文訊息
│   │   └── Resources/      API 輸出格式
│   ├── Models/             User / AuditLog / EmailChangeRequest
│   ├── Notifications/      郵件通知
│   ├── Services/           業務邏輯集中處
│   └── Support/            ApiResponse / ErrorCode
├── database/migrations/
├── lang/zh_CN/
├── routes/api.php          路由與限流設定
└── tests/                  Feature 93 / Unit 12

web/                        Vue 前端
└── src/
    ├── api/                Axios 封裝與端點定義
    ├── components/         AuthShell / SideMenu / StatCard / PasswordStrength
    ├── composables/        useApiForm
    ├── stores/             Pinia 認證狀態
    ├── styles/             設計 token 與 Element Plus 主題覆蓋
    ├── utils/              格式化、圖表設定、稽核顯示
    └── views/              頁面（含 admin/）
```

控制器不寫業務邏輯，只做「取參數 → 呼叫 Service → 回傳 Resource」；
業務規則集中在 `app/Services/`，測試直接打這一層。

---

## 安全設計

| 專案 | 做法 |
| --- | --- |
| 密碼儲存 | bcrypt cost 12；強度由後端 `Password::defaults()` 把關，生產環境啟用 `uncompromised()` |
| 認證 | 憑證放在 httpOnly Cookie，前端 JS 讀不到；搭配 CSRF token 校驗 |
| 防爆破 | IP 限流 + 帳號失敗計數鎖定（5 次／15 分鐘），換 IP 繞不過 |
| 帳號枚舉 | 帳號不存在與密碼錯誤回傳相同結果，且都走一次 bcrypt 讓耗時一致 |
| 越權 | `$fillable` 只放本人可改欄位；`role`／`status`／`email`／`password` 走專用方法 |
| 權限邊界 | 管理端由 middleware 把關，與前端選單是否顯示無關 |
| 檔案上傳 | 以 `getimagesize()` 讀檔頭判定真實型別，服務端重新編碼剝除 EXIF，隨機檔名儲存 |
| 雙因素 | TOTP 密鑰與恢復碼加密儲存，恢復碼另存雜湊且用後即焚，第二關獨立限流 |
| 會話 | 登入後 regenerate session id；改密碼／重設密碼／停權會清除其他裝置 session |
| Token | 信箱變更 token 只存 SHA-256 摘要，一次性使用且 60 分鐘過期 |
| 稽核 | 23 種敏感操作留痕；資料變更只記錄**欄位名**不記錄值 |

---

## 設計取捨

**IP 限流閾值（10 次／分）刻意高於帳號鎖定閾值（5 次）**
兩者都設 5 的話 IP 限流會先觸發，使用者只看到籠統的「請求過於頻繁」。
讓語義更精確的「帳號已鎖定 15 分鐘」先講話，IP 限流只兜住換帳號掃號的流量。

**信箱不用 `email:rfc,dns` 校驗**
`dns` 規則每次註冊都做真實 MX 查詢，DNS 超時會直接讓註冊失敗，新網域也會被誤殺。
信箱真實性由「必須收信才能完成驗證」把關更可靠。

**變更信箱採雙重確認**
驗證通過前不動 `users.email`，使用者填錯一個字母不會把自己鎖在門外。

**2FA 綁定拆成三步**
產生密鑰後不立即啟用，必須先用 App 產出一組正確驗證碼，避免掃碼失敗的人被鎖在門外。

**`manualChunks` 改用函式形式**
物件形式一旦點名套件名，Rollup 會把該套件完整打進產物，
按需引入的 tree-shaking 全白做（實測 917 KB → 119 KB）。

---

## 本地開發

需求：PHP >= 8.3（含 gd、mbstring、pdo_sqlite、bcmath）、Composer、Node >= 20

```bash
# 後端
cd api
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve                  # http://localhost:8000

# 前端
cd web
npm install
npm run dev                        # http://localhost:5273
```

未設定 SMTP 時郵件會寫進 `api/storage/logs/laravel.log`，驗證連結可從日誌複製。

---

## 測試

```bash
cd api && php artisan test
```

| 測試檔 | 覆蓋內容 |
| --- | --- |
| `Auth/RegistrationTest` | 註冊流程、密碼雜湊、信箱重複與大小寫、弱密碼、越權指定 admin 角色 |
| `Auth/LoginTest` | 登入、帳號枚舉防護、連續失敗鎖定、停權帳號、登出後 session 失效 |
| `Auth/PasswordResetTest` | 寄送重設信、枚舉防護、token 重設、重設後解除鎖定 |
| `Profile/UpdateProfileTest` | 資料更新、批量賦值防護、驗證規則、稽核只記欄位名 |
| `Profile/ChangePasswordTest` | 舊密碼校驗、新舊密碼不得相同、改密後清除其他裝置 |
| `Profile/AvatarTest` | 上傳、正方形裁切、舊檔清理、偽裝成圖片的 PHP 檔被拒 |
| `Profile/EmailChangeTest` | 雙重確認、token 一次性與過期、token 以雜湊儲存 |
| `Profile/SessionManagementTest` | 裝置列表、不回傳完整 session id、無法撤銷他人會話 |
| `Profile/TwoFactorTest` | 綁定三步流程、登入第二關、恢復碼用後即焚、密鑰加密儲存 |
| `Admin/UserManagementTest` | 權限邊界、搜尋篩選、停權連帶清 session、保留最後一位管理員 |
| `Unit/EnumsTest` | 列舉標籤與等級的完整性 |
| `Unit/ApiResponseTest` | 統一回應外殼的結構契約 |

---

## API

統一回應格式：

```jsonc
{ "ok": true, "data": { ... } }
{ "ok": false, "code": "INVALID_CREDENTIALS", "message": "...", "errors": { "email": ["..."] } }
```

| 方法 | 路徑 | 說明 | 限流 |
| --- | --- | --- | --- |
| POST | `/api/auth/register` | 註冊 | 6 次/時 |
| POST | `/api/auth/login` | 登入 | 10 次/分 |
| POST | `/api/auth/two-factor-challenge` | 雙因素第二關 | 20 次/分 |
| POST | `/api/auth/logout` | 登出 | — |
| POST | `/api/auth/password/forgot` | 寄送重設連結 | 10 次/時 |
| POST | `/api/auth/password/reset` | 重設密碼 | 10 次/時 |
| GET | `/api/auth/email/verify/{id}/{hash}` | 驗證信箱（籤章連結） | 10 次/分 |
| POST | `/api/auth/email/resend` | 重寄驗證信 | 1 次/分 |
| GET · PATCH | `/api/me` | 取得／**修改會員資料** | — |
| GET | `/api/me/overview` | 會員總覽 | — |
| POST · DELETE | `/api/me/avatar` | 上傳／移除頭像 | 20 次/時 |
| PUT | `/api/me/password` | 修改密碼 | 10 次/時 |
| POST | `/api/me/email-change` | 申請變更信箱 | 5 次/時 |
| POST | `/api/email-change/confirm` | 確認變更信箱 | 10 次/時 |
| POST | `/api/me/two-factor/generate` | 產生 2FA 密鑰與 QR | 10 次/時 |
| POST | `/api/me/two-factor/confirm` | 確認綁定並取得恢復碼 | 10 次/10 分 |
| POST | `/api/me/two-factor/disable` | 關閉 2FA | 10 次/時 |
| POST | `/api/me/two-factor/recovery-codes` | 重產恢復碼 | 5 次/時 |
| GET · DELETE | `/api/me/sessions` | 登入裝置列表／登出其他裝置 | — |
| GET | `/api/me/activities` | 操作紀錄 | — |
| GET | `/api/admin/stats` | 看板統計 | — |
| GET | `/api/admin/users` | 會員列表 | — |
| PATCH | `/api/admin/users/{id}/status` · `/role` | 變更狀態／角色 | — |
| POST | `/api/admin/users/{id}/unlock` · `/force-logout` | 解鎖／強制登出 | — |
| DELETE | `/api/admin/users/{id}` | 軟刪除會員 | — |
| GET | `/api/admin/audit-logs` | 全站稽核 | — |

---

## 部署

Nginx + PHP-FPM，前後端同網域：`/api`、`/sanctum` 交給 Laravel，
其餘路徑回退 `index.html` 由前端路由接手。規則見 [`deploy/nginx-laravel-spa.conf`](deploy/nginx-laravel-spa.conf)。

```bash
cd web && npm run build && cp -r dist/* ../api/public/

cd api
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

站點 Nginx 運行目錄需指向 `api/public`。
