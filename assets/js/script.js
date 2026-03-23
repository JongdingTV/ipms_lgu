/* ========================================================
   LGU Infrastructure Dashboard — app.js
   ======================================================== */

/* ── Animated counters ── */
function animateCounter(el, target, isBudget = false) {
  const duration = 1200;
  const start = performance.now();

  function tick(now) {
    const elapsed = Math.min((now - start) / duration, 1);
    const ease = 1 - Math.pow(1 - elapsed, 3); // ease-out-cubic
    const value = ease * target;

    if (isBudget) {
      el.textContent = value.toFixed(1);
    } else {
      el.textContent = Math.round(value);
    }

    if (elapsed < 1) requestAnimationFrame(tick);
    else el.textContent = isBudget ? target.toFixed(1) : target;
  }

  requestAnimationFrame(tick);
}

function initCounters() {
  document.querySelectorAll('.kpi-value[data-target]').forEach(el => {
    const target = parseFloat(el.dataset.target);
    const isBudget = el.classList.contains('kpi-peso');
    animateCounter(el, target, isBudget);
  });
}

/* ── Progress Line Chart ── */
function initProgressChart() {
  const ctx = document.getElementById('progressChart').getContext('2d');
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

  const planned = [8, 14, 22, 32, 44, 52, 58, 64, 70, 78, 86, 90];
  const actual  = [5, 11, 18, 27, 38, 48, 55, 61, 68, 76, 82, 85];

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: months,
      datasets: [
        {
          label: 'Planned',
          data: planned,
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59,130,246,0.08)',
          borderWidth: 2.5,
          tension: 0.4,
          fill: true,
          pointBackgroundColor: '#3b82f6',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6,
        },
        {
          label: 'Actual',
          data: actual,
          borderColor: '#22c55e',
          backgroundColor: 'rgba(34,197,94,0.06)',
          borderWidth: 2.5,
          tension: 0.4,
          fill: true,
          pointBackgroundColor: '#22c55e',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 900, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1e2a3b',
          titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12 },
          bodyFont:  { family: "'Plus Jakarta Sans', sans-serif", size: 12 },
          padding: 10,
          callbacks: {
            label: ctx => ` ${ctx.dataset.label}: ${ctx.raw}%`
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: {
            font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
            color: '#94a3b8'
          },
          border: { display: false }
        },
        y: {
          min: 0,
          max: 100,
          ticks: {
            stepSize: 25,
            font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
            color: '#94a3b8',
            callback: v => v + '%'
          },
          grid: {
            color: '#f1f5f9',
            drawTicks: false
          },
          border: { display: false, dash: [4,4] }
        }
      }
    }
  });
}

/* ── Budget Donut Chart ── */
function initBudgetChart() {
  const ctx = document.getElementById('budgetChart').getContext('2d');

  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Spent', 'Remaining', 'Overspending'],
      datasets: [{
        data: [62, 28, 10],
        backgroundColor: ['#3b82f6', '#22c55e', '#ef4444'],
        borderColor: ['#fff','#fff','#fff'],
        borderWidth: 3,
        hoverOffset: 6
      }]
    },
    options: {
      responsive: false,
      cutout: '70%',
      animation: { duration: 900, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1e2a3b',
          titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12 },
          bodyFont:  { family: "'Plus Jakarta Sans', sans-serif", size: 12 },
          padding: 10,
          callbacks: {
            label: ctx => ` ${ctx.label}: ${ctx.raw}%`
          }
        }
      }
    }
  });
}

/* ── Sidebar toggle ── */
function initSidebarToggle() {
  const btn     = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');

  btn.addEventListener('click', () => {
    sidebar.classList.toggle('open');
  });

  // Close on outside click (mobile)
  document.addEventListener('click', (e) => {
    if (
      window.innerWidth <= 768 &&
      !sidebar.contains(e.target) &&
      !btn.contains(e.target) &&
      sidebar.classList.contains('open')
    ) {
      sidebar.classList.remove('open');
    }
  });
}

/* ── Nav active state ── */
function initNav() {
  const items = document.querySelectorAll('.nav-item');
  items.forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      items.forEach(i => i.classList.remove('active'));
      item.classList.add('active');
      if (window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.remove('open');
      }
    });
  });
}

/* ── Notification panel ── */
function initNotifications() {
  const btn   = document.getElementById('notifBtn');
  const panel = document.getElementById('notifPanel');
  const clear = document.getElementById('notifClear');

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    panel.classList.toggle('open');
  });

  clear.addEventListener('click', () => {
    panel.querySelectorAll('.notif-item').forEach(el => {
      el.style.transition = 'opacity 0.3s, transform 0.3s';
      el.style.opacity = '0';
      el.style.transform = 'translateX(20px)';
      setTimeout(() => el.remove(), 300);
    });
    document.querySelector('.notif-badge').textContent = '0';
    document.querySelector('.notif-badge').style.display = 'none';
  });

  document.addEventListener('click', (e) => {
    if (!panel.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
      panel.classList.remove('open');
    }
  });
}

/* ── Modal helpers ── */
const modalOverlay = document.getElementById('modalOverlay');
const modalTitle   = document.getElementById('modalTitle');
const modalBody    = document.getElementById('modalBody');

function openModal(title, html) {
  modalTitle.textContent = title;
  modalBody.innerHTML = html;
  modalOverlay.classList.add('open');
}

function closeModal() {
  modalOverlay.classList.remove('open');
}

document.getElementById('modalClose').addEventListener('click', closeModal);
modalOverlay.addEventListener('click', (e) => {
  if (e.target === modalOverlay) closeModal();
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeModal();
});

/* ── Project detail data ── */
const projectData = {
  123: {
    name: 'Road Rehabilitation',
    location: 'Barangay 7, Main Avenue',
    contractor: 'JKL Builders',
    progress: 38,
    budget: '₱2.5M',
    spent: '₱2.1M',
    status: 'Delayed',
    delay: '12 days behind schedule',
    notes: 'Contractor cited material delivery issues. Site inspection scheduled for Friday.'
  },
  204: {
    name: 'Drainage Improvement',
    location: 'Zone 2 — Riverside District',
    contractor: 'ABC Construction',
    progress: 55,
    budget: '₱1.8M',
    spent: '₱1.3M',
    status: 'Delayed',
    delay: '7 days behind schedule',
    notes: 'Heavy rain caused work stoppage for 3 days. Revised timeline submitted.'
  },
  315: {
    name: 'Municipal Hall Renovation',
    location: 'Poblacion, Town Center',
    contractor: 'XYZ Infrastructure',
    progress: 22,
    budget: '₱4.2M',
    spent: '₱0.9M',
    status: 'Delayed',
    delay: '21 days behind schedule',
    notes: 'Permit revisions required additional compliance review.'
  }
};

function viewProject(id) {
  const p = projectData[id];
  if (!p) return;

  const progressColor = p.progress >= 60 ? '#22c55e' : p.progress >= 35 ? '#f97316' : '#ef4444';

  openModal(`Project #${id} — ${p.name}`, `
    <div style="display:flex;flex-direction:column;gap:12px;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div>
          <p style="font-size:0.72rem;color:#94a3b8;margin-bottom:2px;">LOCATION</p>
          <p style="color:#1e293b;font-weight:600;">${p.location}</p>
        </div>
        <div>
          <p style="font-size:0.72rem;color:#94a3b8;margin-bottom:2px;">CONTRACTOR</p>
          <p style="color:#1e293b;font-weight:600;">${p.contractor}</p>
        </div>
        <div>
          <p style="font-size:0.72rem;color:#94a3b8;margin-bottom:2px;">BUDGET</p>
          <p style="color:#1e293b;font-weight:600;">${p.budget}</p>
        </div>
        <div>
          <p style="font-size:0.72rem;color:#94a3b8;margin-bottom:2px;">SPENT</p>
          <p style="color:#1e293b;font-weight:600;">${p.spent}</p>
        </div>
      </div>
      <div>
        <p style="font-size:0.72rem;color:#94a3b8;margin-bottom:6px;">PROGRESS</p>
        <div style="background:#f1f5f9;border-radius:20px;height:10px;overflow:hidden;">
          <div style="width:${p.progress}%;background:${progressColor};height:100%;border-radius:20px;transition:width 0.8s ease;"></div>
        </div>
        <p style="font-size:0.75rem;color:#64748b;margin-top:4px;">${p.progress}% complete</p>
      </div>
      <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px 12px;">
        <p style="font-size:0.72rem;color:#c2410c;font-weight:700;margin-bottom:4px;">⚠ DELAY: ${p.delay}</p>
        <p style="color:#92400e;">${p.notes}</p>
      </div>
    </div>
  `);
}

function reviewAnomalies() {
  openModal('Budget Anomalies Review', `
    <div style="display:flex;flex-direction:column;gap:14px;">
      <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
          <p style="font-weight:700;color:#991b1b;">Brgy. Health Center</p>
          <span style="background:#ef4444;color:white;font-size:0.7rem;font-weight:700;padding:2px 10px;border-radius:20px;">Over Budget +35%</span>
        </div>
        <p style="color:#7f1d1d;font-size:0.82rem;">Original budget: ₱800K. Current spend: ₱1.08M. Excess of ₱280K attributed to scope change in structural repair.</p>
      </div>
      <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
          <p style="font-weight:700;color:#92400e;">River Dike Project</p>
          <span style="background:#f97316;color:white;font-size:0.7rem;font-weight:700;padding:2px 10px;border-radius:20px;">High Expense Spike</span>
        </div>
        <p style="color:#78350f;font-size:0.82rem;">Unusual material purchase of ₱320K logged on March 15. Awaiting contractor invoice verification.</p>
      </div>
      <p style="font-size:0.78rem;color:#64748b;">Flagged by AI anomaly detection. Please verify with finance officer before approving.</p>
    </div>
  `);
}

/* ── Search ── */
function initSearch() {
  const input = document.getElementById('searchInput');
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && input.value.trim()) {
      openModal(`Search: "${input.value.trim()}"`, `
        <p style="color:#64748b;">No results found for "<strong>${input.value.trim()}</strong>" in the current view.</p>
        <p style="color:#64748b;margin-top:8px;font-size:0.8rem;">Try searching for a project name, barangay, or contractor.</p>
      `);
    }
  });
}

/* ── Init ── */
document.addEventListener('DOMContentLoaded', () => {
  initCounters();
  initProgressChart();
  initBudgetChart();
  initSidebarToggle();
  initNav();
  initNotifications();
  initSearch();
});