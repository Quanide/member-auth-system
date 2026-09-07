# 会员登入系统

一套完整的会员身份管理系统，涵盖**会员注册、会员登入、会员资料修改**三项核心功能，
并在其上补齐了生产环境真正会用到的部分：邮箱验证、忘记密码、变更登入邮箱、
头像上传、登入装置管理与操作稽核。

- **线上展示**：https://wanghui.aipod.works
- **示范帐号**：`demo@wanghui.aipod.works` / `Demo12345`（登入页有「一键填入」）

---

## 目录

- [功能一览](#功能一览)
- [技术栈](#技术栈)
- [专案结构](#专案结构)
- [安全设计](#安全设计)
- [几个值得说明的设计决策](#几个值得说明的设计决策)
- [本地开发](#本地开发)
- [测试](#测试)
- [API 一览](#api-一览)
- [部署](#部署)

---

## 功能一览

### 题目要求的三项

| 功能 | 说明 |
| --- | --- |
| **会员注册** | 表单验证、密码强度即时提示、服务条款确认、注册即建立会话、自动寄出验证信 |
| **会员登入** | 记住我、失败次数限制与帐号锁定、IP 限流、登入轨迹记录 |
| **会员资料修改** | 姓名／昵称／手机／生日／性别／简介、头像上传与裁切、资料完整度提示 |

### 在此之上补齐的

| 功能 | 说明 |
| --- | --- |
| 邮箱验证 | 签章连结、可重寄（每分钟一封）、未验证时全站横幅提醒 |
| 忘记密码 | 寄送重设连结、一次性 token、重设后自动解除帐号锁定 |
| 修改密码 | 需验证旧密码，改完自动登出其他装置 |
| 变更登入邮箱 | 需验证密码 + 新邮箱双重确认，验证通过前不动原邮箱 |
| 登入装置管理 | 列出所有登入中的装置，可单独登出或一键登出其他装置 |
| 操作稽核 | 18 种敏感操作留痕，可依类型筛选、分页查询 |
| 会员总览 | 帐号安全状态、资料完整度、近期登入一览 |

---

## 技术栈

**后端**

- PHP 8.3 + Laravel 13（撰写时的最新稳定版）
- Laravel Sanctum — SPA 模式（session cookie 认证）
- MySQL 8.4
- PHPUnit 12 — 65 个测试 / 189 项断言

**前端**

- Vue 3.5（Composition API + `<script setup>`）+ TypeScript strict
- Element Plus 2.9（按需引入）+ 自订蓝紫主题
- Pinia · Vue Router · Axios · Vite 6

**部署**

- Nginx + PHP-FPM，前后端同网域

---

## 专案结构

```
member-auth/
├── api/                        Laravel 后端
│   ├── app/
│   │   ├── Enums/              UserRole / UserStatus / AuditAction
│   │   ├── Exceptions/         DomainException 与具名业务例外
│   │   ├── Http/
│   │   │   ├── Controllers/    只负责组装请求与回应
│   │   │   ├── Requests/       表单验证规则与中文讯息
│   │   │   └── Resources/      API 输出格式
│   │   ├── Models/             User / AuditLog / EmailChangeRequest
│   │   ├── Notifications/      三种邮件通知（中文文案）
│   │   ├── Services/           业务逻辑集中处
│   │   └── Support/            ApiResponse / ErrorCode
│   ├── database/migrations/    资料表结构
│   ├── lang/zh_CN/             验证讯息中文化
│   ├── routes/api.php          路由与限流设定
│   └── tests/                  Feature 测试
├── web/                        Vue 前端
│   └── src/
│       ├── api/                Axios 封装与端点定义
│       ├── components/         AuthShell / SideMenu / PasswordStrength
│       ├── composables/        useApiForm — 表单提交的通用处理
│       ├── stores/             Pinia 认证状态
│       ├── styles/             设计 token 与 Element Plus 主题覆盖
│       └── views/              页面
└── deploy/                     Nginx 设定
```

**分层原则**：控制器不写业务逻辑，只做「取参数 → 呼叫 Service → 回传 Resource」；
所有业务规则集中在 `app/Services/`，因此登入的锁定逻辑、改密码的连带效果都只有一处实作，
测试也直接打在这一层。

---

## 安全设计

| 项目 | 做法 |
| --- | --- |
| 密码储存 | bcrypt cost 12，永不落明文；`password` 栏位由 `casts()` 的 `hashed` 自动处理 |
| 密码强度 | 后端 `Password::defaults()` 统一把关，生产环境额外启用 `uncompromised()`（HIBP k-匿名查询）；前端强度条只是即时提示 |
| 认证方式 | Sanctum SPA 模式，凭证放在 **httpOnly Cookie**，前端 JS 读不到，XSS 也偷不走 |
| CSRF | 全站 CSRF token 校验；Axios 自动带 `X-XSRF-TOKEN`，419 时自动重取 |
| 防爆破 | 双层：路由层 IP 限流 + 帐号层失败计数（5 次锁定 15 分钟），换 IP 也绕不过 |
| 帐号枚举 | 「帐号不存在」与「密码错误」回传完全相同的错误码与讯息，且都走一次 bcrypt 比对让耗时一致；忘记密码不论邮箱是否存在都回同一句话 |
| 越权防护 | `$fillable` 只放会员本人可改的资料栏位，`role`／`status`／`email`／`password` 一律走专用方法，杜绝批量赋值提权 |
| 档案上传 | 不信任副档名与 Content-Type，以 `getimagesize()` 读档头判定真实型别；服务端重新编码，顺带剥掉 EXIF 与可能夹带的脚本；随机档名储存 |
| 会话安全 | 登入后 regenerate session id（防固定会话）；改密码／重设密码自动清除其他装置的 session |
| Token 储存 | 变更邮箱的 token 只存 SHA-256 摘要，资料库外泄也无法直接冒用；一次性使用 + 60 分钟过期 |
| 错误处理 | 未预期的例外一律回 500 且不吐堆叠；生产环境 `APP_DEBUG=false` |
| HTTP 标头 | Nginx 层设定 CSP、X-Frame-Options、X-Content-Type-Options 等 |
| 稽核轨迹 | 18 种敏感操作留痕；资料变更只记录**栏位名**不记录值，避免个资明文堆进日志表 |

---

## 几个值得说明的设计决策

**1. 为什么 IP 限流阈值（10 次/分）高于帐号锁定阈值（5 次）？**

两者都设 5 的话，IP 限流会先触发，使用者看到的是笼统的「请求过于频繁」，
而不是更精确的「帐号已锁定 15 分钟，或改用忘记密码」。
把 IP 限流放宽，让语义更明确的那一层先讲话；IP 限流只用来兜住换帐号扫号的流量。

**2. 为什么邮箱验证不用 `email:rfc,dns`？**

`dns` 规则会对每次注册做一次真实 MX 查询：DNS 超时会直接让注册失败，
新网域与内网网域也会被误杀。邮箱是否真实由「必须收信才能完成验证」把关更可靠，
所以只做格式校验（`email:rfc,filter`）。

**3. 变更邮箱为什么要双重确认？**

邮箱等同帐号入口。若直接写回 `users.email`，使用者填错一个字母就会把自己锁在门外。
因此验证通过前不动原邮箱，只在 `email_change_requests` 存一笔待确认纪录。

**4. 头像为什么在前后端各压一次？**

前端压缩是为了省流量与加快上传；后端重新编码则是安全必须——
它同时完成真实型别校验、EXIF 剥离与统一尺寸。前端那一层可以被绕过，后端这层不行。

**5. 为什么 `manualChunks` 不能写 `element-plus`？**

`manualChunks` 一旦点名整个套件，Rollup 就会把它完整打进产物，
按需引入的 tree-shaking 全部白做（实测差异：917 KB → 119 KB）。

---

## 本地开发

**需求**：PHP >= 8.2（含 gd、mbstring、pdo_mysql、bcmath）、Composer、Node >= 20

```bash
# 后端
cd api
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite     # 本地用 SQLite，免装资料库
php artisan migrate --seed
php artisan serve                  # http://localhost:8000

# 前端（另开一个终端）
cd web
npm install
npm run dev                        # http://localhost:5273
```

本地不设定 SMTP 时，`MAIL_MAILER=log` 会把邮件内容写进
`api/storage/logs/laravel.log`，验证连结可直接从日志复制。

---

## 测试

```bash
cd api
php artisan test
```

65 个测试 / 189 项断言，覆盖三项核心功能与各项安全防护：

| 测试档 | 覆盖内容 |
| --- | --- |
| `Auth/RegistrationTest` | 注册成功、寄验证信、密码雜凑储存、邮箱重复与大小写、弱密码、越权指定 admin 角色 |
| `Auth/LoginTest` | 登入成功、帐号枚举防护、连续失败锁定、锁定期内正确密码亦拒绝、停权帐号、登出后 session 失效 |
| `Auth/PasswordResetTest` | 寄送重设信、枚举防护、token 重设、无效 token、重设后解除锁定 |
| `Profile/UpdateProfileTest` | 资料更新、批量赋值防护、各项验证规则、空字串正规化、稽核只记栏位名 |
| `Profile/ChangePasswordTest` | 旧密码校验、新旧密码不得相同、改密后清除其他装置 session |
| `Profile/AvatarTest` | 上传、正方形裁切、旧档清理、**伪装成图片的 PHP 档被拒**、大小上限 |
| `Profile/EmailChangeTest` | 双重确认流程、token 一次性、过期拒绝、**token 以雜凑储存** |
| `Profile/SessionManagementTest` | 装置列表、不回传完整 session id、无法撤销他人会话、稽核纪录隔离 |

---

## API 一览

统一回应格式：

```jsonc
// 成功
{ "ok": true, "data": { ... } }

// 失败
{ "ok": false, "code": "INVALID_CREDENTIALS", "message": "邮箱或密码不正确", "errors": { "email": ["..."] } }
```

前端只需判断 `ok`，再依 `code` 分支处理，不必去猜 HTTP 状态码的语义。

| 方法 | 路径 | 说明 | 限流 |
| --- | --- | --- | --- |
| POST | `/api/auth/register` | 注册 | 6 次/时 |
| POST | `/api/auth/login` | 登入 | 10 次/分（帐号+IP） |
| POST | `/api/auth/logout` | 登出 | — |
| POST | `/api/auth/password/forgot` | 寄送重设连结 | 10 次/时 |
| POST | `/api/auth/password/reset` | 重设密码 | 10 次/时 |
| GET | `/api/auth/email/verify/{id}/{hash}` | 验证邮箱（签章连结） | 10 次/分 |
| POST | `/api/auth/email/resend` | 重寄验证信 | 1 次/分 |
| GET | `/api/me` | 取得个人资料 | — |
| PATCH | `/api/me` | **修改会员资料** | — |
| GET | `/api/me/overview` | 会员总览 | — |
| POST | `/api/me/avatar` | 上传头像 | 20 次/时 |
| DELETE | `/api/me/avatar` | 移除头像 | — |
| PUT | `/api/me/password` | 修改密码 | 10 次/时 |
| POST | `/api/me/email-change` | 申请变更邮箱 | 5 次/时 |
| POST | `/api/email-change/confirm` | 确认变更邮箱 | 10 次/时 |
| GET | `/api/me/sessions` | 登入装置列表 | — |
| DELETE | `/api/me/sessions/{id}` | 登出指定装置 | — |
| DELETE | `/api/me/sessions/others` | 登出其他装置 | — |
| GET | `/api/me/activities` | 操作纪录（分页、可筛选） | — |

---

## 部署

线上环境为 Nginx + PHP-FPM，前后端同网域：`/api`、`/sanctum` 交给 Laravel，
其余路径回退到 Vue 的 `index.html` 由前端路由接手。
Nginx 规则见 [`deploy/nginx-laravel-spa.conf`](deploy/nginx-laravel-spa.conf)。

```bash
# 前端建置后放进 Laravel 的 public/
cd web && npm run build && cp -r dist/* ../api/public/

# 后端
cd api
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

站点的 Nginx 运行目录需指向 `api/public`。
