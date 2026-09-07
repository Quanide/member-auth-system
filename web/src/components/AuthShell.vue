<template>
  <div class="auth-page">
    <!-- 背景裝飾：網格 + 浮動光球 + 掃描線，純 CSS 實作，不佔主線程 -->
    <div class="bg-layer" aria-hidden="true">
      <div class="bg-mesh"></div>
      <div class="bg-orb orb-1"></div>
      <div class="bg-orb orb-2"></div>
      <div class="bg-orb orb-3"></div>
      <div class="scan-line"></div>
    </div>

    <div class="auth-wrap" :class="{ 'auth-wrap--narrow': narrow }">
      <!-- 左側品牌區（窄版隱藏） -->
      <div v-if="!narrow" class="auth-brand">
        <div class="brand-logo">
          <svg width="56" height="56" viewBox="0 0 56 56" fill="none">
            <defs>
              <linearGradient id="brandGrad" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#6366F1" />
                <stop offset="100%" stop-color="#8B5CF6" />
              </linearGradient>
            </defs>
            <rect width="56" height="56" rx="15" fill="url(#brandGrad)" />
            <circle cx="28" cy="22" r="8.5" fill="white" opacity="0.95" />
            <path
              d="M13 45 C13 35.5 21 31 28 31 C35 31 43 35.5 43 45"
              fill="white"
              opacity="0.95"
            />
          </svg>
        </div>

        <div class="brand-tag">
          <span class="brand-tag-star">✦</span>
          <span class="brand-tag-text">SECURE MEMBER IDENTITY PLATFORM</span>
        </div>

        <h1 class="brand-name">會員中心 <em>Member</em></h1>
        <p class="brand-desc">安全、可追溯的會員身份管理。</p>

        <div class="brand-features">
          <div v-for="(f, i) in features" :key="f" class="feature-item" :style="{ '--d': 0.4 + i * 0.1 + 's' }">
            <span class="feature-check">
              <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round">
                <polyline points="20 6 9 17 4 12" />
              </svg>
            </span>
            <span>{{ f }}</span>
          </div>
        </div>
      </div>

      <!-- 右側內容卡片 -->
      <div class="auth-card">
        <div class="card-shine" aria-hidden="true"></div>
        <div class="auth-card-inner">
          <div class="card-head">
            <h2 class="card-title">{{ title }}</h2>
            <p v-if="subtitle" class="card-subtitle">{{ subtitle }}</p>
          </div>

          <slot />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    title: string
    subtitle?: string
    /** 窄版：隱藏左側品牌區，用於驗證結果類的單卡片頁面 */
    narrow?: boolean
  }>(),
  { subtitle: '', narrow: false },
)

const features = ['bcrypt 加密儲存，絕不落明文', '登入裝置管理與操作稽核', '信箱驗證與雙重確認變更']
</script>

<style scoped lang="scss">
.auth-page {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 32px 20px;
  background: radial-gradient(ellipse at 30% 40%, #0f1238 0%, #0a0c1e 60%, #060810 100%);
  overflow: hidden;
}

// ── 背景 ──────────────────────────────────────────
.bg-layer {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.bg-mesh {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(99, 102, 241, 0.045) 1px, transparent 1px),
    linear-gradient(90deg, rgba(99, 102, 241, 0.045) 1px, transparent 1px);
  background-size: 56px 56px;
}

.bg-orb {
  position: absolute;
  border-radius: 50%;
  will-change: transform;
}

.orb-1 {
  width: 620px;
  height: 620px;
  background: radial-gradient(circle, rgba(99, 102, 241, 0.2) 0%, rgba(99, 102, 241, 0.08) 40%, transparent 68%);
  top: -220px;
  left: -160px;
  animation: orbFloat1 16s ease-in-out infinite;
}

.orb-2 {
  width: 460px;
  height: 460px;
  background: radial-gradient(circle, rgba(139, 92, 246, 0.15) 0%, rgba(139, 92, 246, 0.06) 40%, transparent 68%);
  bottom: -160px;
  right: 80px;
  animation: orbFloat2 22s ease-in-out infinite;
}

.orb-3 {
  width: 280px;
  height: 280px;
  background: radial-gradient(circle, rgba(99, 102, 241, 0.09) 0%, transparent 68%);
  top: 42%;
  right: 36%;
  animation: orbFloat3 28s ease-in-out infinite;
}

@keyframes orbFloat1 {
  0%, 100% { transform: translate(0, 0) scale(1); }
  35% { transform: translate(38px, -32px) scale(1.06); }
  70% { transform: translate(-16px, 18px) scale(0.96); }
}

@keyframes orbFloat2 {
  0%, 100% { transform: translate(0, 0); }
  50% { transform: translate(-32px, -42px); }
}

@keyframes orbFloat3 {
  0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.5; }
  50% { transform: translate(22px, -26px) scale(1.14); opacity: 0.9; }
}

.scan-line {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 1.5px;
  will-change: transform;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(99, 102, 241, 0.45) 20%,
    rgba(167, 139, 250, 0.9) 50%,
    rgba(99, 102, 241, 0.45) 80%,
    transparent
  );
  box-shadow: 0 0 10px rgba(99, 102, 241, 0.5);
  animation: scanDown 7s linear infinite;
}

@keyframes scanDown {
  from { transform: translateY(0); opacity: 0; }
  8% { opacity: 1; }
  92% { opacity: 1; }
  to { transform: translateY(100vh); opacity: 0; }
}

// ── 布局 ──────────────────────────────────────────
.auth-wrap {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 72px;
  width: 100%;
  max-width: 1040px;
}

.auth-wrap--narrow {
  max-width: 480px;
  justify-content: center;
}

// ── 品牌區 ────────────────────────────────────────
.auth-brand {
  flex: 1;
  min-width: 0;
  color: #fff;
  animation: fadeUp 0.6s ease both;
}

.brand-logo {
  margin-bottom: 22px;
}

.brand-tag {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 5px 12px;
  margin-bottom: 18px;
  border: 1px solid rgba(99, 102, 241, 0.3);
  border-radius: 999px;
  background: rgba(99, 102, 241, 0.08);
}

.brand-tag-star {
  color: #a78bfa;
  font-size: 11px;
}

.brand-tag-text {
  font-size: 10px;
  letter-spacing: 0.12em;
  color: rgba(255, 255, 255, 0.55);
}

.brand-name {
  margin: 0 0 12px;
  font-size: 38px;
  font-weight: 700;
  letter-spacing: 0.5px;

  em {
    font-style: normal;
    background: linear-gradient(135deg, #818cf8, #a78bfa);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 800;
  }
}

.brand-desc {
  margin: 0 0 32px;
  font-size: 15px;
  color: rgba(255, 255, 255, 0.45);
}

.brand-features {
  display: flex;
  flex-direction: column;
  gap: 13px;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13.5px;
  color: rgba(255, 255, 255, 0.62);
  animation: fadeUp 0.5s ease both;
  animation-delay: var(--d);
}

.feature-check {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 17px;
  height: 17px;
  border-radius: 50%;
  background: rgba(99, 102, 241, 0.18);
  border: 1px solid rgba(99, 102, 241, 0.4);
  color: #a78bfa;
  flex-shrink: 0;
}

// ── 卡片 ──────────────────────────────────────────
.auth-card {
  position: relative;
  width: 420px;
  flex-shrink: 0;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.98);
  box-shadow:
    0 24px 60px rgba(0, 0, 0, 0.4),
    0 0 0 1px rgba(99, 102, 241, 0.12);
  overflow: hidden;
  animation: fadeUp 0.55s ease 0.12s both;
}

.auth-wrap--narrow .auth-card {
  width: 100%;
}

.card-shine {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: linear-gradient(90deg, transparent, #8b5cf6, #6366f1, transparent);
}

.auth-card-inner {
  padding: 34px 34px 30px;
}

.card-head {
  margin-bottom: 24px;
}

.card-title {
  margin: 0 0 6px;
  font-size: 21px;
  font-weight: 650;
  color: #1d2129;
}

.card-subtitle {
  margin: 0;
  font-size: 13px;
  color: #86909c;
  line-height: 1.6;
}

@keyframes fadeUp {
  from {
    opacity: 0;
    transform: translateY(14px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

// ── 響應式 ────────────────────────────────────────
@media (max-width: 900px) {
  .auth-wrap {
    flex-direction: column;
    gap: 36px;
    max-width: 440px;
  }

  .auth-brand {
    text-align: center;
  }

  .brand-name {
    font-size: 30px;
  }

  .brand-features {
    display: none;
  }

  .auth-card {
    width: 100%;
  }
}

@media (max-width: 480px) {
  .auth-card-inner {
    padding: 26px 22px 24px;
  }
}
</style>
