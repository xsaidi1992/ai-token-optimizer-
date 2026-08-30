<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AI Token Optimizer — Universal Token Optimization Suite</title>
  <meta name="description" content="Suite universelle d'optimisation des tokens pour agents IA de développement. 11 IDEs supportés. Guide 2026 intégré.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/chart.min.js"></script>
</head>
<body>

  <div class="dashboard-container">

    <!-- Header -->
    <header class="header">
      <div class="brand">
        <div class="brand-icon">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
        </div>
        <div>
          <h1 class="brand-title">AI Token Optimizer</h1>
          <p class="brand-subtitle">Universal Token Optimization Suite • 12 Supported IDEs & AI Agents • Guide 2026</p>
        </div>
      </div>
      <div class="header-actions">
        <button id="btn-deploy-all" class="btn btn-primary" style="background: linear-gradient(135deg, #10b981, #059669); border-color: rgba(16,185,129,0.5); font-weight: 700; box-shadow: 0 0 15px rgba(16,185,129,0.3);">
          🚀 Déployer sur Tous les IDEs
        </button>
        <button id="btn-toggle-optimization" class="btn btn-primary" style="background: linear-gradient(135deg, #10b981, #059669); border-color: rgba(16,185,129,0.5);">
          <span id="btn-opt-text">⚡ Règles Antigravity</span>
        </button>
        <div class="live-badge"><span class="pulse-dot"></span><span>Live</span></div>
        <span id="scanned-at-time" style="font-size: 0.8rem; color: var(--text-dim);">--:--:--</span>
        <button id="btn-rescan" class="btn btn-secondary">🔄 Reanalyser</button>
        <button id="btn-simulate" class="btn btn-primary">+ Simuler</button>
      </div>
    </header>

    <!-- System Info Banner -->
    <div class="glass-card" id="system-banner" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; padding: 1rem 1.5rem; border-color: rgba(99,102,241,0.3);">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <span style="font-size: 1.5rem;">🖥️</span>
        <div>
          <span style="font-weight: 700; color: white;" id="sys-hostname">Détection machine...</span>
          <span style="font-size: 0.8rem; color: var(--text-muted); display: block;" id="sys-details">OS • PHP • Home</span>
        </div>
      </div>
      <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
        <div style="text-align: right;">
          <span style="font-size: 0.7rem; color: var(--text-muted); display: block; text-transform: uppercase;">Tokens Mesurés</span>
          <span style="font-weight: 700; color: white;" id="real-tokens-measured">--</span>
        </div>
        <div style="text-align: right;">
          <span style="font-size: 0.7rem; color: var(--text-muted); display: block; text-transform: uppercase;">Économie</span>
          <span style="font-weight: 700; color: var(--accent-emerald);" id="real-tokens-saved">--</span>
        </div>
        <div>
          <span id="opt-status-badge" class="model-tag" style="background: rgba(16,185,129,0.2); color: #10b981; border-color: rgba(16,185,129,0.4); font-weight: 700;">⚡ ACTIF</span>
        </div>
      </div>
    </div>

    <!-- Main Navigation Tabs -->
    <div class="main-nav-tabs" style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; overflow-x: auto;">
      <button class="nav-tab active" data-tab="dashboard">📊 Dashboard</button>
      <button class="nav-tab" data-tab="editors">🔧 Éditeurs & Règles</button>
      <button class="nav-tab" data-tab="guide">📘 Guide 2026</button>
      <button class="nav-tab" data-tab="audit">🔍 Audit Workspace</button>
      <button class="nav-tab" data-tab="benchmark">📸 Benchmarks</button>
      <button class="nav-tab" data-tab="proxy">⚡ Proxy Optimizer</button>
    </div>

    <!-- TAB: Dashboard -->
    <div id="tab-dashboard" class="tab-content active">

      <!-- Editor Tabs -->
      <section class="glass-card" style="margin-bottom: 2rem; padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
          <h2 class="section-title" style="margin: 0; color: white;">Sélecteur d'Éditeur • Détection Automatique</h2>
          <span style="font-size: 0.8rem; color: var(--text-muted);">12 Éditeurs Pris en Charge</span>
        </div>
        <div class="editor-tabs-nav" id="editor-tabs-nav"></div>
      </section>

      <div id="editor-view-container"></div>

      <!-- Global KPIs -->
      <section class="kpi-grid">
        <div class="glass-card">
          <div class="kpi-card-header"><span>Consommation Totale (1 Mois)</span><div class="kpi-icon indigo"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div></div>
          <div class="kpi-value" id="global-total-tokens">--</div>
          <div class="kpi-subtext">Total Tokens</div>
        </div>
        <div class="glass-card">
          <div class="kpi-card-header"><span>Répartition</span><div class="kpi-icon emerald"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div></div>
          <div style="display:flex; justify-content:space-between;">
            <div><div style="font-size:0.75rem; color:var(--text-muted)">Prompt</div><div class="kpi-value" style="font-size:1.3rem;" id="global-prompt-tokens">--</div></div>
            <div style="text-align:right;"><div style="font-size:0.75rem; color:var(--text-muted)">Completion</div><div class="kpi-value" style="font-size:1.3rem; color:var(--accent-emerald)" id="global-completion-tokens">--</div></div>
          </div>
        </div>
        <div class="glass-card">
          <div class="kpi-card-header"><span>Coût Estimé</span><div class="kpi-icon pink"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
          <div class="kpi-value" style="color:var(--accent-pink)" id="global-cost">$0.00</div>
        </div>
        <div class="glass-card">
          <div class="kpi-card-header"><span>Requêtes</span><div class="kpi-icon violet"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div></div>
          <div class="kpi-value" id="global-requests">--</div>
        </div>
        <div class="glass-card" style="background: linear-gradient(135deg, rgba(16,185,129,0.18), rgba(6,182,212,0.12)); border: 1px solid rgba(16,185,129,0.4); box-shadow: 0 0 20px rgba(16,185,129,0.15);">
          <div class="kpi-card-header"><span style="color: #10b981; font-weight: 700;">Gain $ / 100k Tokens</span><div class="kpi-icon emerald"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
          <div class="kpi-value" style="font-size: 1.7rem; font-weight: 900; color: #10b981;" id="global-savings-100k">--</div>
          <div class="kpi-subtext" id="global-savings-100k-sub" style="color: var(--text-muted); font-size: 0.75rem;">Avant: -- → Après: --</div>
        </div>
        <div class="glass-card">
          <div class="kpi-card-header"><span>Modèles / Pic</span><div class="kpi-icon amber"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/></svg></div></div>
          <div class="kpi-value" style="font-size: 1.4rem;" id="active-models-count">--</div>
          <div class="kpi-subtext" id="peak-day-text">Pic: --</div>
        </div>
      </section>

      <!-- Chart -->
      <section class="chart-section glass-card">
        <div class="chart-header">
          <h2 class="section-title">Consommation 30 Jours</h2>
          <div class="model-filters">
            <button class="filter-pill active" data-model="ALL">Tous</button>
          </div>
        </div>
        <div class="chart-wrapper"><canvas id="consumptionChart"></canvas></div>
      </section>

      <!-- Models Grid -->
      <h2 class="section-title" style="margin-bottom: 1.25rem;">Statistiques par Modèle</h2>
      <div class="models-grid" id="models-grid"></div>
    </div>

    <!-- TAB: Editors & Rules -->
    <div id="tab-editors" class="tab-content" style="display:none;">
      <section class="glass-card" style="margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
          <h2 class="section-title">Configuration des 12 Éditeurs IA</h2>
          <button id="btn-deploy-all-tab" class="btn btn-primary" style="background: linear-gradient(135deg, #10b981, #059669);">🚀 Déployer Tout d'un Coup</button>
        </div>
        <div id="editors-grid" class="editors-config-grid"></div>
      </section>
    </div>

    <!-- TAB: Guide 2026 -->
    <div id="tab-guide" class="tab-content" style="display:none;">
      <section class="glass-card" style="margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
          <h2 class="section-title">📘 Guide Maximal d'Optimisation des Tokens (60 Sections)</h2>
          <input type="text" id="guide-search" class="form-control" style="max-width: 300px;" placeholder="🔍 Rechercher dans le guide...">
        </div>
        <div class="guide-categories" id="guide-categories" style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
          <button class="filter-pill active" data-cat="all">Tout</button>
          <button class="filter-pill" data-cat="principles">Principes</button>
          <button class="filter-pill" data-cat="ide">IDE-Specific</button>
          <button class="filter-pill" data-cat="linux">Linux</button>
          <button class="filter-pill" data-cat="prompts">Prompts</button>
          <button class="filter-pill" data-cat="audit">Audit</button>
          <button class="filter-pill" data-cat="team">Équipe</button>
          <button class="filter-pill" data-cat="advanced">⚡ Avancé</button>
        </div>
        <div id="guide-content" class="guide-sections"></div>
      </section>
    </div>

    <!-- TAB: Audit Workspace -->
    <div id="tab-audit" class="tab-content" style="display:none;">
      <section class="glass-card" style="margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
          <h2 class="section-title">🔍 Audit de Bruit du Workspace</h2>
          <button id="btn-run-audit" class="btn btn-primary">Lancer l'Audit</button>
        </div>
        <pre id="audit-output" style="background: rgba(0,0,0,0.4); border-radius: 12px; padding: 1.5rem; color: var(--accent-emerald); font-size: 0.85rem; overflow-x: auto; white-space: pre-wrap; max-height: 600px; overflow-y: auto;">Cliquez sur "Lancer l'Audit" pour analyser votre workspace...</pre>
      </section>
    </div>

    <!-- TAB: Benchmarks -->
    <div id="tab-benchmark" class="tab-content" style="display:none;">
      <section class="glass-card" style="margin-bottom: 2rem; border-color: rgba(16,185,129,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
          <div>
            <h2 class="section-title">📸 Agent Benchmarks (AVANT vs APRÈS par Éditeur & Modèles)</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Impact réel des règles d'optimisation sur les modèles spécifiques de chaque IDE.</p>
          </div>
          <button id="btn-agent-snapshot" class="btn btn-primary" style="background: linear-gradient(135deg, #10b981, #059669);">📸 Capturer Snapshot Benchmark</button>
        </div>

        <!-- Per-IDE Benchmark Filter Pills -->
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 0.5rem;" id="benchmark-editor-filters"></div>

        <div class="kpi-grid" style="margin-bottom: 1.5rem;">
          <div class="glass-card" style="background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.25);">
            <div class="kpi-card-header"><span>Réduction Tokens</span><span id="bench-token-reduction-badge" style="color: var(--accent-emerald); font-weight: 700;">--%</span></div>
            <div class="kpi-value" style="color: var(--accent-emerald)" id="opt-token-reduction">--</div>
            <div class="kpi-subtext" id="opt-token-subtext">Économie par exécution</div>
          </div>
          <div class="glass-card" style="background: rgba(99,102,241,0.08); border-color: rgba(99,102,241,0.25);">
            <div class="kpi-card-header"><span>Compression</span><span style="color: var(--accent-indigo); font-weight: 700;" id="bench-compression-badge">--x</span></div>
            <div class="kpi-value" style="color: var(--accent-indigo)" id="opt-ratio">--</div>
          </div>
          <div class="glass-card" style="background: rgba(236,72,153,0.08); border-color: rgba(236,72,153,0.25);">
            <div class="kpi-card-header"><span>Réduction Coût</span><span id="bench-cost-reduction-badge" style="color: var(--accent-pink); font-weight: 700;">--%</span></div>
            <div class="kpi-value" style="color: var(--accent-pink)" id="opt-cost-reduction">--</div>
          </div>
        </div>
        <div style="position: relative; height: 260px; width: 100%; margin-bottom: 1.5rem;"><canvas id="snapshotComparisonChart"></canvas></div>
        <div class="feed-table-container">
          <table class="feed-table"><thead><tr><th>Date</th><th>Éditeur</th><th>Benchmark</th><th>Modèle</th><th>Statut</th><th>Prompt/Response</th><th>Total</th><th>Économie</th><th>Coût</th></tr></thead>
          <tbody id="snapshot-table-body"></tbody></table>
        </div>
      </section>
    </div>

    <!-- TAB: Proxy Optimizer -->
    <div id="tab-proxy" class="tab-content" style="display:none;">
      <!-- Proxy Status Header -->
      <section class="glass-card" style="margin-bottom:1.5rem; border-color:rgba(16,185,129,0.3); box-shadow:0 0 24px rgba(16,185,129,0.08);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
          <div style="display:flex; align-items:center; gap:1rem;">
            <span style="font-size:2rem;">⚡</span>
            <div>
              <h2 class="section-title" style="margin:0;">Proxy Optimizer — 19 Patterns</h2>
              <p style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">Contrôle en temps réel de chaque optimisation appliquée par le proxy :3100</p>
            </div>
          </div>
          <div style="display:flex; gap:1rem; align-items:center;">
            <span id="proxy-status-dot" style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.85rem; font-weight:700;">
              <span class="pulse-dot"></span> <span id="proxy-status-text">Vérification...</span>
            </span>
            <button id="btn-proxy-toggle-all" class="btn btn-primary" style="background:linear-gradient(135deg,#10b981,#059669);">🔌 Activer/Désactiver Proxy</button>
          </div>
        </div>
      </section>

      <!-- Proxy KPIs -->
      <section class="kpi-grid" style="margin-bottom:1.5rem;">
        <div class="glass-card" style="background:rgba(16,185,129,0.08); border-color:rgba(16,185,129,0.25);">
          <div class="kpi-card-header"><span>Économie Réelle</span></div>
          <div class="kpi-value" style="color:#10b981;" id="proxy-avg-savings">--%</div>
          <div class="kpi-subtext">Sur les requêtes loggées</div>
        </div>
        <div class="glass-card" style="background:rgba(99,102,241,0.08); border-color:rgba(99,102,241,0.25);">
          <div class="kpi-card-header"><span>Requêtes Interceptées</span></div>
          <div class="kpi-value" style="color:#818cf8;" id="proxy-total-requests">--</div>
        </div>
        <div class="glass-card" style="background:rgba(14,165,233,0.08); border-color:rgba(14,165,233,0.25);">
          <div class="kpi-card-header"><span>Bytes Avant → Après</span></div>
          <div class="kpi-value" style="font-size:1.1rem; color:#0ea5e9;" id="proxy-bytes-flow">-- → --</div>
        </div>
        <div class="glass-card" style="background:rgba(245,158,11,0.08); border-color:rgba(245,158,11,0.25);">
          <div class="kpi-card-header"><span>Patterns Actifs</span></div>
          <div class="kpi-value" style="color:#f59e0b;" id="proxy-active-patterns">--/19</div>
        </div>
      </section>

      <!-- Pattern Toggles Grid -->
      <section class="glass-card" style="margin-bottom:1.5rem;">
        <h3 style="font-weight:700; color:white; margin-bottom:1rem;">🎛️ Contrôle des 19 Patterns d'Optimisation</h3>
        <div id="proxy-patterns-grid" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:0.75rem;"></div>
      </section>

      <!-- Recent Proxy Requests Table -->
      <section class="glass-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
          <h3 style="font-weight:700; color:white; margin:0;">📊 Dernières Requêtes Optimisées</h3>
          <button id="btn-proxy-refresh" class="btn btn-secondary" style="font-size:0.8rem;">🔄 Actualiser</button>
        </div>
        <div id="proxy-stale-banner" style="display:none; background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.3); border-radius:8px; padding:0.6rem 1rem; margin-bottom:0.75rem; font-size:0.82rem; color:#fbbf24;"></div>
        <div class="feed-table-container" style="max-height:400px; overflow-y:auto;">
          <table class="feed-table">
            <thead><tr><th>Heure</th><th>URI</th><th>Avant</th><th>Après</th><th>Économie</th><th>Tier</th><th>Actions</th></tr></thead>
            <tbody id="proxy-requests-body"></tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- Footer -->
    <footer class="footer">
      <p>AI Token Optimizer v3.0 — Universal Token Optimization Suite for AI Development Agents • 12 IDEs • 19 Proxy Patterns • Guide 2026</p>
    </footer>
  </div>

  <!-- Simulation Modal -->
  <div class="modal-backdrop" id="sim-modal">
    <div class="modal-box">
      <h3 style="font-family: var(--font-heading); font-size: 1.3rem; margin-bottom: 1rem; color: white;">Simuler une Détection de Tokens</h3>
      <form id="sim-form">
        <div class="form-group">
          <label class="form-label" for="sim-model">Modèle</label>
          <select class="form-control" id="sim-model" name="model">
            <option value="Gemini 3.6 Flash (High)">Gemini 3.6 Flash (High)</option>
            <option value="Gemini 3.5 Flash (High)">Gemini 3.5 Flash (High)</option>
            <option value="Gemini 3.1 Pro (High)">Gemini 3.1 Pro (High)</option>
            <option value="Claude Sonnet 4.6 (Thinking)">Claude Sonnet 4.6</option>
            <option value="Claude Opus 4.6 (Thinking)">Claude Opus 4.6</option>
            <option value="GPT-OSS 120B (Medium)">GPT-OSS 120B</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="sim-prompt">Prompt</label>
          <textarea class="form-control" id="sim-prompt" name="prompt" rows="3" placeholder="Requête simulée..."></textarea>
        </div>
        <div style="display:flex; justify-content:flex-end; gap: 0.75rem; margin-top: 1.5rem;">
          <button type="button" class="btn btn-secondary" id="close-sim-modal">Annuler</button>
          <button type="submit" class="btn btn-primary">Envoyer</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Snapshot Modal -->
  <div class="modal-backdrop" id="snapshot-modal">
    <div class="modal-box" style="border-color: rgba(16,185,129,0.4);">
      <h3 style="font-family: var(--font-heading); font-size: 1.3rem; margin-bottom: 1rem; color: white;">📸 Capturer un Snapshot Benchmark</h3>
      <form id="snapshot-form">
        <div class="form-group">
          <label class="form-label" for="snap-editor">Éditeur Target</label>
          <select class="form-control" id="snap-editor" name="editor">
            <!-- Dynamically populated from Editors -->
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="snap-prompt-key">Tâche Benchmark</label>
          <select class="form-control" id="snap-prompt-key" name="prompt_key">
            <option value="code_analysis">Analyse & Refactorisation de Code System</option>
            <option value="architecture_design">Conception Architecture Microservices & UI</option>
            <option value="security_audit">Audit Sécurité OWASP & Conformité Agentic</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="snap-model">Modèle de l'Éditeur</label>
          <select class="form-control" id="snap-model" name="model">
            <!-- Dynamically populated per editor -->
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="snap-mode">Mode Benchmark</label>
          <select class="form-control" id="snap-mode" name="mode">
            <option value="AFTER_OPTIMIZATION">✨ APRÈS Optimisation (Règles Actives)</option>
            <option value="BEFORE_OPTIMIZATION">⚠️ AVANT (Baseline Non-Optimisé)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Règles Appliquées</label>
          <input type="text" class="form-control" name="rules" value="context-pruning, concise-prompting, guide-2026-compress" readonly>
        </div>
        <div style="display:flex; justify-content:flex-end; gap: 0.75rem; margin-top: 1.5rem;">
          <button type="button" class="btn btn-secondary" id="close-snapshot-modal">Annuler</button>
          <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #10b981, #059669);">Capturer Snapshot</button>
        </div>
      </form>
    </div>
  </div>

  <script src="js/app.js"></script>
</body>
</html>
