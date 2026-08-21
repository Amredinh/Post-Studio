<?php
$page_title = 'Analytics';
$active = 'analytics';
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h2 class="text-xl font-bold text-white tracking-tight">Performance Overview</h2>
    <p class="text-sm text-slate-400">Track engagement and post statuses across all platforms.</p>
  </div>
  <button id="btn-refresh-analytics" class="btn btn-primary inline-flex items-center gap-2">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0h15.356m-15.356-4h15.356m-15.356 4H5.582m0 0h15.356m-15.356-4h15.356M10.5 19.5L3 12l7.5-7.5M19.5 10.5l-7.5 7.5m0 0a3 3 0 11-4.243 4.243M15 15l4.243-4.243" /></svg>
    <span>Refresh Analytics</span>
  </button>
</div>

<div id="analytics-error" class="hidden mb-6 px-4 py-3 rounded-xl text-sm text-rose-200 bg-rose-500/10 border border-rose-500/30"></div>

<div id="analytics-content" class="hidden space-y-6">
  <!-- KPI Grid -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" id="kpi-grid">
    <!-- Populated by JS -->
  </div>

  <!-- Detailed Table -->
  <div class="card overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-800/80 flex items-center justify-between bg-slate-900/50">
      <h3 class="font-semibold text-slate-200">Recent Post Performance</h3>
    </div>
    <div class="overflow-x-auto">
      <table class="table w-full">
        <thead>
          <tr>
            <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider px-6 py-3">Content</th>
            <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider px-6 py-3">Platform</th>
            <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider px-6 py-3">Status</th>
            <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider px-6 py-3">Likes</th>
            <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider px-6 py-3">Comments</th>
            <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider px-6 py-3">Views</th>
          </tr>
        </thead>
        <tbody id="analytics-tbody" class="divide-y divide-slate-800/50">
          <!-- Populated by JS -->
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="analytics-loading" class="flex flex-col items-center justify-center py-20 text-slate-500 space-y-4 hidden">
  <div class="w-8 h-8 rounded-full border-4 border-violet-500/30 border-t-violet-500 animate-spin"></div>
  <p class="text-sm font-medium">Fetching latest metrics...</p>
</div>

<div id="analytics-empty" class="flex flex-col items-center justify-center py-20 bg-slate-900/20 border border-slate-800/50 rounded-2xl">
  <div class="text-4xl mb-4">📊</div>
  <h3 class="text-lg font-medium text-slate-300 mb-2">No Data Available</h3>
  <p class="text-sm text-slate-500 max-w-md text-center">Click the refresh button above to fetch the latest analytics data from your connected social platforms.</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnRefresh = document.getElementById('btn-refresh-analytics');
    const content = document.getElementById('analytics-content');
    const loading = document.getElementById('analytics-loading');
    const empty = document.getElementById('analytics-empty');
    const errorEl = document.getElementById('analytics-error');
    const kpiGrid = document.getElementById('kpi-grid');
    const tbody = document.getElementById('analytics-tbody');

    const escapeHTML = (str) => {
        if (!str) return '';
        return String(str).replace(/[&<>'"]/g, match => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
        })[match]);
    };

    btnRefresh.addEventListener('click', async () => {
        btnRefresh.disabled = true;
        btnRefresh.querySelector('span').textContent = 'Refreshing...';
        btnRefresh.classList.add('opacity-75');

        content.classList.add('hidden');
        empty.classList.add('hidden');
        errorEl.classList.add('hidden');
        loading.classList.remove('hidden');

        try {
            const res = await fetch('ajax/refresh_analytics.php');
            const data = await res.json();
            
            if (!res.ok || !data.ok) {
                throw new Error(data.error || 'Failed to load analytics');
            }

            loading.classList.add('hidden');
            
            if (!data.posts || data.posts.length === 0) {
                empty.classList.remove('hidden');
                empty.querySelector('h3').textContent = 'No Posts Found';
                empty.querySelector('p').textContent = 'Make sure you have created posts to see analytics data.';
                return;
            }

            // Render KPIs
            const kpis = [
                { label: 'Total Posts', value: data.stats.total, color: 'text-violet-400', bg: 'bg-violet-500/10' },
                { label: 'Published', value: data.stats.published, color: 'text-emerald-400', bg: 'bg-emerald-500/10' },
                { label: 'Failed', value: data.stats.failed, color: 'text-rose-400', bg: 'bg-rose-500/10' },
                { label: 'Total Reach', value: data.stats.reach, color: 'text-sky-400', bg: 'bg-sky-500/10' }
            ];

            kpiGrid.innerHTML = kpis.map(kpi => `
                <div class="card p-5 border border-slate-800/80 bg-slate-900/60 shadow-lg shadow-black/20 rounded-2xl flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center ${kpi.bg} ${kpi.color}">
                        <div class="text-xl font-bold">${kpi.value}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-slate-400">${kpi.label}</div>
                    </div>
                </div>
            `).join('');

            // Render Table
            tbody.innerHTML = data.posts.map(p => {
                const serviceTag = p.service === 'bulkpublish' 
                    ? '<span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-fuchsia-500/15 text-fuchsia-300 border border-fuchsia-500/25">BP</span>' 
                    : '<span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-violet-500/15 text-violet-300 border border-violet-500/25">ZN</span>';
                
                const statusDot = p.status === 'published' ? 'bg-emerald-400' : (p.status === 'failed' ? 'bg-rose-400' : 'bg-amber-400');
                
                return `
                <tr class="hover:bg-slate-800/30 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            ${serviceTag}
                            <div class="text-sm font-medium text-slate-200 truncate max-w-xs">${escapeHTML(p.content) || '<span class="text-slate-500 italic">No Content</span>'}</div>
                        </div>
                        <div class="text-[10px] text-slate-500 font-mono mt-1">${escapeHTML(p._id)}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-wrap gap-1">
                            ${p.platforms.map(pl => `<span class="inline-flex items-center text-[10px] px-2 py-0.5 rounded border border-slate-700 text-slate-300 bg-slate-800/50 uppercase font-semibold">${escapeHTML(pl.platform)}</span>`).join('')}
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border border-slate-700/50 bg-slate-800/50">
                            <span class="w-1.5 h-1.5 rounded-full ${statusDot}"></span>
                            <span class="text-slate-200 capitalize">${escapeHTML(p.status)}</span>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300 font-medium">${p.metrics.likes}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300 font-medium">${p.metrics.comments}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300 font-medium">${p.metrics.views}</td>
                </tr>
                `;
            }).join('');

            content.classList.remove('hidden');

        } catch (err) {
            loading.classList.add('hidden');
            errorEl.textContent = err.message;
            errorEl.classList.remove('hidden');
            empty.classList.remove('hidden');
        } finally {
            btnRefresh.disabled = false;
            btnRefresh.querySelector('span').textContent = 'Refresh Analytics';
            btnRefresh.classList.remove('opacity-75');
        }
    });

    // Auto-trigger refresh on page load
    btnRefresh.click();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
