// Citizen Portal JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the page
    loadDashboardData();
    setupEventListeners();
    populateProjectSelects();
});

function setupEventListeners() {
    // Navigation
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const pageName = this.getAttribute('data-page');
            changePage(pageName);
        });
    });

    // Filters
    const projectSearch = document.getElementById('projectSearch');
    const statusFilter = document.getElementById('statusFilter');
    
    if (projectSearch) projectSearch.addEventListener('input', debounce(loadProjects, 300));
    if (statusFilter) statusFilter.addEventListener('change', loadProjects);

    // Forms
    const feedbackForm = document.getElementById('feedbackForm');
    if (feedbackForm) {
        feedbackForm.addEventListener('submit', handleFeedbackSubmit);
    }
}

function changePage(pageName) {
    // Hide all pages
    document.querySelectorAll('.page-section').forEach(page => {
        page.style.display = 'none';
    });

    // Show selected page
    const targetPage = document.getElementById('page-' + pageName);
    if (targetPage) {
        targetPage.style.display = 'block';
    }

    // Update active nav item
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('data-page') === pageName) {
            item.classList.add('active');
        }
    });

    // Load page-specific data
    if (pageName === 'projects') {
        loadProjects();
    } else if (pageName === 'project-status') {
        loadProjectStatus();
    } else if (pageName === 'track-feedback') {
        loadTrackedFeedback();
    } else if (pageName === 'transparency') {
        loadTransparencyDashboard();
    }

    // Scroll to top
    document.querySelector('.content').scrollTop = 0;
}

function loadDashboardData() {
    fetch('/ipms.lgu/citizen/api/dashboard.php')
        .then(res => res.json())
        .then(data => {
            // Update KPI cards
            document.getElementById('activeProjectsCount').textContent = data.stats.active_projects;
            document.getElementById('completedProjectsCount').textContent = data.stats.completed_projects;
            document.getElementById('delayedProjectsCount').textContent = data.stats.delayed_projects;
            document.getElementById('mySubmissionsCount').textContent = data.stats.my_submissions;

            // Load recent projects
            displayRecentProjects(data.recent_projects);

            // Load recent feedback
            displayRecentFeedback(data.recent_feedback);
        })
        .catch(err => console.error('Error loading dashboard:', err));
}

function loadProjects() {
    const searchTerm = document.getElementById('projectSearch')?.value || '';
    const statusFilter = document.getElementById('statusFilter')?.value || '';

    const params = new URLSearchParams();
    if (searchTerm) params.append('search', searchTerm);
    if (statusFilter) params.append('status', statusFilter);

    fetch('/ipms.lgu/citizen/api/projects.php?' + params)
        .then(res => res.json())
        .then(data => {
            displayProjects(data.projects);
        })
        .catch(err => console.error('Error loading projects:', err));
}

function loadProjectStatus() {
    fetch('/ipms.lgu/citizen/api/project-status.php')
        .then(res => res.json())
        .then(data => {
            displayProjectStatus(data.projects);
        })
        .catch(err => console.error('Error loading project status:', err));
}

function loadTrackedFeedback() {
    fetch('/ipms.lgu/citizen/api/my-feedback.php')
        .then(res => res.json())
        .then(data => {
            displayTrackedFeedback(data.feedback);
        })
        .catch(err => console.error('Error loading feedback:', err));
}

function loadTransparencyDashboard() {
    fetch('/ipms.lgu/citizen/api/transparency.php')
        .then(res => res.json())
        .then(data => {
            document.getElementById('totalBudget').textContent = formatCurrency(data.stats.total_budget);
            document.getElementById('totalExpenses').textContent = formatCurrency(data.stats.total_expenses);
            document.getElementById('budgetRemaining').textContent = formatCurrency(data.stats.budget_remaining);
            document.getElementById('onTimeProjects').textContent = data.stats.on_time_projects;

            displayExpenses(data.expenses);
        })
        .catch(err => console.error('Error loading transparency data:', err));
}

function populateProjectSelects() {
    fetch('/ipms.lgu/citizen/api/projects.php?all=1')
        .then(res => res.json())
        .then(data => {
            const select = document.getElementById('feedbackProject');
            if (select) {
                select.innerHTML = '<option value="">Select a project</option>';
                data.projects.forEach(project => {
                    const option = document.createElement('option');
                    option.value = project.id;
                    option.textContent = project.name + ' - ' + project.location;
                    select.appendChild(option);
                });
            }
        })
        .catch(err => console.error('Error loading projects:', err));
}

function displayRecentProjects(projects) {
    const container = document.getElementById('recentProjectsContainer');
    if (!container) return;

    if (projects.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">No recent projects</p>';
        return;
    }

    container.innerHTML = projects.slice(0, 3).map(project => createProjectCard(project)).join('');
}

function displayProjects(projects) {
    const container = document.getElementById('projectsGridContainer');
    if (!container) return;

    if (projects.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem; grid-column: 1/-1;">No projects found</p>';
        return;
    }

    container.innerHTML = projects.map(project => createProjectCard(project)).join('');
}

function createProjectCard(project) {
    const progress = project.progress || 0;
    const statusClass = 'badge-' + project.status;
    
    return `
        <div class="project-card">
            <span class="project-badge ${statusClass}">${capitalizeFirst(project.status)}</span>
            <div class="project-name">${escapeHtml(project.name)}</div>
            <div class="project-location">📍 ${escapeHtml(project.location || 'N/A')}</div>
            <div class="project-meta">
                <span>Budget: ₱${formatNumber(project.budget)}</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: ${progress}%"></div>
            </div>
            <div class="project-meta">
                <span>Progress: ${progress}%</span>
                <span>${formatDate(project.start_date)} to ${formatDate(project.end_date)}</span>
            </div>
        </div>
    `;
}

function displayProjectStatus(projects) {
    const container = document.getElementById('projectStatusContainer');
    if (!container) return;

    if (projects.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">No projects to display</p>';
        return;
    }

    container.innerHTML = projects.map(project => `
        <div class="status-item ${project.status}">
            ${project.latest_photo_path ? `
                <img class="status-photo" src="${window.BASE_PATH || '/ipms.lgu/'}${escapeHtml(project.latest_photo_path)}" alt="${escapeHtml(project.latest_photo_title || project.name)}">
            ` : ''}
            <div>
                <div class="feedback-title">${escapeHtml(project.name)}</div>
                <div class="feedback-message">${escapeHtml(project.description || 'No description')}</div>
            </div>
            <div class="status-meta">
                <div class="meta-item">
                    <span class="meta-label">Status</span>
                    <span class="meta-value">${capitalizeFirst(project.status)}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Progress</span>
                    <span class="meta-value">${project.progress}%</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Budget</span>
                    <span class="meta-value">₱${formatNumber(project.budget)}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">End Date</span>
                    <span class="meta-value">${formatDate(project.end_date)}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Delays</span>
                    <span class="meta-value">${project.delay_reports || 0}</span>
                </div>
            </div>
        </div>
    `).join('');
}

function displayRecentFeedback(feedback) {
    const container = document.getElementById('recentFeedbackContainer');
    if (!container) return;

    if (feedback.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">No submissions yet. Submit your first feedback!</p>';
        return;
    }

    container.innerHTML = feedback.slice(0, 3).map(item => createFeedbackItem(item)).join('');
}

function displayTrackedFeedback(feedback) {
    const container = document.getElementById('trackedFeedbackContainer');
    if (!container) return;

    if (feedback.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">No feedback submissions yet</p>';
        return;
    }

    container.innerHTML = feedback.map(item => createFeedbackItem(item)).join('');
}

function createFeedbackItem(item) {
    const statusClass = 'status-' + item.status;
    const priorityColor = {
        'low': '#3498db',
        'medium': '#f39c12',
        'high': '#e74c3c',
        'urgent': '#c0392b'
    }[item.priority] || '#666';

    return `
        <div class="feedback-item">
            <div>
                <div class="feedback-header">
                    <div class="feedback-title">${escapeHtml(item.project_name || 'General Feedback')}</div>
                    <span class="feedback-status ${statusClass}">${capitalizeFirst(item.status)}</span>
                </div>
                <div class="feedback-message">${escapeHtml(item.message)}</div>
                <div class="feedback-meta">
                    <span style="color: ${priorityColor}; font-weight: 600;">● ${capitalizeFirst(item.priority)} Priority</span>
                    <span>📅 ${formatDate(item.created_at)}</span>
                    <span>📋 ${capitalizeFirst(item.category)}</span>
                </div>
            </div>
        </div>
    `;
}

function displayExpenses(expenses) {
    const container = document.getElementById('expensesContainer');
    if (!container) return;

    if (expenses.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">No expense data available</p>';
        return;
    }

    container.innerHTML = expenses.map(expense => `
        <div class="expense-item">
            <div class="expense-info">
                <div class="expense-project">${escapeHtml(expense.project_name)}</div>
                <div class="expense-category">${escapeHtml(expense.category)} • ${formatDate(expense.expense_date)}</div>
            </div>
            <div class="expense-amount">₱${formatNumber(expense.amount)}</div>
        </div>
    `).join('');
}

function handleFeedbackSubmit(e) {
    e.preventDefault();

    const formData = new FormData(e.target);

    fetch('/ipms.lgu/citizen/api/submit-feedback.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Thank you! Your feedback has been submitted successfully.');
            e.target.reset();
            changePage('dashboard');
            loadDashboardData();
        } else {
            alert('Error: ' + (data.message || 'Failed to submit feedback'));
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error submitting feedback');
    });
}

// Utility Functions
function formatCurrency(value) {
    return '₱' + formatNumber(value);
}

function formatNumber(num) {
    if (!num) return '0';
    return parseFloat(num).toLocaleString('en-PH', { maximumFractionDigits: 0 });
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-PH');
}

function capitalizeFirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1).replace(/_/g, ' ');
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function debounce(func, delay) {
    let timeoutId;
    return function(...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func(...args), delay);
    };
}
