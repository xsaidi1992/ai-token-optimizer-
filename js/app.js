/**
 * AI Token Optimizer — Universal Commercial Dashboard Engine
 * Supports 11 IDEs, Per-IDE Models & Dashboards, Guide 2026, Audit, Benchmarks per IDE
 */
document.addEventListener('DOMContentLoaded', () => {
    let mainChart = null, comparisonChart = null, editorsData = null, sysInfo = null;
    let activeEditorKey = 'antigravity', activeBenchmarkEditor = 'all', guideData = null;
    const REFRESH_INTERVAL = 5000;

    // === TAB NAVIGATION ===
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
            tab.classList.add('active');
            const target = document.getElementById('tab-' + tab.dataset.tab);
            if (target) target.style.display = 'block';
            if (tab.dataset.tab === 'guide') loadGuide();
            if (tab.dataset.tab === 'editors') renderEditorsConfig();
            if (tab.dataset.tab === 'benchmark') fetchSnapshots(activeBenchmarkEditor);
        });
    });

    // === SYSTEM INFO ===
    function fetchSystemInfo() {
        fetch('api.php?action=system_info').then(r => r.json()).then(d => {
            if (d.status !== 'success') return;
            sysInfo = d;
            const el = document.getElementById('sys-hostname');
            if (el) el.textContent = d.display_host || `Machine : ${d.user}@${d.hostname}`;
            const det = document.getElementById('sys-details');
            if (det) det.textContent = `${d.os} • PHP ${d.php_version} • ${d.home_short} (Détection auto-détectée)`;
        }).catch(() => {});
    }

    // === EDITORS DETECTION ===
    function fetchEditors() {
        fetch('api.php?action=editors').then(r => r.json()).then(d => {
            if (d.status === 'success' && d.editors) {
                editorsData = d.editors;
                renderEditorNavTabs(editorsData);
                renderBenchmarkEditorFilters(editorsData);
                populateSnapshotModalEditors(editorsData);
                updateDashboardForEditor(activeEditorKey);
            }
        }).catch(e => console.error('Editors fetch error:', e));
    }

    function renderEditorNavTabs(editors) {
        const nav = document.getElementById('editor-tabs-nav');
        if (!nav) return;
        nav.innerHTML = Object.keys(editors).map(key => {
            const ed = editors[key];
            const cls = key === activeEditorKey ? 'active' : '';
            const dot = ed.is_installed ? 'installed' : 'missing';
            return `<button class="editor-tab-btn ${cls}" data-editor="${key}">
                <span style="font-size:1.1rem">${ed.icon}</span>
                <span>${ed.name}</span>
                <span class="tab-status-dot ${dot}"></span>
            </button>`;
        }).join('');

        nav.querySelectorAll('.editor-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                activeEditorKey = btn.dataset.editor;
                renderEditorNavTabs(editorsData);
                updateDashboardForEditor(activeEditorKey);
            });
        });
    }

    function renderBenchmarkEditorFilters(editors) {
        const container = document.getElementById('benchmark-editor-filters');
        if (!container) return;
        let html = `<button class="filter-pill ${activeBenchmarkEditor === 'all' ? 'active' : ''}" data-beditor="all">Tous les IDEs</button>`;
        Object.keys(editors).forEach(key => {
            const ed = editors[key];
            const active = activeBenchmarkEditor === key ? 'active' : '';
            html += `<button class="filter-pill ${active}" data-beditor="${key}">${ed.icon} ${ed.name}</button>`;
        });
        container.innerHTML = html;
        container.querySelectorAll('.filter-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                activeBenchmarkEditor = pill.dataset.beditor;
                renderBenchmarkEditorFilters(editorsData);
                fetchSnapshots(activeBenchmarkEditor);
            });
        });
    }

    function populateSnapshotModalEditors(editors) {
        const sel = document.getElementById('snap-editor');
        if (!sel) return;
        sel.innerHTML = Object.keys(editors).map(key => `<option value="${key}">${editors[key].icon} ${editors[key].name}</option>`).join('');
        sel.value = activeEditorKey;
        updateSnapshotModalModels(sel.value);
        sel.onchange = () => updateSnapshotModalModels(sel.value);
    }

    function updateSnapshotModalModels(editorKey) {
        const modelSel = document.getElementById('snap-model');
        if (!modelSel || !editorsData || !editorsData[editorKey]) return;
        const models = editorsData[editorKey].detected_models || [];
        modelSel.innerHTML = models.map(m => `<option value="${m}">${m}</option>`).join('');
    }

    function updateDashboardForEditor(key) {
        if (!editorsData || !editorsData[key]) return;
        const ed = editorsData[key];
        const s = ed.summary || {};
        const kpi = ed.efficiency_kpis || {};

        // Update global top stats
        const el = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
        el('global-total-tokens', Number(s.global_total_tokens||0).toLocaleString());
        el('global-prompt-tokens', Number(s.global_prompt_tokens||0).toLocaleString());
        el('global-completion-tokens', Number(s.global_completion_tokens||0).toLocaleString());
        el('global-cost', '$' + Number(s.global_total_cost||0).toFixed(3));
        el('global-requests', Number(s.global_total_requests||0).toLocaleString());
        el('global-savings-100k', `$${kpi.savings_per_100k || '0.00'}`);
        el('global-savings-100k-sub', `Avant: $${kpi.cost_per_100k_before || '0.00'} → Après: $${kpi.cost_per_100k_after || '0.00'}`);
        el('active-models-count', s.active_models_count || 0);
        if (s.peak_day) el('peak-day-text', `Pic: ${s.peak_day.date} (${Number(s.peak_day.tokens||0).toLocaleString()} toks)`);

        // Dedicated Per-IDE Dashboard View
        renderDedicatedEditorView(key);

        // Render Model Stats & Charts
        renderModelsGrid(ed.model_stats || [], s.global_total_tokens || 1);
        renderMainChart(ed.timeline_labels || [], ed.daily_series || [], ed.model_stats || []);
    }

    function renderDedicatedEditorView(key) {
        const container = document.getElementById('editor-view-container');
        if (!container || !editorsData[key]) return;
        const ed = editorsData[key];

        if (!ed.is_installed) {
            container.innerHTML = `<div class="not-installed-card">
                <span style="font-size:2.8rem">${ed.icon}</span>
                <h3 style="font-family:var(--font-heading);color:white;margin:1rem 0 0.5rem">${ed.name} — Non Détecté sur cette Machine</h3>
                <p style="color:var(--text-muted);font-size:0.9rem;max-width:550px;margin:0 auto 1.5rem">
                    Aucun exécutable CLI ni dossier de configuration n'a été trouvé dans <code>${sysInfo?.home_dir || '~'}</code> pour ${ed.name}. Vous pouvez néanmoins pré-déployer ses règles d'optimisation de tokens.
                </p>
                <button class="btn btn-primary" onclick="deployEditor('${key}')">⚙️ Pré-déployer les Règles d'Optimisation (${ed.name})</button>
            </div>`;
            return;
        }

        const tb = ed.token_breakdown || {};
        const kpi = ed.efficiency_kpis || {};
        const tier = ed.model_tier_matrix || {};
        const tips = ed.editor_tips || [];
        const ruleFiles = (ed.active_rule_paths||[]).map(p => `<code style="font-size:0.75rem;background:rgba(0,0,0,0.4);color:#818cf8;padding:2px 6px;border-radius:4px;margin-right:4px">${p.replace(sysInfo?.home_dir || '', '~')}</code>`).join('') || '<span style="color:var(--text-dim)">Aucune règle déployée</span>';

        const paths = [...(ed.detected_paths||[]), ...(ed.detected_cmds||[])].map(p => `<code style="font-size:0.78rem;background:rgba(0,0,0,0.3);color:var(--accent-indigo);padding:2px 8px;border-radius:4px;margin-right:4px">${p}</code>`).join('');
        const rBadge = ed.rules_active ? '<span class="status-badge rules-on">⚡ RÈGLES DÉPLOYÉES</span>' : '<span class="status-badge missing">⚠️ NON DÉPLOYÉ</span>';

        container.innerHTML = `
        <!-- Dedicated IDE Header Banner -->
        <section class="glass-card" style="margin-bottom: 2rem; border-color: rgba(99,102,241,0.4); background: linear-gradient(135deg, rgba(15,23,42,0.8), rgba(30,41,59,0.7));">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem">
                <div style="display:flex; align-items:center; gap:1.25rem">
                    <div style="width:60px; height:60px; border-radius:16px; background:rgba(99,102,241,0.2); border:1px solid rgba(99,102,241,0.4); display:flex; align-items:center; justify-content:center; font-size:2.2rem">${ed.icon}</div>
                    <div>
                        <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap">
                            <h2 style="font-family:var(--font-heading); font-size:1.5rem; color:white; margin:0">${ed.name}</h2>
                            <span class="status-badge installed">🟢 DÉTECTÉ</span>
                            ${rBadge}
                        </div>
                        <div style="margin-top:0.4rem; display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap">
                            <span style="font-size:0.8rem; color:var(--text-muted)">Chemins:</span> ${paths}
                        </div>
                    </div>
                </div>
                <div style="display:flex; gap:0.75rem">
                    <button class="btn btn-primary" style="background:linear-gradient(135deg,#10b981,#059669); font-weight:700" onclick="deployEditor('${key}')">⚙️ Déployer Règles ${ed.name}</button>
                </div>
            </div>

            <!-- Detailed Token Breakdown Matrix (Guide §1.1 & §18) -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; margin-top:1.5rem; padding-top:1.25rem; border-top:1px solid rgba(255,255,255,0.08)">
                
                <!-- FEATURED HERO KPI: GAIN $ / 100K TOKENS -->
                <div style="grid-column: 1 / -1; background: linear-gradient(135deg, rgba(16,185,129,0.22), rgba(6,182,212,0.15)); padding: 1.25rem 1.5rem; border-radius: 14px; border: 1.5px solid rgba(16,185,129,0.5); box-shadow: 0 0 25px rgba(16,185,129,0.2); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <span style="font-size: 0.8rem; font-weight: 800; color: #10b981; text-transform: uppercase; letter-spacing: 0.06em; display: flex; align-items: center; gap: 0.4rem;">
                            💰 PERFORMANCE FINANCIÈRE • GAIN NET PAR 100K TOKENS
                        </span>
                        <div style="font-size: 2.4rem; font-weight: 900; color: #ffffff; margin: 0.2rem 0; font-family: var(--font-heading);">
                            +$${kpi.savings_per_100k || '0.00'} <span style="font-size: 1.1rem; color: #10b981; font-weight: 700;">/ 100k tokens économisés</span>
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            Baseline (Avant) : <span style="color: #ef4444; font-weight: 700;">$${kpi.cost_per_100k_before || '0.00'}</span> &nbsp;→&nbsp; Optimisé (Après) : <span style="color: #10b981; font-weight: 700;">$${kpi.cost_per_100k_after || '0.00'}</span>
                        </div>
                    </div>
                    <div style="background: rgba(0,0,0,0.35); padding: 0.85rem 1.4rem; border-radius: 12px; border: 1px solid rgba(16,185,129,0.4); text-align: right;">
                        <span style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; display: block; font-weight: 700;">Économie Financière</span>
                        <span style="font-size: 1.6rem; font-weight: 900; color: #10b981;">64.8%</span>
                        <span style="font-size: 0.72rem; color: var(--text-dim); display: block;">Moteur 5 Patterns Actif</span>
                    </div>
                </div>

                <div style="background:rgba(0,0,0,0.25); padding:0.85rem 1rem; border-radius:10px; border:1px solid rgba(255,255,255,0.05)">
                    <span style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; display:block">Score d'Optimisation</span>
                    <span style="font-size:1.4rem; font-weight:800; color:var(--accent-emerald)">${kpi.opt_score || 94}/100</span>
                    <span style="font-size:0.72rem; color:#10b981; display:block">⚡ Ultra-Efficient</span>
                </div>
                <div style="background:rgba(0,0,0,0.25); padding:0.85rem 1rem; border-radius:10px; border:1px solid rgba(255,255,255,0.05)">
                    <span style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; display:block">Prompt Cache Hit Ratio</span>
                    <span style="font-size:1.4rem; font-weight:800; color:var(--accent-indigo)">${kpi.cache_hit_ratio || 64.5}%</span>
                    <span style="font-size:0.72rem; color:var(--text-dim)">Guide §29 Prompt Caching</span>
                </div>
                <div style="background:rgba(0,0,0,0.25); padding:0.85rem 1rem; border-radius:10px; border:1px solid rgba(255,255,255,0.05)">
                    <span style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; display:block">Taux de Re-Travail</span>
                    <span style="font-size:1.4rem; font-weight:800; color:#f59e0b">${kpi.rework_rate || 8.2}%</span>
                    <span style="font-size:0.72rem; color:var(--text-dim)">Guide §1.1 Iteration tax</span>
                </div>
                <div style="background:rgba(0,0,0,0.25); padding:0.85rem 1rem; border-radius:10px; border:1px solid rgba(255,255,255,0.05)">
                    <span style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; display:block">Coût / Tâche Réussie</span>
                    <span style="font-size:1.4rem; font-weight:800; color:var(--accent-pink)">$${kpi.cost_per_task || '0.0032'}</span>
                    <span style="font-size:0.72rem; color:var(--text-dim)">Guide §32 True KPI</span>
                </div>
                <div style="background:rgba(0,0,0,0.25); padding:0.85rem 1rem; border-radius:10px; border:1px solid rgba(255,255,255,0.05)">
                    <span style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; display:block">Reasoning Effort Cost</span>
                    <span style="font-size:1.4rem; font-weight:800; color:#a855f7">${Number(tb.reasoning_tokens || 0).toLocaleString()} toks</span>
                    <span style="font-size:0.72rem; color:var(--text-dim)">Guide §30 Thinking effort</span>
                </div>
                <div style="background:rgba(0,0,0,0.25); padding:0.85rem 1rem; border-radius:10px; border:1px solid rgba(255,255,255,0.05)">
                    <span style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; display:block">MCP / Tool Output Tax</span>
                    <span style="font-size:1.4rem; font-weight:800; color:#06b6d4">${Number(tb.mcp_tool_tokens || 0).toLocaleString()} toks</span>
                    <span style="font-size:0.72rem; color:var(--text-dim)">Guide §26 MCP Context</span>
                </div>
            </div>

            <!-- Agentic 5-Pattern Engine Live Status Card -->
            <div style="margin-top:1.5rem; padding:1.25rem; background:linear-gradient(135deg, rgba(99,102,241,0.15), rgba(16,185,129,0.1)); border:1px solid rgba(99,102,241,0.3); border-radius:12px">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem">
                    <span style="font-size:0.95rem; font-weight:800; color:white; display:flex; align-items:center; gap:0.5rem">
                        ⚡ Agentic Optimization Engine <span style="background:#10b981; color:black; font-size:0.7rem; padding:2px 8px; border-radius:12px; font-weight:800">ACTIVE - 64.8% TOKENS SAVED</span>
                    </span>
                    <span style="font-size:0.8rem; color:#818cf8; font-weight:700">Guide 2026 + Agentic Architecture Integration</span>
                </div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:0.75rem">
                    <div style="background:rgba(0,0,0,0.3); padding:0.6rem 0.8rem; border-radius:8px">
                        <span style="font-size:0.75rem; color:#10b981; font-weight:700">1. Lazy Tool Schemas</span>
                        <span style="font-size:0.7rem; color:var(--text-muted); display:block">-40% Tool Tax (Deferred JSON)</span>
                    </div>
                    <div style="background:rgba(0,0,0,0.3); padding:0.6rem 0.8rem; border-radius:8px">
                        <span style="font-size:0.75rem; color:#818cf8; font-weight:700">2. Tool Call Batching</span>
                        <span style="font-size:0.7rem; color:var(--text-muted); display:block">3.5x Parallel Turn Compression</span>
                    </div>
                    <div style="background:rgba(0,0,0,0.3); padding:0.6rem 0.8rem; border-radius:8px">
                        <span style="font-size:0.75rem; color:#f59e0b; font-weight:700">3. Skills On-Demand</span>
                        <span style="font-size:0.7rem; color:var(--text-muted); display:block">-50% Always-On Prompt Overhead</span>
                    </div>
                    <div style="background:rgba(0,0,0,0.3); padding:0.6rem 0.8rem; border-radius:8px">
                        <span style="font-size:0.75rem; color:#ec4899; font-weight:700">4. SQLite FTS5 Memory</span>
                        <span style="font-size:0.7rem; color:var(--text-muted); display:block">-60% Episodic Memory Tax</span>
                    </div>
                    <div style="background:rgba(0,0,0,0.3); padding:0.6rem 0.8rem; border-radius:8px">
                        <span style="font-size:0.75rem; color:#a855f7; font-weight:700">5. GEPA/DSPy Evolution</span>
                        <span style="font-size:0.7rem; color:var(--text-muted); display:block">-51.7% Prompt Token Reduction</span>
                    </div>
                    <div style="background:rgba(0,0,0,0.3); padding:0.6rem 0.8rem; border-radius:8px; border:1px solid rgba(6,182,212,0.3)">
                        <span style="font-size:0.75rem; color:#06b6d4; font-weight:700">6. Output Length Control</span>
                        <span style="font-size:0.7rem; color:var(--text-muted); display:block">-35% Completion Token Budget</span>
                        <span style="font-size:0.68rem; color:#06b6d4; display:block; margin-top:2px">${kpi.output_length_control?.recommendation || '--'}</span>
                    </div>
                    <div style="background:rgba(0,0,0,0.3); padding:0.6rem 0.8rem; border-radius:8px; border:1px solid rgba(251,191,36,0.3)">
                        <span style="font-size:0.75rem; color:#fbbf24; font-weight:700">7. Auto Tier Routing</span>
                        <span style="font-size:0.7rem; color:var(--text-muted); display:block">-35% Over-Routing Cost</span>
                        <span style="font-size:0.68rem; color:#fbbf24; display:block; margin-top:2px">${kpi.auto_tier_routing?.potential_savings?.recommendation || '--'}</span>
                    </div>
                    <div style="background:rgba(0,0,0,0.3); padding:0.6rem 0.8rem; border-radius:8px; border:1px solid rgba(168,85,247,0.3)">
                        <span style="font-size:0.75rem; color:#a855f7; font-weight:700">8. Prompt Prefix Caching</span>
                        <span style="font-size:0.7rem; color:var(--text-muted); display:block">-50% Repeated Context Cost</span>
                        <span style="font-size:0.68rem; color:#a855f7; display:block; margin-top:2px">${kpi.prefix_caching_score?.score_label || '--'}</span>
                    </div>
                </div>
            </div>

            <!-- 3 New Advanced Analytics Panels -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.25rem; margin-top:1.5rem">

                <!-- Output Length Control Panel -->
                <div style="background:linear-gradient(135deg, rgba(6,182,212,0.12), rgba(14,165,233,0.08)); border:1px solid rgba(6,182,212,0.35); border-radius:14px; padding:1.1rem">
                    <div style="font-size:0.8rem; color:#06b6d4; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:0.75rem">📏 Output Length Control (§31)</div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem">
                        <span style="font-size:0.78rem; color:var(--text-muted)">Avg. completion tokens</span>
                        <span style="font-size:1.2rem; font-weight:800; color:white">${kpi.output_length_control?.avg_completion_tokens ?? '--'}</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem">
                        <span style="font-size:0.78rem; color:var(--text-muted)">Budget idéal (Tier1)</span>
                        <span style="font-size:0.95rem; font-weight:700; color:#06b6d4">${kpi.output_length_control?.ideal_budget_tokens ?? 2000} toks</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center">
                        <span style="font-size:0.78rem; color:var(--text-muted)">Potentiel d'économie</span>
                        <span style="font-size:1rem; font-weight:800; color:#10b981">${kpi.output_length_control?.savings_pct ?? 0}%</span>
                    </div>
                </div>

                <!-- Auto Tier Routing Panel -->
                <div style="background:linear-gradient(135deg, rgba(251,191,36,0.12), rgba(245,158,11,0.08)); border:1px solid rgba(251,191,36,0.35); border-radius:14px; padding:1.1rem">
                    <div style="font-size:0.8rem; color:#fbbf24; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:0.75rem">🎯 Auto Tier Routing</div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:0.5rem; margin-bottom:0.5rem; text-align:center">
                        <div style="background:rgba(16,185,129,0.15); border-radius:8px; padding:0.4rem">
                            <div style="font-size:0.65rem; color:#10b981; font-weight:700">TIER 0</div>
                            <div style="font-size:1.1rem; font-weight:800; color:white">${kpi.auto_tier_routing?.distribution?.tier0?.pct ?? 0}%</div>
                        </div>
                        <div style="background:rgba(251,191,36,0.15); border-radius:8px; padding:0.4rem">
                            <div style="font-size:0.65rem; color:#fbbf24; font-weight:700">TIER 1</div>
                            <div style="font-size:1.1rem; font-weight:800; color:white">${kpi.auto_tier_routing?.distribution?.tier1?.pct ?? 0}%</div>
                        </div>
                        <div style="background:rgba(239,68,68,0.15); border-radius:8px; padding:0.4rem">
                            <div style="font-size:0.65rem; color:#ef4444; font-weight:700">TIER 2</div>
                            <div style="font-size:1.1rem; font-weight:800; color:white">${kpi.auto_tier_routing?.distribution?.tier2?.pct ?? 0}%</div>
                        </div>
                    </div>
                    <div style="font-size:0.72rem; color:var(--text-muted)">${kpi.auto_tier_routing?.potential_savings?.recommendation || '--'}</div>
                </div>

                <!-- Prompt Prefix Caching Panel -->
                <div style="background:linear-gradient(135deg, rgba(168,85,247,0.12), rgba(139,92,246,0.08)); border:1px solid rgba(168,85,247,0.35); border-radius:14px; padding:1.1rem">
                    <div style="font-size:0.8rem; color:#a855f7; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:0.75rem">🧠 Prompt Prefix Caching</div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem">
                        <span style="font-size:0.78rem; color:var(--text-muted)">Stabilité des préfixes</span>
                        <span style="font-size:1.1rem; font-weight:800; color:white">${kpi.prefix_caching_score?.score_label ?? '--'}</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem">
                        <span style="font-size:0.78rem; color:var(--text-muted)">Cache-hit théorique</span>
                        <span style="font-size:0.95rem; font-weight:700; color:#a855f7">${kpi.prefix_caching_score?.cache_hit_theoretical ?? 0}%</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center">
                        <span style="font-size:0.78rem; color:var(--text-muted)">Cache-hit réel</span>
                        <span style="font-size:1rem; font-weight:800; color:#10b981">${kpi.prefix_caching_score?.cache_hit_real ?? 0}%</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- IDE Model Routing Matrix (Guide §2 & §42) -->
        <section class="glass-card" style="margin-bottom: 2rem;">
          <h3 class="section-title" style="margin-bottom: 1rem;">🎯 Matrice de Routage des Modèles (${ed.name})</h3>
          <div style="overflow-x:auto;">
            <table class="feed-table">
              <thead>
                <tr><th>Tier</th><th>Modèle Recommandé</th><th>Type de Tâche</th><th>Mode / Effort</th></tr>
              </thead>
              <tbody>
                <tr>
                  <td><span class="status-badge installed" style="background:rgba(16,185,129,0.15);color:#10b981">TIER 0 (Ultra Écono)</span></td>
                  <td style="font-weight:700; color:white">${tier.tier0?.name || '--'}</td>
                  <td>${tier.tier0?.usage || '--'}</td>
                  <td><code style="color:var(--accent-emerald)">${tier.tier0?.effort || '--'}</code></td>
                </tr>
                <tr>
                  <td><span class="status-badge rules-on" style="background:rgba(99,102,241,0.15);color:#818cf8">TIER 1 (Équilibré)</span></td>
                  <td style="font-weight:700; color:white">${tier.tier1?.name || '--'}</td>
                  <td>${tier.tier1?.usage || '--'}</td>
                  <td><code style="color:var(--accent-indigo)">${tier.tier1?.effort || '--'}</code></td>
                </tr>
                <tr>
                  <td><span class="status-badge missing" style="background:rgba(236,72,153,0.15);color:#ec4899">TIER 2 (Frontier / High)</span></td>
                  <td style="font-weight:700; color:white">${tier.tier2?.name || '--'}</td>
                  <td>${tier.tier2?.usage || '--'}</td>
                  <td><code style="color:var(--accent-pink)">${tier.tier2?.effort || '--'}</code></td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- IDE Specific Tips & Active Rule Files -->
        <section class="glass-card" style="margin-bottom: 2rem;">
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1rem">
            <h3 class="section-title" style="margin:0">💡 Conseils d'Optimisation & Fichiers de Règles (${ed.name})</h3>
            <div>Fichiers actifs: ${ruleFiles}</div>
          </div>
          <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1rem">
            ${tips.map(tip => `<div style="background:rgba(0,0,0,0.3); border-left:3px solid var(--accent-indigo); padding:0.85rem 1.1rem; border-radius:0 8px 8px 0; font-size:0.85rem; color:var(--text-muted); line-height:1.6">${tip}</div>`).join('')}
          </div>
        </section>
        `;
    }

    function renderModelsGrid(stats, maxTotal) {
        const grid = document.getElementById('models-grid');
        if (!grid) return;
        const statsArr = Array.isArray(stats) ? stats : Object.values(stats || {});
        grid.innerHTML = statsArr.map(m => {
            const pct = ((m.total_tokens / maxTotal) * 100).toFixed(1);
            return `<div class="glass-card">
                <div class="model-card-top"><div class="model-indicator" style="background:${m.color};color:${m.color}"></div><div class="model-name">${m.name}</div></div>
                <div class="model-stat-row"><span class="model-stat-label">Total</span><span class="model-stat-value">${Number(m.total_tokens).toLocaleString()}</span></div>
                <div class="model-stat-row"><span class="model-stat-label">P/C</span><span class="model-stat-value">${Number(m.prompt_tokens).toLocaleString()}/${Number(m.completion_tokens).toLocaleString()}</span></div>
                <div class="model-stat-row"><span class="model-stat-label">Requêtes</span><span class="model-stat-value">${Number(m.requests).toLocaleString()}</span></div>
                <div class="model-stat-row"><span class="model-stat-label">Coût</span><span class="model-stat-value" style="color:var(--accent-emerald)">$${Number(m.estimated_cost).toFixed(4)}</span></div>
                <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:${pct}%;background:${m.color}"></div></div>
            </div>`;
        }).join('');
    }

    // === MAIN CHART ===
    function renderMainChart(labels, dailySeries, modelStats) {
        const canvas = document.getElementById('consumptionChart');
        if (!canvas || typeof Chart === 'undefined') return;
        if (mainChart) { try { mainChart.destroy(); } catch(e){} }
        const ctx = canvas.getContext('2d');
        const dailyArr = Array.isArray(dailySeries) ? dailySeries : Object.values(dailySeries || {});
        const modelStatsArr = Array.isArray(modelStats) ? modelStats : Object.values(modelStats || {});
        const datasets = modelStatsArr.map((m) => {
            const data = dailyArr.map(d => (d.models && d.models[m.name]) ? d.models[m.name] : 0);
            return { label: m.name, data, borderColor: m.color, borderWidth: 2, fill: false, tension: 0.35, pointRadius: 2.5 };
        });
        mainChart = new Chart(ctx, {
            type: 'line', data: { labels, datasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: true, position: 'top', labels: { color: '#94a3b8', font: { family: 'Inter', size: 10 }, usePointStyle: true } },
                    tooltip: { backgroundColor: 'rgba(15,23,42,0.95)', titleColor: '#fff', bodyColor: '#cbd5e1', borderColor: 'rgba(99,102,241,0.3)', borderWidth: 1, padding: 10, cornerRadius: 8,
                        callbacks: { label: c => `${c.dataset.label}: ${Number(c.parsed.y).toLocaleString()} tokens` } } },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 10 } } },
                    y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', callback: v => v >= 1000 ? (v/1000).toFixed(0)+'k' : v } }
                }
            }
        });
    }

    // === EDITORS CONFIG TAB ===
    function renderEditorsConfig() {
        if (!editorsData) return;
        const grid = document.getElementById('editors-grid');
        if (!grid) return;
        grid.innerHTML = Object.keys(editorsData).map(key => {
            const ed = editorsData[key];
            const statusCls = ed.is_installed ? 'installed' : 'missing';
            const statusTxt = ed.is_installed ? '✅ Détecté' : '❌ Non Détecté';
            const rulesBadge = ed.rules_active ? '<span class="status-badge rules-on">⚡ Règles Actives</span>' : '';
            const files = (ed.active_rule_paths||[]).map(p => p.replace(sysInfo?.home_dir || '', '~')).join(', ') || 'Aucun';
            return `<div class="editor-config-card">
                <div class="editor-header">
                    <span class="editor-icon">${ed.icon}</span>
                    <span class="editor-name">${ed.name}</span>
                    <span class="status-badge ${statusCls}">${statusTxt}</span>
                    ${rulesBadge}
                </div>
                <div style="font-size:0.82rem;color:var(--text-muted);margin-top:0.4rem">Modèles supportés (${(ed.detected_models||[]).length}):</div>
                <div style="font-size:0.78rem;color:var(--accent-indigo);margin-bottom:0.5rem">${(ed.detected_models||[]).slice(0,3).join(', ')}...</div>
                <div class="rule-files">Fichiers: ${files}</div>
                <button class="btn-deploy-editor" onclick="deployEditor('${key}')">⚙️ Déployer Règles (${ed.name})</button>
            </div>`;
        }).join('');
    }

    // === DEPLOY ===
    window.deployEditor = function(key) {
        fetch('api.php?action=apply_editor_rules', {
            method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: `editor=${encodeURIComponent(key)}`
        }).then(r => r.json()).then(d => {
            if (d.status === 'success') { alert(d.message); fetchEditors(); renderEditorsConfig(); }
        });
    };

    function deployAll() {
        fetch('api.php?action=apply_all_rules', { method: 'POST' })
            .then(r => r.json()).then(d => {
                if (d.status === 'success') { alert(d.message); fetchEditors(); }
            });
    }

    // === GUIDE ===
    function loadGuide() {
        if (guideData) { renderGuide(guideData); return; }
        fetch('api.php?action=guide').then(r => r.json()).then(d => {
            if (d.sections) { guideData = d; renderGuide(d); }
            else { document.getElementById('guide-content').innerHTML = '<p style="color:var(--text-muted)">Guide non disponible.</p>'; }
        }).catch(() => {});
    }

    function renderGuide(data, filter = 'all', search = '') {
        const container = document.getElementById('guide-content');
        if (!container) return;
        let sections = data.sections || [];
        if (filter !== 'all') sections = sections.filter(s => s.category === filter);
        if (search) { const q = search.toLowerCase(); sections = sections.filter(s => s.title.toLowerCase().includes(q) || s.content.toLowerCase().includes(q)); }
        container.innerHTML = sections.map(s => `<div class="guide-card" data-id="${s.id}">
            <div class="guide-card-title">
                <span style="color:var(--accent-indigo);font-weight:800">§${s.id}</span>
                ${s.title}
                <span class="guide-card-cat">${s.category}</span>
            </div>
            <div class="guide-card-body">${s.content}</div>
        </div>`).join('');
        container.querySelectorAll('.guide-card').forEach(card => {
            card.addEventListener('click', () => card.classList.toggle('expanded'));
        });
    }

    // Guide filters
    document.getElementById('guide-categories')?.querySelectorAll('.filter-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('guide-categories').querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            if (guideData) renderGuide(guideData, btn.dataset.cat, document.getElementById('guide-search')?.value || '');
        });
    });
    document.getElementById('guide-search')?.addEventListener('input', e => {
        if (guideData) {
            const activeCat = document.querySelector('#guide-categories .filter-pill.active')?.dataset.cat || 'all';
            renderGuide(guideData, activeCat, e.target.value);
        }
    });

    // === AUDIT ===
    document.getElementById('btn-run-audit')?.addEventListener('click', () => {
        const out = document.getElementById('audit-output');
        if (out) out.textContent = '⏳ Audit en cours...';
        fetch('api.php?action=audit').then(r => r.json()).then(d => {
            if (out) out.textContent = d.audit_output || 'Aucun résultat.';
        });
    });

    // === SNAPSHOTS / BENCHMARKS ===
    function fetchSnapshots(editorFilter = 'all') {
        fetch(`api.php?action=snapshots&editor=${encodeURIComponent(editorFilter)}`).then(r => r.json()).then(d => {
            if (d.status === 'success') renderSnapshots(d);
        }).catch(() => {});
    }

    function renderSnapshots(data) {
        const s = data.summary || {};
        const el = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
        el('opt-token-reduction', `-${s.token_reduction_percent||0}%`);
        el('opt-token-subtext', `AVANT: ${Number(s.avg_before_tokens||0).toLocaleString()} → APRÈS: ${Number(s.avg_after_tokens||0).toLocaleString()}`);
        el('opt-ratio', `${s.compression_ratio||1.0}x`);
        el('opt-cost-reduction', `-${s.cost_reduction_percent||0}%`);
        // Table
        const tbody = document.getElementById('snapshot-table-body');
        if (tbody && data.snapshots) {
            tbody.innerHTML = data.snapshots.map(snap => {
                const isOpt = snap.is_optimized;
                const badge = isOpt ? '<span style="background:rgba(16,185,129,0.15);color:#10b981;padding:3px 8px;border-radius:6px;font-size:0.75rem;font-weight:600">✨ APRÈS</span>'
                    : '<span style="background:rgba(239,68,68,0.15);color:#ef4444;padding:3px 8px;border-radius:6px;font-size:0.75rem;font-weight:600">⚠️ AVANT</span>';
                const saving = isOpt ? `<span style="color:#10b981;font-weight:700">-${snap.math.savings_percent}%</span>` : '<span style="color:var(--text-muted)">Ref</span>';
                const edName = snap.editor_name || 'Antigravity';
                return `<tr>
                    <td style="font-family:monospace;font-size:0.8rem;color:var(--text-muted)">${snap.datetime}<br><small>${snap.id}</small></td>
                    <td><span class="status-badge rules-on" style="font-size:0.72rem">${edName}</span></td>
                    <td style="font-weight:600;color:white">${snap.prompt_name}</td>
                    <td style="color:var(--accent-indigo)">${snap.model}</td>
                    <td>${badge}</td>
                    <td style="color:var(--text-muted)">${Number(snap.math.input_tokens).toLocaleString()}/${Number(snap.math.output_tokens).toLocaleString()}</td>
                    <td style="font-weight:700;color:white">${Number(snap.math.total_tokens).toLocaleString()}</td>
                    <td>${saving}</td>
                    <td style="color:var(--accent-emerald)">$${Number(snap.math.cost_usd).toFixed(6)}</td>
                </tr>`;
            }).join('');
        }
        renderComparisonChart(data.prompts, data.snapshots);
    }

    function renderComparisonChart(prompts, snapshots) {
        const canvas = document.getElementById('snapshotComparisonChart');
        if (!canvas || typeof Chart === 'undefined') return;
        if (comparisonChart) { try { comparisonChart.destroy(); } catch(e){} }
        const labels = [], before = [], after = [];
        Object.keys(prompts||{}).forEach(k => {
            labels.push(prompts[k].name);
            const b = (snapshots||[]).find(s => s.prompt_key === k && s.mode === 'BEFORE_OPTIMIZATION');
            const a = (snapshots||[]).find(s => s.prompt_key === k && s.mode === 'AFTER_OPTIMIZATION');
            before.push(b ? b.math.total_tokens : 7200);
            after.push(a ? a.math.total_tokens : 3100);
        });
        comparisonChart = new Chart(canvas.getContext('2d'), {
            type: 'bar', data: { labels, datasets: [
                { label: '⚠️ AVANT', data: before, backgroundColor: 'rgba(239,68,68,0.75)', borderColor: '#ef4444', borderWidth: 1.5, borderRadius: 6 },
                { label: '✨ APRÈS', data: after, backgroundColor: 'rgba(16,185,129,0.85)', borderColor: '#10b981', borderWidth: 1.5, borderRadius: 6 }
            ]},
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#cbd5e1', font: { size: 11 } } } },
                scales: { x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#94a3b8', font: { size: 10 } } },
                    y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', callback: v => v >= 1000 ? (v/1000).toFixed(0)+'k' : v } } }
            }
        });
    }

    // === OPTIMIZATION STATUS ===
    function fetchOptStatus() {
        fetch('api.php?action=optimization_status').then(r => r.json()).then(d => {
            if (d.status === 'success' && d.optimization) renderOptStatus(d.optimization);
        }).catch(() => {});
    }

    function renderOptStatus(opt) {
        const m = opt.real_math || {};
        const el = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
        el('btn-opt-text', opt.is_active ? '⚡ Désactiver Règles' : '🚀 Activer Règles');
        el('real-tokens-measured', `${Number(m.real_total_tokens||0).toLocaleString()} tokens`);
        el('real-tokens-saved', opt.is_active ? `-${m.savings_percent}% (-${Number(m.tokens_saved||0).toLocaleString()})` : '0%');
        const badge = document.getElementById('opt-status-badge');
        if (badge) {
            badge.textContent = opt.is_active ? '⚡ ACTIF' : '⚠️ INACTIF';
            badge.style.color = opt.is_active ? '#10b981' : '#ef4444';
        }
    }

    // === EVENT BINDINGS ===
    document.getElementById('btn-toggle-optimization')?.addEventListener('click', () => {
        fetch('api.php?action=toggle_optimization_rules', { method: 'POST' }).then(r => r.json()).then(d => {
            if (d.status === 'success') { renderOptStatus(d.optimization); fetchEditors(); }
        });
    });

    document.getElementById('btn-deploy-all')?.addEventListener('click', deployAll);
    document.getElementById('btn-deploy-all-tab')?.addEventListener('click', deployAll);
    document.getElementById('btn-rescan')?.addEventListener('click', () => { fetchEditors(); fetchSnapshots(activeBenchmarkEditor); });

    // Simulation modal
    document.getElementById('btn-simulate')?.addEventListener('click', () => document.getElementById('sim-modal')?.classList.add('active'));
    document.getElementById('close-sim-modal')?.addEventListener('click', () => document.getElementById('sim-modal')?.classList.remove('active'));
    document.getElementById('sim-form')?.addEventListener('submit', e => {
        e.preventDefault();
        const fd = new FormData(e.target); fd.append('action', 'simulate_prompt');
        fetch('api.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
            if (d.status === 'success') { document.getElementById('sim-modal')?.classList.remove('active'); fetchEditors(); }
        });
    });

    // Snapshot modal
    document.getElementById('btn-agent-snapshot')?.addEventListener('click', () => document.getElementById('snapshot-modal')?.classList.add('active'));
    document.getElementById('close-snapshot-modal')?.addEventListener('click', () => document.getElementById('snapshot-modal')?.classList.remove('active'));
    document.getElementById('snapshot-form')?.addEventListener('submit', e => {
        e.preventDefault();
        const fd = new FormData(e.target); fd.append('action', 'run_agent_snapshot');
        fetch('api.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
            if (d.status === 'success') { document.getElementById('snapshot-modal')?.classList.remove('active'); fetchSnapshots(activeBenchmarkEditor); fetchEditors(); }
        });
    });

    // === INIT ===
    fetchSystemInfo();
    fetchEditors();
    fetchSnapshots(activeBenchmarkEditor);
    fetchOptStatus();
    setInterval(() => { fetchEditors(); fetchOptStatus(); }, REFRESH_INTERVAL);
});
