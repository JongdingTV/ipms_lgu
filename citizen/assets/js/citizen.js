// Citizen Portal JavaScript
const CITIZEN_BASE_PATH = window.BASE_PATH || '/';

function citizenUrl(path) {
    return CITIZEN_BASE_PATH + path.replace(/^\/+/, '');
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the page
    loadDashboardData();
    setupEventListeners();

    // Deep-linking: #projects, #profile, etc. restore the page on load…
    const initialPage = (location.hash || '').replace(/^#/, '');
    if (initialPage && document.getElementById('page-' + initialPage)) {
        changePage(initialPage);
    }
});

// …and browser back/forward moves between pages.
let currentPageName = 'dashboard';
window.addEventListener('hashchange', function() {
    const page = (location.hash || '').replace(/^#/, '') || 'dashboard';
    if (page !== currentPageName && document.getElementById('page-' + page)) {
        changePage(page);
    }
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

    setupListControls();

    // Forms
    const feedbackForm = document.getElementById('feedbackForm');
    if (feedbackForm) {
        feedbackForm.addEventListener('submit', handleFeedbackSubmit);
    }

    setupIdUpload();
    setupUserMenu();
    setupLocationPicker();
    setupFeedbackPhotos();
    setupFeedbackWizard();
    setupChangePassword();
    setupProjectDetailModal();
    setupSidebarToggle();
    setupLogoutConfirm();
    setupIdleLogout();
}

// ===== Logout confirmation =====
// Every logout link opens a confirm modal first so a stray click on the
// sidebar footer doesn't silently end the session.
function setupLogoutConfirm() {
    const modal = document.getElementById('logoutConfirmModal');
    if (!modal) return;

    document.querySelectorAll('.btn-logout, .user-menu-logout').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            modal.style.display = 'flex';
        });
    });

    const closeModal = () => { modal.style.display = 'none'; };
    document.getElementById('logoutConfirmClose').addEventListener('click', closeModal);
    document.getElementById('logoutCancelBtn').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
    });
}

// ===== Inactivity auto-logout =====
// After IDLE_LOGOUT_MS with no activity the session ends. The final
// IDLE_WARNING_MS is spent on a countdown modal that only "Stay Logged In"
// can dismiss — background mouse noise won't cancel it by accident.
const IDLE_LOGOUT_MS = 5 * 60 * 1000;
const IDLE_WARNING_MS = 60 * 1000;

function setupIdleLogout() {
    const modal = document.getElementById('idleWarningModal');
    if (!modal) return;

    const countdownEl = document.getElementById('idleCountdown');
    const logoutUrl = citizenUrl('auth/logout.php') + '?timeout=1';

    let warnTimer = null;
    let countdownTimer = null;
    let warningShown = false;
    let lastReset = 0;

    const startCountdown = () => {
        warningShown = true;
        let secondsLeft = Math.round(IDLE_WARNING_MS / 1000);
        countdownEl.textContent = secondsLeft;
        modal.style.display = 'flex';
        countdownTimer = setInterval(() => {
            secondsLeft--;
            countdownEl.textContent = Math.max(secondsLeft, 0);
            if (secondsLeft <= 0) {
                clearInterval(countdownTimer);
                window.location.href = logoutUrl;
            }
        }, 1000);
    };

    const resetIdleTimer = () => {
        if (warningShown) return;
        // Activity events fire constantly; only re-arm the timer once a second.
        const now = Date.now();
        if (now - lastReset < 1000) return;
        lastReset = now;
        clearTimeout(warnTimer);
        warnTimer = setTimeout(startCountdown, IDLE_LOGOUT_MS - IDLE_WARNING_MS);
    };

    document.getElementById('idleStayBtn').addEventListener('click', () => {
        clearInterval(countdownTimer);
        modal.style.display = 'none';
        warningShown = false;
        lastReset = 0;
        resetIdleTimer();
    });

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetIdleTimer, { passive: true });
    });
    resetIdleTimer();
}

// Off-canvas sidebar for small screens. On desktop the sidebar is always
// visible and the .open class has no styles attached, so this is a no-op there.
function setupSidebarToggle() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (!toggle || !sidebar) return;

    let backdrop = document.getElementById('sidebarBackdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'sidebarBackdrop';
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);
    }

    const closeSidebar = () => {
        sidebar.classList.remove('open');
        backdrop.classList.remove('show');
    };

    toggle.addEventListener('click', () => {
        // Desktop: collapse/expand the fixed sidebar so the content gets the
        // full width. Mobile keeps the off-canvas drawer behavior below.
        if (window.matchMedia('(min-width: 769px)').matches) {
            document.body.classList.toggle('sidebar-collapsed');
            return;
        }
        const isOpen = sidebar.classList.toggle('open');
        backdrop.classList.toggle('show', isOpen);
    });
    backdrop.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeSidebar();
    });
    // Navigating closes the drawer so the chosen page is immediately visible.
    sidebar.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', closeSidebar);
    });
}

// ===== Feedback proof photos (max 4 like the CIMMS request form, 3MB each) =====
const FEEDBACK_MAX_PHOTOS = 4;
const FEEDBACK_MAX_PHOTO_BYTES = 3 * 1024 * 1024; // must match citizen/api/submit-feedback.php

function setupFeedbackPhotos() {
    const input = document.getElementById('feedbackPhotos');
    if (!input) return;

    input.addEventListener('change', () => {
        const status = document.getElementById('feedbackPhotoStatus');
        status.style.display = 'none';

        // Merge new picks with what's already selected, then re-apply limits.
        const files = Array.from(input.files);
        const valid = [];
        for (const file of files) {
            if (!file.type.startsWith('image/')) {
                showFeedbackPhotoStatus('Only image files are allowed (JPG, PNG, GIF, or WEBP).', 'error');
                continue;
            }
            if (file.size > FEEDBACK_MAX_PHOTO_BYTES) {
                showFeedbackPhotoStatus('"' + file.name + '" is over the 3MB limit and was not added.', 'error');
                continue;
            }
            valid.push(file);
        }

        if (valid.length > FEEDBACK_MAX_PHOTOS) {
            showFeedbackPhotoStatus('You can attach up to ' + FEEDBACK_MAX_PHOTOS + ' photos only — the first ' + FEEDBACK_MAX_PHOTOS + ' were kept.', 'error');
            valid.length = FEEDBACK_MAX_PHOTOS;
        }

        const dt = new DataTransfer();
        valid.forEach(f => dt.items.add(f));
        input.files = dt.files;

        renderFeedbackPhotoPreviews();
    });
}

function renderFeedbackPhotoPreviews() {
    const input = document.getElementById('feedbackPhotos');
    const wrap = document.getElementById('feedbackPhotoPreviews');
    if (!input || !wrap) return;

    wrap.innerHTML = '';
    Array.from(input.files).forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'feedback-photo-thumb';

        const img = document.createElement('img');
        const reader = new FileReader();
        reader.onload = () => { img.src = reader.result; };
        reader.readAsDataURL(file);
        img.alt = file.name;

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'feedback-photo-remove';
        removeBtn.title = 'Remove photo';
        removeBtn.textContent = '×';
        removeBtn.addEventListener('click', () => removeFeedbackPhoto(index));

        item.appendChild(img);
        item.appendChild(removeBtn);
        wrap.appendChild(item);
    });
}

function removeFeedbackPhoto(index) {
    const input = document.getElementById('feedbackPhotos');
    const dt = new DataTransfer();
    Array.from(input.files).forEach((file, i) => {
        if (i !== index) dt.items.add(file);
    });
    input.files = dt.files;
    renderFeedbackPhotoPreviews();
}

function showFeedbackPhotoStatus(message, type) {
    const status = document.getElementById('feedbackPhotoStatus');
    if (!status) return;
    status.textContent = message;
    status.className = 'id-upload-status ' + type;
    status.style.display = 'block';
}

// ===== QC location picker (district -> barangay) + interactive map =====
const QC_DISTRICT_COLORS = {
    'District 1': '#2563eb',
    'District 2': '#16a34a',
    'District 3': '#9333ea',
    'District 4': '#ea580c',
    'District 5': '#0d9488',
    'District 6': '#db2777'
};

let qcMap = null;
let qcGeoLayer = null;
let qcMapLoading = false;
const qcLayersByGeo = {};   // geojson name -> leaflet layer
let qcBarangayByGeo = null; // geojson name -> {district, name, alt}
let qcSelectedGeo = null;   // geojson name of the selected barangay
let qcPinMarker = null;     // draggable marker for the exact spot

// The geojson uses official PSA spellings; entry.geo carries that spelling
// when it differs from the display name (see citizen/includes/qc-locations.php).
function buildBarangayIndex() {
    if (qcBarangayByGeo) return qcBarangayByGeo;
    qcBarangayByGeo = {};
    Object.keys(window.QC_DISTRICTS || {}).forEach(district => {
        window.QC_DISTRICTS[district].forEach(entry => {
            qcBarangayByGeo[entry.geo || entry.name] = {
                district: district,
                name: entry.name,
                alt: entry.alt || ''
            };
        });
    });
    return qcBarangayByGeo;
}

function findBarangayEntry(district, name) {
    const list = (window.QC_DISTRICTS || {})[district] || [];
    return list.find(e => e.name === name) || null;
}

function setupLocationPicker() {
    const districtSel = document.getElementById('feedbackDistrict');
    const barangaySel = document.getElementById('feedbackBarangay');
    if (!districtSel || !barangaySel) return;

    districtSel.addEventListener('change', () => {
        populateBarangayOptions(districtSel.value);
        clearExactPin(); // the old pin belonged to a different selection
        updateAltHint();
        updateLocationPill();
        if (qcMap) focusDistrictOnMap(districtSel.value);
    });

    barangaySel.addEventListener('change', () => {
        clearExactPin();
        updateAltHint();
        updateLocationPill();
        if (qcMap) focusBarangayOnMap(districtSel.value, barangaySel.value);
    });
}

// --- Exact-spot pin ---
function placeExactPin(latlng) {
    if (!qcMap) return;

    if (!qcPinMarker) {
        qcPinMarker = L.marker(latlng, { draggable: true, title: 'Exact spot (drag to adjust)' }).addTo(qcMap);
        qcPinMarker.bindTooltip('Exact spot — drag to adjust', { direction: 'top', offset: [-14, -10] });
        qcPinMarker.on('dragend', () => {
            setPinInputs(qcPinMarker.getLatLng());
            updateLocationPill();
        });
    } else {
        qcPinMarker.setLatLng(latlng);
    }
    setPinInputs(latlng);
}

function setPinInputs(latlng) {
    const latInput = document.getElementById('feedbackLat');
    const lngInput = document.getElementById('feedbackLng');
    if (latInput) latInput.value = latlng ? latlng.lat.toFixed(7) : '';
    if (lngInput) lngInput.value = latlng ? latlng.lng.toFixed(7) : '';
}

function clearExactPin() {
    if (qcPinMarker && qcMap) {
        qcMap.removeLayer(qcPinMarker);
        qcPinMarker = null;
    }
    setPinInputs(null);
}

function populateBarangayOptions(district) {
    const barangaySel = document.getElementById('feedbackBarangay');
    if (!barangaySel) return;

    if (!district || !(window.QC_DISTRICTS || {})[district]) {
        barangaySel.innerHTML = '<option value="">Select a district first</option>';
        barangaySel.disabled = true;
        return;
    }

    barangaySel.innerHTML = '<option value="">Select your barangay</option>';
    window.QC_DISTRICTS[district].forEach(entry => {
        const option = document.createElement('option');
        option.value = entry.name;
        option.textContent = entry.name;
        barangaySel.appendChild(option);
    });
    barangaySel.disabled = false;
}

function updateAltHint() {
    const hint = document.getElementById('barangayAltHint');
    const districtSel = document.getElementById('feedbackDistrict');
    const barangaySel = document.getElementById('feedbackBarangay');
    if (!hint || !districtSel || !barangaySel) return;

    const entry = findBarangayEntry(districtSel.value, barangaySel.value);
    if (entry && entry.alt) {
        hint.textContent = 'Also known as: ' + entry.alt;
        hint.style.display = 'block';
    } else {
        hint.textContent = '';
        hint.style.display = 'none';
    }
}

function updateLocationPill() {
    const pill = document.getElementById('locationPill');
    const pillText = document.getElementById('locationPillText');
    const districtSel = document.getElementById('feedbackDistrict');
    const barangaySel = document.getElementById('feedbackBarangay');
    if (!pill || !pillText || !districtSel || !barangaySel) return;

    if (districtSel.value && barangaySel.value) {
        const latInput = document.getElementById('feedbackLat');
        const pinned = latInput && latInput.value !== '';
        pillText.textContent = 'Brgy. ' + barangaySel.value + ' — ' + districtSel.value + ', Quezon City'
            + (pinned ? ' (exact spot pinned)' : '');
        pill.style.display = 'inline-flex';
    } else {
        pill.style.display = 'none';
    }
}

function resetLocationPicker() {
    const districtSel = document.getElementById('feedbackDistrict');
    if (districtSel) districtSel.value = '';
    populateBarangayOptions('');
    clearExactPin();
    updateAltHint();
    updateLocationPill();
    if (qcMap) focusDistrictOnMap('');
}

// --- Map ---
function loadLeafletOnce() {
    if (window.L) return Promise.resolve();
    return new Promise((resolve, reject) => {
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
        document.head.appendChild(css);

        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

function initQcMap() {
    const container = document.getElementById('qcMap');
    if (!container || !window.QC_GEOJSON_URL) return;

    if (qcMap) {
        // Leaflet mis-sizes maps created while hidden; re-measure now that the page is visible.
        setTimeout(() => qcMap.invalidateSize(), 120);
        return;
    }
    if (qcMapLoading) return;
    qcMapLoading = true;

    Promise.all([
        loadLeafletOnce(),
        fetch(window.QC_GEOJSON_URL).then(res => res.json())
    ])
    .then(([, geojson]) => {
        buildBarangayIndex();

        qcMap = L.map('qcMap', { minZoom: 11, maxZoom: 17, zoomSnap: 0.25 });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            className: 'qc-basemap-tiles' // grayscaled via CSS so QC's colors stand out
        }).addTo(qcMap);

        qcGeoLayer = L.geoJSON(geojson, {
            style: qcFeatureStyle,
            onEachFeature: (feature, layer) => {
                const geoName = feature.properties.adm4_en;
                const info = qcBarangayByGeo[geoName];
                qcLayersByGeo[geoName] = layer;

                if (info) {
                    layer.bindTooltip(
                        '<strong>' + info.name + '</strong>' +
                        (info.alt ? '<br><em>' + info.alt + '</em>' : '') +
                        '<br><span class="qc-tip-district">' + info.district + '</span>',
                        { sticky: true, className: 'qc-tooltip' }
                    );

                    layer.on('mouseover', () => {
                        if (qcSelectedGeo !== geoName) {
                            layer.setStyle({ weight: 2.5, fillOpacity: 0.65 });
                        }
                    });
                    layer.on('mouseout', () => {
                        if (qcSelectedGeo !== geoName) {
                            qcGeoLayer.resetStyle(layer);
                            reapplyDistrictDim();
                        }
                    });
                    layer.on('click', (e) => {
                        const districtSel = document.getElementById('feedbackDistrict');
                        const barangaySel = document.getElementById('feedbackBarangay');
                        // Unverified accounts see the map but not the form.
                        if (!districtSel || !barangaySel) return;
                        districtSel.value = info.district;
                        populateBarangayOptions(info.district);
                        barangaySel.value = info.name;
                        updateAltHint();
                        // zoom=false: the user clicked exactly where they're looking — don't jump the view.
                        focusBarangayOnMap(info.district, info.name, false);
                        // One click does it all: barangay selected + exact spot pinned where they tapped.
                        placeExactPin(e.latlng);
                        updateLocationPill();
                    });
                }
            }
        }).addTo(qcMap);

        const bounds = qcGeoLayer.getBounds();
        qcMap.fitBounds(bounds);
        qcMap.setMaxBounds(bounds.pad(0.3));

        addQcLegend();

        const loading = container.querySelector('.qc-map-loading');
        if (loading) loading.remove();

        // If a district was already chosen before the map finished loading, reflect it.
        const districtSel = document.getElementById('feedbackDistrict');
        if (districtSel && districtSel.value) {
            focusDistrictOnMap(districtSel.value);
            const barangaySel = document.getElementById('feedbackBarangay');
            if (barangaySel && barangaySel.value) {
                focusBarangayOnMap(districtSel.value, barangaySel.value);
            }
        }
    })
    .catch(err => {
        console.error('QC map failed to load:', err);
        const loading = container.querySelector('.qc-map-loading');
        if (loading) loading.textContent = 'Map could not be loaded. You can still pick your district and barangay on the form.';
    })
    .finally(() => { qcMapLoading = false; });
}

function qcFeatureStyle(feature) {
    const info = buildBarangayIndex()[feature.properties.adm4_en];
    const color = info ? QC_DISTRICT_COLORS[info.district] : '#94a3b8';
    return { color: '#ffffff', weight: 1.2, fillColor: color, fillOpacity: 0.4 };
}

// Dim every district except the active one (called after style resets too).
function reapplyDistrictDim() {
    const districtSel = document.getElementById('feedbackDistrict');
    const active = districtSel ? districtSel.value : '';
    if (!active) return;
    Object.keys(qcLayersByGeo).forEach(geoName => {
        if (geoName === qcSelectedGeo) return;
        const info = qcBarangayByGeo[geoName];
        if (info && info.district !== active) {
            qcLayersByGeo[geoName].setStyle({ fillOpacity: 0.08, weight: 0.7 });
        }
    });
}

// zoom=false keeps the current view — used for map clicks, where re-fitting
// the bounds right under the user's cursor makes the map jump around.
function focusDistrictOnMap(district, zoom = true) {
    if (!qcGeoLayer) return;

    qcSelectedGeo = null;
    qcGeoLayer.eachLayer(layer => qcGeoLayer.resetStyle(layer));

    if (!district) {
        if (zoom) qcMap.fitBounds(qcGeoLayer.getBounds());
        return;
    }

    const districtLayers = [];
    Object.keys(qcLayersByGeo).forEach(geoName => {
        const info = qcBarangayByGeo[geoName];
        if (!info) return;
        if (info.district === district) {
            qcLayersByGeo[geoName].setStyle({ fillOpacity: 0.55, weight: 1.4 });
            districtLayers.push(qcLayersByGeo[geoName]);
        } else {
            qcLayersByGeo[geoName].setStyle({ fillOpacity: 0.08, weight: 0.7 });
        }
    });

    if (zoom && districtLayers.length) {
        qcMap.fitBounds(L.featureGroup(districtLayers).getBounds().pad(0.05));
    }
}

function focusBarangayOnMap(district, barangayName, zoom = true) {
    if (!qcGeoLayer || !district || !barangayName) return;

    focusDistrictOnMap(district, false);

    const entry = findBarangayEntry(district, barangayName);
    if (!entry) return;
    const geoName = entry.geo || entry.name;
    const layer = qcLayersByGeo[geoName];
    if (!layer) return;

    qcSelectedGeo = geoName;
    layer.setStyle({ fillOpacity: 0.8, weight: 3, color: '#1e293b' });
    if (layer.bringToFront) layer.bringToFront();
    if (zoom) qcMap.fitBounds(layer.getBounds().pad(0.4), { maxZoom: 15 });
}

function addQcLegend() {
    const legend = L.control({ position: 'bottomright' });
    legend.onAdd = () => {
        const div = L.DomUtil.create('div', 'qc-legend');
        div.innerHTML = '<strong>Districts</strong>' + Object.keys(QC_DISTRICT_COLORS).map(d =>
            '<span class="qc-legend-row"><i style="background:' + QC_DISTRICT_COLORS[d] + '"></i>' + d + '</span>'
        ).join('');
        return div;
    };
    legend.addTo(qcMap);
}

// Topbar user menu (avatar button at the top right). The shared topbar markup
// expects this toggle, but the script that provides it for staff portals
// (assets/js/script.js) isn't loaded on citizen pages, so it's wired here.
function setupUserMenu() {
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userMenu = document.getElementById('userMenu');
    if (!userMenuBtn || !userMenu) return;

    userMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        userMenu.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
        if (!userMenu.contains(e.target) && e.target !== userMenuBtn) {
            userMenu.classList.remove('open');
        }
    });
}

// Topbar user-menu entry point (shared includes/topbar.php calls this)
function showProfileSettings() {
    const userMenu = document.getElementById('userMenu');
    if (userMenu) userMenu.classList.remove('open');
    changePage('profile');
}

function showChangePassword() {
    const userMenu = document.getElementById('userMenu');
    if (userMenu) userMenu.classList.remove('open');

    const modal = document.getElementById('changePasswordModal');
    if (!modal) return;
    modal.style.display = 'flex';
    const status = document.getElementById('changePasswordStatus');
    if (status) status.style.display = 'none';
    document.getElementById('changePasswordForm').reset();
    document.getElementById('currentPassword').focus();
}

function setupChangePassword() {
    const modal = document.getElementById('changePasswordModal');
    const form = document.getElementById('changePasswordForm');
    if (!modal || !form) return;

    const closeModal = () => { modal.style.display = 'none'; };
    document.getElementById('changePasswordClose').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const btn = document.getElementById('changePasswordBtn');
        btn.disabled = true;
        btn.textContent = 'Updating...';

        const formData = new FormData(form);
        formData.append('_csrf', window.CSRF_TOKEN || '');

        fetch(citizenUrl('citizen/api/change-password.php'), {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            const status = document.getElementById('changePasswordStatus');
            status.textContent = data.message || (data.success ? 'Password updated.' : 'Something went wrong.');
            status.className = 'id-upload-status ' + (data.success ? 'success' : 'error');
            status.style.display = 'block';
            if (data.success) {
                form.reset();
                setTimeout(closeModal, 1600);
            }
        })
        .catch(() => {
            const status = document.getElementById('changePasswordStatus');
            status.textContent = 'Request failed. Please check your connection and try again.';
            status.className = 'id-upload-status error';
            status.style.display = 'block';
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Update Password';
        });
    });
}

// ===== Project detail modal (read-only view of staff-side project data) =====
function setupProjectDetailModal() {
    const modal = document.getElementById('projectDetailModal');
    if (!modal) return;

    const closeModal = () => { modal.style.display = 'none'; };
    document.getElementById('projectDetailClose').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
    });
}

function openProjectDetail(projectId) {
    const modal = document.getElementById('projectDetailModal');
    const body = document.getElementById('projectDetailBody');
    if (!modal || !body) return;

    modal.style.display = 'flex';
    body.innerHTML = '<p class="empty-state">Loading project details...</p>';

    fetch(citizenUrl('citizen/api/project-details.php') + '?id=' + encodeURIComponent(projectId))
        .then(res => res.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Not found');
            body.innerHTML = renderProjectDetail(data);
        })
        .catch(() => {
            body.innerHTML = '<p class="empty-state">Could not load this project. Please try again.</p>';
        });
}

function renderProjectDetail(data) {
    const p = data.project;
    const progress = Number(p.progress) || 0;
    const milestones = data.milestones || [];
    const updates = data.updates || [];
    const photos = data.photos || [];
    const doneMilestones = milestones.filter(m => Number(m.completed) === 1).length;

    return `
        <div class="detail-header">
            <span class="project-badge badge-${escapeHtml(p.status)}">${projectStatusLabel(p.status)}</span>
            <h4 class="detail-name">${escapeHtml(p.name)}</h4>
            <p class="detail-code">${escapeHtml(p.project_code || '')}${p.location ? ' · ' + escapeHtml(p.location) : ''}</p>
            ${p.description ? `<p class="detail-desc">${escapeHtml(p.description)}</p>` : ''}
        </div>

        <div class="detail-stats">
            <div class="detail-stat"><span class="profile-label">Budget</span><strong>₱${formatNumber(p.budget)}</strong></div>
            <div class="detail-stat"><span class="profile-label">Spent so far</span><strong>₱${formatNumber(data.total_expenses)}</strong></div>
            <div class="detail-stat"><span class="profile-label">Timeline</span><strong>${formatDate(p.start_date)} – ${formatDate(p.end_date)}</strong></div>
            <div class="detail-stat"><span class="profile-label">Contractor</span><strong>${escapeHtml(p.contractor_name || 'Not yet awarded')}</strong></div>
        </div>

        <div class="detail-progress">
            <div class="detail-progress-head"><span>Overall progress</span><strong>${progress}%</strong></div>
            <div class="progress-bar"><div class="progress-fill" style="width: ${progress}%"></div></div>
        </div>

        ${data.bid_notice ? `
        <div class="detail-section">
            <h5>Procurement Notice</h5>
            <p class="detail-bid">Reference <strong>${escapeHtml(data.bid_notice.reference_no)}</strong> · published ${formatDate(data.bid_notice.published_at)}${data.bid_notice.deadline ? ' · bid deadline ' + formatDate(data.bid_notice.deadline) : ''} · ${capitalizeFirst(data.bid_notice.status)}</p>
        </div>` : ''}

        <div class="detail-section">
            <h5>Milestones ${milestones.length ? `<span class="detail-count">${doneMilestones}/${milestones.length} done</span>` : ''}</h5>
            ${milestones.length ? `
            <ul class="milestone-list">
                ${milestones.map(m => `
                    <li class="${Number(m.completed) === 1 ? 'done' : ''}">
                        <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor">${Number(m.completed) === 1
                            ? '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>'
                            : '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>'}</svg>
                        <span>${escapeHtml(m.title)}</span>
                        ${m.due_date ? `<span class="milestone-due">due ${formatDate(m.due_date)}</span>` : ''}
                    </li>
                `).join('')}
            </ul>` : '<p class="empty-state empty-state-compact">No milestones published yet.</p>'}
        </div>

        <div class="detail-section">
            <h5>Field Updates</h5>
            ${updates.length ? `
            <div class="updates-feed">
                ${updates.map(u => `
                    <div class="update-item update-item-static">
                        <div class="update-dot update-dot-${escapeHtml(u.status)}"></div>
                        <div class="update-body">
                            <div class="update-head">
                                <span class="update-progress">${Number(u.progress_percent)}% · ${projectStatusLabel(u.status)}</span>
                                <span class="update-date">${formatDate(u.created_at)}</span>
                            </div>
                            ${u.notes ? `<p class="update-notes">${escapeHtml(u.notes)}</p>` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>` : '<p class="empty-state empty-state-compact">No field updates posted yet.</p>'}
        </div>

        ${photos.length ? `
        <div class="detail-section">
            <h5>Progress Photos</h5>
            <div class="detail-photos">
                ${photos.map(ph => `
                    <a href="${citizenUrl(escapeHtml(ph.file_path))}" target="_blank" rel="noopener" title="${escapeHtml(ph.title || 'Progress photo')}">
                        <img src="${citizenUrl(escapeHtml(ph.file_path))}" alt="${escapeHtml(ph.title || 'Progress photo')}" loading="lazy">
                    </a>
                `).join('')}
            </div>
        </div>` : ''}
    `;
}

// ===== Profile: ID verification upload =====
function setupIdUpload() {
    const form = document.getElementById('idUploadForm');
    const input = document.getElementById('profile_id_photo');
    if (!form || !input) return;

    const previewWrap = document.getElementById('idUploadPreview');
    const previewImg = document.getElementById('idUploadPreviewImg');
    const submitBtn = document.getElementById('idUploadBtn');
    const status = document.getElementById('idUploadStatus');

    input.addEventListener('change', () => {
        const file = input.files[0];
        status.style.display = 'none';
        if (!file) {
            previewWrap.style.display = 'none';
            submitBtn.style.display = 'none';
            return;
        }

        if (!file.type.startsWith('image/')) {
            showIdUploadStatus('Please select an image file (JPG, PNG, GIF, or WEBP).', 'error');
            input.value = '';
            previewWrap.style.display = 'none';
            submitBtn.style.display = 'none';
            return;
        }

        if (file.size > 3 * 1024 * 1024) {
            showIdUploadStatus('This photo is over 3MB. Please choose a smaller image.', 'error');
            input.value = '';
            previewWrap.style.display = 'none';
            submitBtn.style.display = 'none';
            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            previewImg.src = reader.result;
            previewWrap.style.display = 'block';
            submitBtn.style.display = 'inline-block';
        };
        reader.readAsDataURL(file);
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!input.files[0]) return;

        submitBtn.disabled = true;
        submitBtn.textContent = 'Uploading...';

        const formData = new FormData();
        formData.append('id_photo', input.files[0]);
        formData.append('_csrf', window.CSRF_TOKEN || '');

        fetch(citizenUrl('citizen/api/upload-id.php'), {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showIdUploadStatus(data.message, 'success');
                // Reload so the server-rendered verification card reflects the new state.
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showIdUploadStatus(data.message || 'Upload failed. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit for Verification';
            }
        })
        .catch(() => {
            showIdUploadStatus('Upload failed. Please check your connection and try again.', 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit for Verification';
        });
    });
}

function showIdUploadStatus(message, type) {
    const status = document.getElementById('idUploadStatus');
    if (!status) return;
    status.textContent = message;
    status.className = 'id-upload-status ' + type;
    status.style.display = 'block';
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
    } else if (pageName === 'submit-feedback') {
        initQcMap();
        if (document.getElementById('fbStepper')) fbGoToStep(1);
    }

    // Keep the URL hash in sync so pages are deep-linkable and the browser
    // back button works. currentPageName stops the resulting hashchange
    // event from re-running changePage.
    currentPageName = pageName;
    const targetHash = '#' + pageName;
    if (location.hash !== targetHash) {
        location.hash = targetHash;
    }

    // Scroll to top
    document.querySelector('.content').scrollTop = 0;
    window.scrollTo(0, 0);
}

window.GLOBAL_SEARCH_NAVIGATE = changePage;
window.GLOBAL_SEARCH_SOURCES = [
    {
        label: 'Projects',
        url: citizenUrl('citizen/api/projects.php'),
        dataKey: 'projects',
        mapItem: (row) => ({
            title: row.name,
            meta: `${row.project_code || ''} · ${row.location || ''}`.replace(/^ · /, ''),
            page: 'projects',
        }),
    },
];

// ===== Charts =====
// Every renderer below reads its colors through chartTheme() so light and
// dark mode each get their own palette (dark's greens are darker steps —
// picked with the palette validator, not eyeballed). The raw API payloads
// are cached in chartDataCache so a theme toggle can re-render in place.
const chartInstances = {};
const chartDataCache = {};

function chartTheme() {
    const dark = document.documentElement.getAttribute('data-theme') === 'dark';
    return dark ? {
        dark: true,
        surface: '#1e293b',
        text: '#e2e8f0',
        muted: '#94a3b8',
        grid: 'rgba(148, 163, 184, .18)',
        status: { active: '#059669', completed: '#3b82f6', delayed: '#ef4444' },
        money: '#3b82f6',          // spent / actual
        moneyFill: 'rgba(59, 130, 246, .18)',
        envelope: '#64748b',       // budget / planned reference (neutral by design)
        remaining: '#475569',
        tooltipBg: '#0f172a',
    } : {
        dark: false,
        surface: '#ffffff',
        text: '#1e293b',
        muted: '#64748b',
        grid: 'rgba(100, 116, 139, .15)',
        status: { active: '#22c55e', completed: '#3b82f6', delayed: '#ef4444' },
        money: '#2563eb',
        moneyFill: 'rgba(37, 99, 235, .12)',
        envelope: '#94a3b8',
        remaining: '#cbd5e1',
        tooltipBg: '#1e2a3b',
    };
}

function mountChart(key, canvasId, config) {
    const ctx = document.getElementById(canvasId)?.getContext('2d');
    if (!ctx || typeof Chart === 'undefined') return null;
    if (chartInstances[key]) chartInstances[key].destroy();
    chartInstances[key] = new Chart(ctx, config);
    return chartInstances[key];
}

// Compact peso figures for axis ticks: ₱1.2M, ₱350K
function pesoShort(value) {
    const n = Number(value) || 0;
    const abs = Math.abs(n);
    if (abs >= 1e9) return '₱' + (n / 1e9).toFixed(1).replace(/\.0$/, '') + 'B';
    if (abs >= 1e6) return '₱' + (n / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
    if (abs >= 1e3) return '₱' + (n / 1e3).toFixed(0) + 'K';
    return '₱' + n;
}

// Re-render every mounted chart when the topbar theme toggle flips
// data-theme, so chart colors follow the page instead of going stale.
new MutationObserver(muts => {
    if (!muts.some(m => m.attributeName === 'data-theme')) return;
    if (chartDataCache.dashboardStats) renderCitizenStatusChart(chartDataCache.dashboardStats);
    if (chartDataCache.progressChart) renderProgressChart(chartDataCache.progressChart);
    if (chartDataCache.dashboardExtras) renderDashboardExtraCharts(chartDataCache.dashboardExtras);
    if (chartDataCache.transparency) renderTransparencyCharts(chartDataCache.transparency);
}).observe(document.documentElement, { attributes: true });

function renderCitizenStatusChart(stats) {
    chartDataCache.dashboardStats = stats;
    const t = chartTheme();

    const segments = [
        { label: 'Active', value: Number(stats.active_projects || 0), color: t.status.active },
        { label: 'Completed', value: Number(stats.completed_projects || 0), color: t.status.completed },
        { label: 'Delayed', value: Number(stats.delayed_projects || 0), color: t.status.delayed },
    ];
    const total = segments.reduce((sum, s) => sum + s.value, 0);

    mountChart('status', 'citizenStatusChart', {
        type: 'doughnut',
        data: {
            labels: segments.map(s => s.label),
            datasets: [{
                data: segments.map(s => s.value),
                backgroundColor: segments.map(s => s.color),
                borderColor: segments.map(() => t.surface), borderWidth: 3, hoverOffset: 6,
            }],
        },
        options: {
            responsive: false, cutout: '70%',
            animation: { duration: 900 },
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: t.tooltipBg, callbacks: { label: c => ` ${c.label}: ${c.raw}` } },
            },
        },
    });

    const totalEl = document.getElementById('citizenStatusChartTotal');
    if (totalEl) totalEl.textContent = total;

    const legendEl = document.getElementById('citizenStatusChartLegend');
    if (legendEl) {
        legendEl.innerHTML = segments.map(s => `
            <div class="budget-legend-item">
                <span class="legend-dot" style="background:${s.color};"></span>
                <span>${s.label} <strong>${s.value}</strong></span>
            </div>
        `).join('');
    }
}

// Planned vs actual monthly progress (same series the staff dashboard plots).
// Planned is the neutral dashed reference; actual carries the accent color.
function renderProgressChart(rows) {
    chartDataCache.progressChart = rows;
    if (!Array.isArray(rows) || !rows.length) return;
    const t = chartTheme();

    mountChart('progress', 'citizenProgressChart', {
        type: 'line',
        data: {
            labels: rows.map(r => r.month),
            datasets: [
                {
                    label: 'Planned',
                    data: rows.map(r => r.planned),
                    borderColor: t.envelope, borderDash: [6, 5], borderWidth: 2,
                    pointRadius: 0, pointHoverRadius: 5, fill: false, tension: .35,
                },
                {
                    label: 'Actual',
                    data: rows.map(r => r.actual),
                    borderColor: t.money, backgroundColor: t.moneyFill, borderWidth: 2,
                    pointRadius: 0, pointHoverRadius: 5, fill: true, tension: .35,
                },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', align: 'end', labels: { color: t.text, boxWidth: 18, boxHeight: 3, usePointStyle: false } },
                tooltip: { backgroundColor: t.tooltipBg, callbacks: { label: c => ` ${c.dataset.label}: ${c.raw}%` } },
            },
            scales: {
                x: { ticks: { color: t.muted }, grid: { display: false } },
                y: { min: 0, max: 100, ticks: { color: t.muted, callback: v => v + '%' }, grid: { color: t.grid } },
            },
        },
    });
}

// Second dashboard charts row: budget by stage, project starts, feedback mix.
function renderDashboardExtraCharts(data) {
    chartDataCache.dashboardExtras = data;
    const t = chartTheme();

    // Budget by workflow stage — one peso measure across stages, single hue.
    const stages = data.budget_by_stage || [];
    mountChart('budgetByStage', 'budgetByStageChart', {
        type: 'bar',
        data: {
            labels: stages.map(s => s.stage),
            datasets: [{
                data: stages.map(s => Number(s.total) || 0),
                backgroundColor: t.money, borderRadius: 4, maxBarThickness: 22,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: t.tooltipBg, callbacks: { label: c => ' ' + formatCurrency(c.raw) } },
            },
            scales: {
                x: { ticks: { color: t.muted, callback: v => pesoShort(v), maxTicksLimit: 5 }, grid: { color: t.grid } },
                y: { ticks: { color: t.text }, grid: { display: false } },
            },
        },
    });

    // New projects per month — counts, so whole-number ticks.
    const started = data.projects_started || [];
    mountChart('projectsStarted', 'projectsStartedChart', {
        type: 'bar',
        data: {
            labels: started.map(m => m.month),
            datasets: [{
                data: started.map(m => Number(m.count) || 0),
                backgroundColor: t.money, borderRadius: 4, maxBarThickness: 18,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: t.tooltipBg, callbacks: { label: c => ` ${c.raw} project${c.raw === 1 ? '' : 's'} started` } },
            },
            scales: {
                x: { ticks: { color: t.muted, maxRotation: 0, autoSkipPadding: 8 }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: t.muted, precision: 0 }, grid: { color: t.grid } },
            },
        },
    });

    // Community feedback by category — aggregate counts, readable labels.
    const cats = (data.feedback_by_category || []).slice(0, 6);
    mountChart('feedbackCategory', 'feedbackCategoryChart', {
        type: 'bar',
        data: {
            labels: cats.map(c => feedbackCategoryLabel(c.category)),
            datasets: [{
                data: cats.map(c => Number(c.total) || 0),
                backgroundColor: t.money, borderRadius: 4, maxBarThickness: 22,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: t.tooltipBg, callbacks: { label: c => ` ${c.raw} report${c.raw === 1 ? '' : 's'}` } },
            },
            scales: {
                x: { beginAtZero: true, ticks: { color: t.muted, precision: 0 }, grid: { color: t.grid } },
                y: { ticks: { color: t.text }, grid: { display: false } },
            },
        },
    });
}

// ===== Transparency charts (budget donut, category bars, monthly line, budget-vs-spent bars) =====
function renderTransparencyCharts(data) {
    chartDataCache.transparency = data;
    const t = chartTheme();

    // Budget utilization donut: spent carries the accent, remaining is a
    // deliberate neutral — identity lives in the HTML legend beside it.
    const donut = data.budget_donut || { spent: 0, remaining: 0 };
    const spent = Number(donut.spent) || 0;
    const remaining = Number(donut.remaining) || 0;
    const totalBudget = spent + remaining;
    const pct = totalBudget > 0 ? Math.round((spent / totalBudget) * 100) : 0;

    mountChart('budgetDonut', 'budgetDonutChart', {
        type: 'doughnut',
        data: {
            labels: ['Spent', 'Remaining'],
            datasets: [{
                data: [spent, remaining],
                backgroundColor: [t.money, t.remaining],
                borderColor: [t.surface, t.surface], borderWidth: 3, hoverOffset: 6,
            }],
        },
        options: {
            responsive: false, cutout: '70%',
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: t.tooltipBg, callbacks: { label: c => ` ${c.label}: ${formatCurrency(c.raw)}` } },
            },
        },
    });

    const pctEl = document.getElementById('budgetDonutPct');
    if (pctEl) pctEl.textContent = pct + '%';
    const legendEl = document.getElementById('budgetDonutLegend');
    if (legendEl) {
        legendEl.innerHTML = [
            { label: 'Spent', value: spent, color: t.money },
            { label: 'Remaining', value: remaining, color: t.remaining },
        ].map(s => `
            <div class="budget-legend-item">
                <span class="legend-dot" style="background:${s.color};"></span>
                <span>${s.label} <strong>${pesoShort(s.value)}</strong></span>
            </div>
        `).join('');
    }

    // Spending by category: one measure across categories → single hue.
    const cats = (data.by_category || []).slice(0, 8);
    mountChart('categorySpend', 'categorySpendChart', {
        type: 'bar',
        data: {
            labels: cats.map(c => c.category),
            datasets: [{
                data: cats.map(c => Number(c.total) || 0),
                backgroundColor: t.money, borderRadius: 4, maxBarThickness: 26,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: t.tooltipBg, callbacks: { label: c => ' ' + formatCurrency(c.raw) } },
            },
            scales: {
                x: { ticks: { color: t.muted, callback: v => pesoShort(v) }, grid: { color: t.grid } },
                y: { ticks: { color: t.text }, grid: { display: false } },
            },
        },
    });

    // Monthly spending over the last 12 months.
    const months = data.monthly_spending || [];
    mountChart('monthlySpend', 'monthlySpendChart', {
        type: 'line',
        data: {
            labels: months.map(m => m.month),
            datasets: [{
                label: 'Spending',
                data: months.map(m => Number(m.total) || 0),
                borderColor: t.money, backgroundColor: t.moneyFill, borderWidth: 2,
                pointRadius: 0, pointHoverRadius: 5, fill: true, tension: .35,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: t.tooltipBg, callbacks: { label: c => ' ' + formatCurrency(c.raw) } },
            },
            scales: {
                x: { ticks: { color: t.muted, maxRotation: 0, autoSkipPadding: 12 }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: t.muted, callback: v => pesoShort(v) }, grid: { color: t.grid } },
            },
        },
    });

    // Budget vs spent for the biggest projects: the allocation is the neutral
    // reference bar, actual spending carries the accent.
    const projects = data.project_budgets || [];
    mountChart('projectBudget', 'projectBudgetChart', {
        type: 'bar',
        data: {
            labels: projects.map(p => p.name.length > 38 ? p.name.slice(0, 36) + '…' : p.name),
            datasets: [
                { label: 'Budget', data: projects.map(p => Number(p.budget) || 0), backgroundColor: t.envelope, borderRadius: 4, maxBarThickness: 18 },
                { label: 'Spent', data: projects.map(p => Number(p.spent) || 0), backgroundColor: t.money, borderRadius: 4, maxBarThickness: 18 },
            ],
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', align: 'end', labels: { color: t.text, boxWidth: 14, boxHeight: 14 } },
                tooltip: { backgroundColor: t.tooltipBg, callbacks: { label: c => ` ${c.dataset.label}: ${formatCurrency(c.raw)}` } },
            },
            scales: {
                x: { ticks: { color: t.muted, callback: v => pesoShort(v) }, grid: { color: t.grid } },
                y: { ticks: { color: t.text }, grid: { display: false } },
            },
        },
    });
}

function loadDashboardData() {
    fetch(citizenUrl('citizen/api/dashboard.php'))
        .then(res => res.json())
        .then(data => {
            if (!data || !data.stats) throw new Error(data && data.error ? data.error : 'Malformed response');

            // Update KPI cards
            document.getElementById('activeProjectsCount').textContent = data.stats.active_projects;
            document.getElementById('completedProjectsCount').textContent = data.stats.completed_projects;
            document.getElementById('delayedProjectsCount').textContent = data.stats.delayed_projects;
            document.getElementById('mySubmissionsCount').textContent = data.stats.my_submissions;

            try {
                renderCitizenStatusChart(data.stats);
                renderProgressChart(data.progress_chart || []);
                renderDashboardExtraCharts(data);
            } catch (error) {
                console.error('Failed to render dashboard charts:', error);
            }

            displayRecentProjects(data.recent_projects || []);
            displayRecentFeedback(data.recent_feedback || []);
            displayLatestUpdates(data.recent_updates || []);
        })
        .catch(err => {
            console.error('Error loading dashboard:', err);
            // Swap the skeletons for a readable message instead of leaving
            // them shimmering forever.
            const failed = '<p class="empty-state">Could not load this section. Please refresh the page to try again.</p>';
            ['recentProjectsContainer', 'recentFeedbackContainer', 'latestUpdatesContainer'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.innerHTML = failed;
            });
        });
}

// ===== Latest field updates (read-only feed of the engineers' status updates) =====
function displayLatestUpdates(updates) {
    const container = document.getElementById('latestUpdatesContainer');
    if (!container) return;

    if (!updates.length) {
        container.innerHTML = '<p class="empty-state">No field updates yet. When project engineers post progress, it shows up here.</p>';
        return;
    }

    container.innerHTML = updates.map(u => `
        <div class="update-item" onclick="openProjectDetail(${Number(u.project_id)})" title="View project details">
            <div class="update-dot ${'update-dot-' + escapeHtml(u.status)}"></div>
            <div class="update-body">
                <div class="update-head">
                    <span class="update-project">${escapeHtml(u.project_name)}</span>
                    <span class="update-date">${formatDate(u.created_at)}</span>
                </div>
                <div class="update-meta">
                    <span class="project-badge badge-${escapeHtml(u.status)}">${projectStatusLabel(u.status)}</span>
                    <span class="update-progress">${Number(u.progress_percent)}% complete</span>
                </div>
                ${u.notes ? `<p class="update-notes">${escapeHtml(u.notes)}</p>` : ''}
            </div>
        </div>
    `).join('');
}

// ===== Paginated module lists (Gmail-style: search + top pager, 10 rows/page) =====
// Each module table shares one controller: data is fetched once, then
// searched/filtered/paged client-side so flipping pages is instant.
const LIST_PAGE_SIZE = 10;
const listStates = {};

function initListControl(key, cfg) {
    listStates[key] = { cfg, data: [], page: 1 };

    const search = document.getElementById(cfg.searchId);
    if (search) search.addEventListener('input', debounce(() => {
        listStates[key].page = 1;
        renderListControl(key);
    }, 200));

    if (cfg.filterId) {
        const filter = document.getElementById(cfg.filterId);
        if (filter) filter.addEventListener('change', () => {
            listStates[key].page = 1;
            renderListControl(key);
        });
    }

    document.getElementById(cfg.prevId)?.addEventListener('click', () => {
        listStates[key].page--;
        renderListControl(key);
    });
    document.getElementById(cfg.nextId)?.addEventListener('click', () => {
        listStates[key].page++;
        renderListControl(key);
    });
}

function setListData(key, data) {
    const state = listStates[key];
    if (!state) return;
    state.data = Array.isArray(data) ? data : [];
    state.page = 1;
    renderListControl(key);
}

function renderListControl(key) {
    const state = listStates[key];
    if (!state) return;
    const { cfg } = state;
    const body = document.getElementById(cfg.bodyId);
    if (!body) return;

    const query = (document.getElementById(cfg.searchId)?.value || '').trim().toLowerCase();
    const filterVal = cfg.filterId ? (document.getElementById(cfg.filterId)?.value || '') : '';

    const filtered = state.data.filter(item => {
        if (filterVal && cfg.matchesFilter && !cfg.matchesFilter(item, filterVal)) return false;
        if (query && !cfg.searchText(item).toLowerCase().includes(query)) return false;
        return true;
    });

    const pageSize = cfg.pageSize || LIST_PAGE_SIZE;
    const total = filtered.length;
    const lastPage = Math.max(1, Math.ceil(total / pageSize));
    state.page = Math.min(Math.max(1, state.page), lastPage);
    const start = (state.page - 1) * pageSize;
    const end = Math.min(start + pageSize, total);
    const pageItems = filtered.slice(start, end);

    // cfg.columns marks a <tbody> target; without it the control renders
    // into a plain list container (e.g. the Project Status tracker cards).
    const emptyMsg = state.data.length ? 'No results match your search.' : cfg.emptyText;
    body.innerHTML = pageItems.length
        ? pageItems.map(cfg.rowHtml).join('')
        : (cfg.columns
            ? `<tr><td colspan="${cfg.columns}" class="table-empty">${emptyMsg}</td></tr>`
            : `<p class="empty-state">${emptyMsg}</p>`);

    const info = document.getElementById(cfg.infoId);
    if (info) info.textContent = total ? `${start + 1}–${end} of ${total}` : '0 of 0';
    const prev = document.getElementById(cfg.prevId);
    if (prev) prev.disabled = state.page <= 1;
    const next = document.getElementById(cfg.nextId);
    if (next) next.disabled = end >= total;
}

function setupListControls() {
    initListControl('projects', {
        bodyId: 'projectsTableBody', searchId: 'projectSearch', filterId: 'statusFilter',
        infoId: 'projectsPagerInfo', prevId: 'projectsPagerPrev', nextId: 'projectsPagerNext',
        columns: 5, emptyText: 'No projects found',
        searchText: p => `${p.name} ${p.location || ''} ${p.description || ''}`,
        matchesFilter: (p, status) => p.status === status,
        rowHtml: p => `
            <tr class="row-click" onclick="openProjectDetail(${Number(p.id)})" title="View project details">
                <td>
                    <div class="cell-title">${escapeHtml(p.name)}</div>
                    <div class="cell-sub">${escapeHtml(p.location || 'Location N/A')}</div>
                </td>
                <td class="cell-money">₱${formatNumber(p.budget)}</td>
                <td class="cell-nowrap">${formatDate(p.start_date)} – ${formatDate(p.end_date)}</td>
                <td>
                    <div class="cell-progress">
                        <div class="mini-progress"><div style="width:${Number(p.progress) || 0}%"></div></div>
                        <span>${Number(p.progress) || 0}%</span>
                    </div>
                </td>
                <td><span class="project-badge badge-${escapeHtml(p.status)}">${projectStatusLabel(p.status)}</span></td>
            </tr>`,
    });

    initListControl('projectStatus', {
        bodyId: 'projectStatusBody', searchId: 'psSearch',
        infoId: 'psPagerInfo', prevId: 'psPagerPrev', nextId: 'psPagerNext',
        pageSize: 5, emptyText: 'No projects to display',
        searchText: p => `${p.name} ${p.location || ''} ${p.description || ''}`,
        rowHtml: createTrackerCard,
    });

    initListControl('trackedFeedback', {
        bodyId: 'trackedFeedbackBody', searchId: 'tfSearch',
        infoId: 'tfPagerInfo', prevId: 'tfPagerPrev', nextId: 'tfPagerNext',
        columns: 6, emptyText: 'No feedback submissions yet',
        searchText: f => `${f.project_name || ''} ${f.message || ''} ${feedbackCategoryLabel(f.category)} ${f.barangay || ''} ${f.district || ''}`,
        rowHtml: f => `
            <tr>
                <td>
                    <div class="cell-title">${escapeHtml(f.project_name || 'Community Concern')}</div>
                    <div class="cell-sub cell-clamp" title="${escapeHtml(f.message || '')}">${escapeHtml(f.message || '')}</div>
                </td>
                <td class="cell-nowrap">${feedbackCategoryLabel(f.category)}</td>
                <td class="cell-nowrap"><span class="priority-dot priority-${escapeHtml(f.priority)}"></span>${capitalizeFirst(f.priority)}</td>
                <td class="cell-nowrap">${f.barangay ? (f.district ? 'Brgy. ' + escapeHtml(f.barangay) : escapeHtml(f.barangay)) : '—'}</td>
                <td class="cell-nowrap">${formatDate(f.created_at)}</td>
                <td><span class="feedback-status status-${escapeHtml(f.status)}">${capitalizeFirst(f.status)}</span></td>
            </tr>`,
    });

    initListControl('expenses', {
        bodyId: 'expensesBody', searchId: 'expSearch',
        infoId: 'expPagerInfo', prevId: 'expPagerPrev', nextId: 'expPagerNext',
        columns: 4, emptyText: 'No expense data available',
        searchText: e => `${e.project_name} ${e.category || ''}`,
        rowHtml: e => `
            <tr>
                <td><div class="cell-title">${escapeHtml(e.project_name)}</div></td>
                <td class="cell-nowrap">${escapeHtml(e.category || 'Uncategorized')}</td>
                <td class="cell-nowrap">${formatDate(e.expense_date)}</td>
                <td class="cell-num cell-money">₱${formatNumber(e.amount)}</td>
            </tr>`,
    });
}

// ===== Project Status tracker cards =====
// Where Public Projects is a directory table, this view answers "how far
// along is it?": a workflow stage stepper plus execution facts per project.
const TRACKER_STAGES = ['Approved', 'Bidding', 'Construction', 'Inspection', 'Completed'];
const TRACKER_STAGE_INDEX = {
    approved: 0,
    bidding: 1, awarded: 1,
    assigned: 2, active: 2, delayed: 2, on_hold: 2,
    completion_inspection: 3,
    completed: 4, turnover: 4,
};

function createTrackerCard(p) {
    const progress = Number(p.progress) || 0;
    const stageIdx = TRACKER_STAGE_INDEX[p.status] ?? 0;
    const finished = p.status === 'completed' || p.status === 'turnover';
    const troubled = p.status === 'delayed' || p.status === 'on_hold';
    const delays = Number(p.delay_reports) || 0;
    const totalMs = Number(p.total_milestones) || 0;
    const doneMs = Number(p.completed_milestones) || 0;

    const stepper = TRACKER_STAGES.map((label, i) => {
        const cls = i < stageIdx || (i === stageIdx && finished) ? 'done'
            : i === stageIdx ? (troubled ? 'current trouble' : 'current')
            : '';
        return `
            <div class="tk-stage ${cls}">
                <span class="tk-dot">${i < stageIdx || (i === stageIdx && finished)
                    ? '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>'
                    : ''}</span>
                <span class="tk-stage-label">${label}</span>
            </div>`;
    }).join('<span class="tk-stage-line"></span>');

    return `
        <div class="tracker-card row-click" onclick="openProjectDetail(${Number(p.id)})" title="View project details">
            <div class="tracker-photo">
                ${p.latest_photo_path
                    ? `<img src="${citizenUrl(escapeHtml(p.latest_photo_path))}" alt="${escapeHtml(p.latest_photo_title || p.name)}" loading="lazy">`
                    : '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>'}
            </div>
            <div class="tracker-main">
                <div class="tracker-head">
                    <div>
                        <div class="cell-title">${escapeHtml(p.name)}</div>
                        <div class="cell-sub">${escapeHtml(p.location || '')}</div>
                    </div>
                    <span class="project-badge badge-${escapeHtml(p.status)}">${projectStatusLabel(p.status)}</span>
                </div>
                <div class="tk-stepper">${stepper}</div>
                <div class="tracker-progress">
                    <div class="mini-progress"><div style="width:${progress}%"></div></div>
                    <span>${progress}% complete</span>
                </div>
                <div class="tracker-facts">
                    ${totalMs ? `<span>${doneMs} of ${totalMs} milestones done</span>` : ''}
                    <span>₱${formatNumber(p.total_expenses)} spent of ₱${formatNumber(p.budget)}</span>
                    <span>${finished ? 'Finished' : 'Target'}: ${formatDate(p.end_date)}</span>
                    ${delays ? `<span class="tk-delay">⚠ ${delays} delay report${delays === 1 ? '' : 's'}</span>` : ''}
                </div>
            </div>
        </div>`;
}

function loadProjects() {
    fetch(citizenUrl('citizen/api/projects.php'))
        .then(res => res.json())
        .then(data => {
            setListData('projects', data.projects);
        })
        .catch(err => console.error('Error loading projects:', err));
}

function loadProjectStatus() {
    fetch(citizenUrl('citizen/api/project-status.php'))
        .then(res => res.json())
        .then(data => {
            setListData('projectStatus', data.projects);
        })
        .catch(err => console.error('Error loading project status:', err));
}

function loadTrackedFeedback() {
    fetch(citizenUrl('citizen/api/my-feedback.php'))
        .then(res => res.json())
        .then(data => {
            setListData('trackedFeedback', data.feedback);
        })
        .catch(err => console.error('Error loading feedback:', err));
}

function loadTransparencyDashboard() {
    fetch(citizenUrl('citizen/api/transparency.php'))
        .then(res => res.json())
        .then(data => {
            document.getElementById('totalBudget').textContent = formatCurrency(data.stats.total_budget);
            document.getElementById('totalExpenses').textContent = formatCurrency(data.stats.total_expenses);
            document.getElementById('budgetRemaining').textContent = formatCurrency(data.stats.budget_remaining);
            document.getElementById('onTimeProjects').textContent = data.stats.on_time_projects;

            try {
                renderTransparencyCharts(data);
            } catch (error) {
                console.error('Failed to render transparency charts:', error);
            }

            setListData('expenses', data.expenses);
        })
        .catch(err => console.error('Error loading transparency data:', err));
}

function displayRecentProjects(projects) {
    const container = document.getElementById('recentProjectsContainer');
    if (!container) return;

    if (projects.length === 0) {
        container.innerHTML = '<p class="empty-state">No recent projects</p>';
        return;
    }

    container.innerHTML = projects.slice(0, 3).map(project => createProjectCard(project)).join('');
}

function createProjectCard(project) {
    const progress = project.progress || 0;
    const statusClass = 'badge-' + project.status;

    return `
        <div class="project-card" onclick="openProjectDetail(${Number(project.id)})" title="View project details">
            <span class="project-badge ${statusClass}">${projectStatusLabel(project.status)}</span>
            <div class="project-name">${escapeHtml(project.name)}</div>
            <div class="project-location">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" class="meta-icon"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                ${escapeHtml(project.location || 'N/A')}
            </div>
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

function displayRecentFeedback(feedback) {
    const container = document.getElementById('recentFeedbackContainer');
    if (!container) return;

    if (feedback.length === 0) {
        container.innerHTML = '<p class="empty-state">No submissions yet. Submit your first feedback!</p>';
        return;
    }

    container.innerHTML = feedback.slice(0, 3).map(item => createFeedbackItem(item)).join('');
}

function createFeedbackItem(item) {
    const statusClass = 'status-' + item.status;
    const priorityColor = {
        'low': '#3498db',
        'medium': '#f39c12',
        'high': '#e74c3c',
        'urgent': '#c0392b'
    }[item.priority] || '#666';

    const photos = Array.isArray(item.photos) ? item.photos : [];
    const isMaintenance = item.concern_type === 'maintenance';
    const title = item.project_name
        || (isMaintenance ? 'Maintenance Concern' : 'Community Concern');
    const cimmBadge = isMaintenance
        ? (() => {
            const sync = item.cimm_sync_status || 'none';
            const ref = item.cimm_reference ? ` · ${escapeHtml(item.cimm_reference)}` : '';
            if (sync === 'synced') {
                return `<span class="fb-cimm-chip fb-cimm-synced">Sent to CIMMS${ref}</span>`;
            }
            if (sync === 'failed' || sync === 'pending') {
                return `<span class="fb-cimm-chip fb-cimm-pending">CIMMS sync: ${escapeHtml(sync)}</span>`;
            }
            return `<span class="fb-cimm-chip">CIMMS route</span>`;
        })()
        : '';

    return `
        <div class="feedback-item">
            <div>
                <div class="feedback-header">
                    <div class="feedback-title">${escapeHtml(title)}</div>
                    <span class="feedback-status ${statusClass}">${capitalizeFirst(item.status)}</span>
                </div>
                ${cimmBadge}
                <div class="feedback-message">${escapeHtml(item.message)}</div>
                ${photos.length ? `
                <div class="feedback-photos">
                    ${photos.map(p => `<a href="${citizenUrl(escapeHtml(p))}" target="_blank" rel="noopener"><img src="${citizenUrl(escapeHtml(p))}" alt="Feedback photo" loading="lazy"></a>`).join('')}
                </div>` : ''}
                <div class="feedback-meta">
                    <span style="color: ${priorityColor}; font-weight: 600;">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" class="meta-icon"><circle cx="10" cy="10" r="6"/></svg>
                        ${capitalizeFirst(item.priority)} Priority
                    </span>
                    <span>
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" class="meta-icon"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
                        ${formatDate(item.created_at)}
                    </span>
                    <span>
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" class="meta-icon"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                        ${feedbackCategoryLabel(item.category)}
                    </span>
                    ${item.barangay ? `
                    <span>
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" class="meta-icon"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                        ${item.district ? 'Brgy. ' + escapeHtml(item.barangay) + ', ' + escapeHtml(item.district) : escapeHtml(item.barangay)}
                    </span>` : ''}
                    ${item.latitude && item.longitude ? `
                    <a class="feedback-pin-link" href="https://www.openstreetmap.org/?mlat=${encodeURIComponent(item.latitude)}&mlon=${encodeURIComponent(item.longitude)}#map=18/${encodeURIComponent(item.latitude)}/${encodeURIComponent(item.longitude)}" target="_blank" rel="noopener">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" class="meta-icon"><path fill-rule="evenodd" d="M12 1.586l-4 4v12.828l4-4V1.586zM3.707 3.293A1 1 0 002 4v10a1 1 0 00.293.707L6 18.414V5.586L3.707 3.293zM17.707 5.293L14 1.586v12.828l2.293 2.293A1 1 0 0018 16V6a1 1 0 00-.293-.707z" clip-rule="evenodd"/></svg>
                        View pinned spot
                    </a>` : ''}
                </div>
            </div>
        </div>
    `;
}

function handleFeedbackSubmit(e) {
    e.preventDefault();
    submitFeedbackWizard();
}

// ===== Submit Feedback wizard (Step 1 concern type -> Step 2 form -> Step 3 review -> Step 4 success) =====
// Posts to citizen/api/submit-feedback.php. Maintenance concerns are also
// forwarded to CIMMS when CIMM_API_ENABLED is configured on the server.
let fbCurrentStep = 1;
let fbConcernType = 'project';

const FB_STEP_LABELS = {
    1: 'Choose Concern Type',
    2: 'Fill Information',
    3: 'Review',
    4: 'Submit',
};

function fbGoToStep(step) {
    fbCurrentStep = step;

    document.querySelectorAll('.fb-panel').forEach(panel => {
        panel.classList.toggle('active', Number(panel.dataset.panel) === step);
    });
    document.querySelectorAll('.fb-step').forEach(stepEl => {
        const n = Number(stepEl.dataset.step);
        stepEl.classList.toggle('active', n === step);
        stepEl.classList.toggle('done', n < step);
    });

    const label = document.getElementById('fbProgressLabel');
    const fill = document.getElementById('fbProgressFill');
    if (label) label.textContent = `Step ${step} of 4 — ${FB_STEP_LABELS[step]}`;
    if (fill) fill.style.width = (step / 4 * 100) + '%';

    if (step === 2) {
        fbApplyConcernType(fbConcernType);
        // The map only ever renders correctly once its container is visible.
        setTimeout(() => { if (typeof qcMap !== 'undefined' && qcMap) qcMap.invalidateSize(); }, 60);
    }
    if (step === 3) fbRenderReview();

    document.querySelector('.content')?.scrollTo({ top: 0, behavior: 'smooth' });
}

function fbSelectConcern(concern) {
    fbConcernType = concern;
    const hidden = document.getElementById('feedbackConcernType');
    if (hidden) hidden.value = concern;

    document.querySelectorAll('.fb-concern-card').forEach(card => {
        card.classList.toggle('selected', card.dataset.concern === concern);
    });
    fbRenderIllustration('fbIllustration1', concern);

    // Smooth transition: let the selection state render for a beat before advancing.
    setTimeout(() => fbGoToStep(2), 420);
}

function fbApplyConcernType(concern) {
    // Maintenance issues get an exact replica of the CIMMS public request
    // form (LGU citizenrepform.php) — its own centered card with only the
    // CIMMS fields — while project concerns keep the IPMS wizard form. The
    // fb-cimms-mode class hides the wizard chrome (progress bar, stepper,
    // illustration) so the card stands alone like the original.
    const maintenance = concern === 'maintenance';
    const projectWrap = document.getElementById('fbProjectWrap');
    const cimmsWrap = document.getElementById('fbCimmsWrap');

    if (projectWrap) projectWrap.style.display = maintenance ? 'none' : '';
    if (cimmsWrap) cimmsWrap.style.display = maintenance ? '' : 'none';
    if (maintenance) {
        // The card is self-contained; only the side illustration follows the concern.
        fbRenderIllustration('fbIllustration2', concern);
        return;
    }

    // Project path: only project + general-purpose categories are offered —
    // same underlying <select>, same values the backend already validates.
    const categorySelect = document.getElementById('feedbackCategory');
    if (categorySelect) {
        let currentStillValid = false;
        Array.from(categorySelect.options).forEach(opt => {
            if (!opt.value) return;
            const optConcern = opt.dataset.concern || 'both';
            const show = optConcern === 'both' || optConcern === concern;
            opt.hidden = !show;
            if (show && opt.selected) currentStillValid = true;
        });
        if (!currentStillValid) categorySelect.value = '';
    }

    fbRenderIllustration('fbIllustration2', concern);
    fbRenderIllustration('fbIllustration3', concern);
}

// The effective infrastructure value: the "specify" text wins over the
// dropdown when it's in use — same hybrid rule as the CIMMS form.
function fbCurrentInfrastructure() {
    const other = document.getElementById('cimmsInfraOther');
    if (other && other.style.display !== 'none' && other.value.trim() !== '') return other.value.trim();
    return document.getElementById('cimmsInfraSelect')?.value || '';
}

// Hybrid infrastructure dropdown/input — behavior copied verbatim from the
// CIMMS request form: picking "Other" swaps the select for a free-text
// input; leaving it empty swaps back.
function setupInfrastructureHybrid() {
    const infraSelect = document.getElementById('cimmsInfraSelect');
    const infraOther = document.getElementById('cimmsInfraOther');
    if (!infraSelect || !infraOther) return;

    const revertToDropdown = () => {
        infraOther.style.display = 'none';
        infraSelect.style.display = '';
        infraSelect.value = '';
    };

    infraSelect.addEventListener('change', () => {
        if (infraSelect.value === 'Other') {
            infraSelect.style.display = 'none';
            infraSelect.value = '';
            infraOther.style.display = '';
            infraOther.focus();
        }
    });

    infraOther.addEventListener('input', () => {
        if (infraOther.value.trim() === '') revertToDropdown();
    });

    document.addEventListener('focusin', (e) => {
        if (infraOther.style.display !== 'none' && e.target !== infraOther && infraOther.value.trim() === '') {
            revertToDropdown();
        }
    });
}

// ===== CIMMS Maintenance Request replica =====
// Everything below is ported from the CIMMS public form
// (LGU/lgu-portal/public/citizenrepform.php) so the maintenance path looks
// and behaves exactly like the original: top-center notif popups, cursor-
// preserving phone formatting, OpenStreetMap location autocomplete, merged
// evidence uploads with previews, and a confirm modal before submitting.
// The only adaptation: it posts to citizen/api/submit-feedback.php (AJAX)
// instead of CIMMS's own requests table.

// The CIMMS form has no category picker — the feedback category is derived
// from the chosen infrastructure so the report still lands in the right
// bucket on the staff side.
const CIMMS_CATEGORY_BY_INFRA = {
    'Roads': 'road_damage',
    'Street Lights': 'streetlight',
    'Electrical': 'streetlight',
    'Drainage': 'drainage_flooding',
};

let cimmsSelectedFiles = [];

function cimmsNotify(type, message) {
    const notif = document.createElement('div');
    notif.className = 'cimms-notif-popup cimms-notif-' + type;
    const icon = (type === 'success') ? '✔️' : (type === 'error' ? '❌' : 'ℹ️');
    notif.innerHTML = `<span class='cimms-notif-icon'>${icon}</span>
                       <span class='cimms-notif-message'>${escapeHtml(message)}</span>
                       <button class='cimms-notif-close'>&times;</button>`;
    document.body.appendChild(notif);

    const dismiss = () => {
        notif.style.opacity = '0';
        setTimeout(() => notif.remove(), 400);
    };
    notif.querySelector('.cimms-notif-close').addEventListener('click', dismiss);
    setTimeout(dismiss, 2200);
}

function cimmsResetForm() {
    const form = document.getElementById('cimmsForm');
    if (!form) return;
    form.reset();
    cimmsSelectedFiles.length = 0;
    const preview = document.getElementById('cimmsImagePreview');
    if (preview) preview.innerHTML = '';
    const evidence = document.getElementById('cimmsEvidence');
    if (evidence) evidence.value = '';
    const dropzone = document.getElementById('cimmsDropzone');
    if (dropzone) { dropzone.style.pointerEvents = 'auto'; dropzone.style.opacity = '1'; }
    const infraSelect = document.getElementById('cimmsInfraSelect');
    const infraOther = document.getElementById('cimmsInfraOther');
    if (infraOther) infraOther.style.display = 'none';
    if (infraSelect) { infraSelect.style.display = ''; infraSelect.value = ''; }
    const search = document.getElementById('cimmsMapSearch');
    if (search) search.value = '';
    const addr = document.getElementById('cimmsMapAddress');
    if (addr) addr.textContent = '';
    const suggestions = document.getElementById('cimmsLocationSuggestions');
    if (suggestions) suggestions.style.display = 'none';
}

function setupCimmsMaintenanceForm() {
    const form = document.getElementById('cimmsForm');
    if (!form) return;

    document.getElementById('fbCimmsBack')?.addEventListener('click', () => fbGoToStep(1));

    // --- Contact number: auto-format 09XX-XXX-XXXX, cursor preserved ---
    const phoneInput = document.getElementById('cimmsContactNumber');
    if (phoneInput) {
        phoneInput.addEventListener('input', (e) => {
            const input = e.target;
            const cursorPos = input.selectionStart;
            let digits = input.value.replace(/\D/g, '').slice(0, 11);

            let formatted = '';
            if (digits.length <= 4) {
                formatted = digits;
            } else if (digits.length <= 7) {
                formatted = digits.slice(0, 4) + '-' + digits.slice(4);
            } else {
                formatted = digits.slice(0, 4) + '-' + digits.slice(4, 7) + '-' + digits.slice(7);
            }

            const digitsBeforeCursor = input.value.slice(0, cursorPos).replace(/\D/g, '').length;
            input.value = formatted;

            let newCursor = 0;
            let digitCount = 0;
            for (let i = 0; i < formatted.length; i++) {
                if (/\d/.test(formatted[i])) digitCount++;
                if (digitCount === digitsBeforeCursor) {
                    newCursor = i + 1;
                    break;
                }
            }
            input.setSelectionRange(newCursor, newCursor);
        });
    }

    // --- Location: the readonly field opens the map picker. Inside the
    // modal: search a place (OpenStreetMap Nominatim, Quezon City only),
    // tap the map to drop a pin, or use GPS — every path reverse-geocodes
    // and fills the Location field automatically. ---
    const locationInput = document.getElementById('cimmsLocationInput');
    const mapBackdrop = document.getElementById('cimmsMapBackdrop');
    const mapAddress = document.getElementById('cimmsMapAddress');
    const mapSearch = document.getElementById('cimmsMapSearch');
    const suggestionBox = document.getElementById('cimmsLocationSuggestions');
    let cimmsMap = null;
    let cimmsMapMarker = null;
    let cimmsPickedAddress = '';
    let cimmsPickedLat = null;
    let cimmsPickedLng = null;
    let debounceTimer = null;

    function cimmsSetAddress(address) {
        cimmsPickedAddress = address;
        if (mapAddress) mapAddress.textContent = '📍 ' + address;
        if (locationInput) locationInput.value = address;
    }

    function cimmsReverseGeocode(latlng) {
        if (mapAddress) mapAddress.textContent = 'Looking up address…';
        const fallback = latlng.lat.toFixed(5) + ', ' + latlng.lng.toFixed(5);
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}&addressdetails=1`)
            .then(res => res.json())
            .then(data => cimmsSetAddress(data.display_name || fallback))
            .catch(() => cimmsSetAddress(fallback));
    }

    // When the address is already known (a picked search suggestion), the
    // reverse lookup is skipped and the suggestion text is used as-is.
    function cimmsPlacePin(latlng, knownAddress) {
        if (!cimmsMap) return;
        if (!cimmsMapMarker) {
            cimmsMapMarker = L.marker(latlng, { draggable: true }).addTo(cimmsMap);
            cimmsMapMarker.on('dragend', () => {
                const dragged = cimmsMapMarker.getLatLng();
                cimmsPickedLat = dragged.lat;
                cimmsPickedLng = dragged.lng;
                cimmsReverseGeocode(dragged);
            });
        } else {
            cimmsMapMarker.setLatLng(latlng);
        }
        cimmsPickedLat = latlng.lat;
        cimmsPickedLng = latlng.lng;
        if (knownAddress) cimmsSetAddress(knownAddress);
        else cimmsReverseGeocode(latlng);
    }

    function openCimmsMap() {
        if (!mapBackdrop) return;
        mapBackdrop.classList.add('active');
        loadLeafletOnce().then(() => {
            if (!cimmsMap) {
                cimmsMap = L.map('cimmsMapCanvas').setView([14.6760, 121.0437], 12); // Quezon City
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(cimmsMap);
                cimmsMap.on('click', (e) => cimmsPlacePin(e.latlng));
            }
            // Leaflet mis-sizes maps created while hidden; re-measure now that the modal is open.
            setTimeout(() => cimmsMap.invalidateSize(), 80);
        }).catch(() => {
            mapBackdrop.classList.remove('active');
            cimmsNotify('error', 'Could not load the map. Please try again.');
        });
    }

    locationInput?.addEventListener('click', openCimmsMap);
    document.getElementById('cimmsMapCancel')?.addEventListener('click', () => {
        mapBackdrop?.classList.remove('active');
    });
    document.getElementById('cimmsMapUse')?.addEventListener('click', () => {
        if (locationInput && cimmsPickedAddress) locationInput.value = cimmsPickedAddress;
        mapBackdrop?.classList.remove('active');
    });

    // Search box inside the modal — picking a result jumps the map there
    // and drops the pin with the suggestion's own address text.
    if (mapSearch && suggestionBox) {
        mapSearch.addEventListener('input', () => {
            const query = mapSearch.value.trim();
            clearTimeout(debounceTimer);

            if (query.length < 3) {
                suggestionBox.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&addressdetails=1&limit=10&countrycodes=PH`)
                    .then(res => res.json())
                    .then(data => {
                        suggestionBox.innerHTML = '';

                        const qcResults = data.filter(place => {
                            const addr = place.address;
                            return addr.city === 'Quezon City' || addr.county === 'Quezon City' || addr.town === 'Quezon City' || addr.village === 'Quezon City';
                        });

                        if (!qcResults.length) {
                            suggestionBox.style.display = 'none';
                            return;
                        }

                        qcResults.forEach(place => {
                            const div = document.createElement('div');
                            div.textContent = place.display_name;
                            div.onclick = () => {
                                suggestionBox.style.display = 'none';
                                mapSearch.value = place.display_name;
                                const latlng = { lat: parseFloat(place.lat), lng: parseFloat(place.lon) };
                                if (cimmsMap) cimmsMap.setView(latlng, 17);
                                cimmsPlacePin(latlng, place.display_name);
                            };
                            suggestionBox.appendChild(div);
                        });

                        suggestionBox.style.display = 'block';
                    })
                    .catch(() => {
                        suggestionBox.style.display = 'none';
                    });
            }, 350);
        });

        document.addEventListener('click', e => {
            if (!e.target.closest('.cimms-map-search')) {
                suggestionBox.style.display = 'none';
            }
        });
    }

    // "My location" — GPS pin with the same reverse-geocode fill.
    document.getElementById('cimmsMapGeo')?.addEventListener('click', () => {
        if (!navigator.geolocation) {
            cimmsNotify('error', 'Location services are not available in this browser.');
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const latlng = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                if (cimmsMap) cimmsMap.setView(latlng, 17);
                cimmsPlacePin(latlng);
            },
            () => cimmsNotify('error', 'Could not get your location. Please allow location access or pin it on the map.')
        );
    });

    // --- Evidence images: dropzone (click or drag), merged file state,
    // previews with remove buttons ---
    const evidenceInput = document.getElementById('cimmsEvidence');
    const dropzone = document.getElementById('cimmsDropzone');
    const previewDiv = document.getElementById('cimmsImagePreview');
    const MAX_FILES = 4;

    function updateUploadButton() {
        const full = cimmsSelectedFiles.length >= MAX_FILES;
        if (dropzone) {
            dropzone.style.pointerEvents = full ? 'none' : 'auto';
            dropzone.style.opacity = full ? '0.5' : '1';
        }
    }

    function syncInputWithState() {
        const dt = new DataTransfer();
        cimmsSelectedFiles.forEach(f => dt.items.add(f));
        if (evidenceInput) evidenceInput.files = dt.files;
        renderImagePreview();
    }

    function mergeFiles(incoming) {
        cimmsSelectedFiles = cimmsSelectedFiles.concat(incoming.filter(f => f.type.startsWith('image/')));

        const seen = new Set();
        cimmsSelectedFiles = cimmsSelectedFiles.filter(f => {
            const key = f.name + f.size + f.lastModified;
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });

        if (cimmsSelectedFiles.length > MAX_FILES) {
            cimmsNotify('error', `Maximum of ${MAX_FILES} images allowed.`);
            cimmsSelectedFiles.length = MAX_FILES;
        }

        syncInputWithState();
    }

    function mergeAndPreviewFiles(e) {
        mergeFiles(Array.from(e.target.files || []));
    }

    function removeImageAtIndex(index) {
        cimmsSelectedFiles.splice(index, 1);
        syncInputWithState();
    }

    function openFullImage(src) {
        const modalBackdrop = document.createElement('div');
        modalBackdrop.style.position = 'fixed';
        modalBackdrop.style.inset = '0';
        modalBackdrop.style.background = 'rgba(0,0,0,0.6)';
        modalBackdrop.style.display = 'flex';
        modalBackdrop.style.alignItems = 'center';
        modalBackdrop.style.justifyContent = 'center';
        modalBackdrop.style.zIndex = '8000';

        const fullImg = document.createElement('img');
        fullImg.src = src;
        fullImg.style.maxWidth = '90%';
        fullImg.style.maxHeight = '90%';
        fullImg.style.borderRadius = '12px';

        modalBackdrop.appendChild(fullImg);
        document.body.appendChild(modalBackdrop);
        modalBackdrop.addEventListener('click', () => modalBackdrop.remove());
    }

    function renderImagePreview() {
        if (!previewDiv) return;
        previewDiv.innerHTML = '';
        cimmsSelectedFiles.forEach((file, index) => {
            if (!file.type.startsWith('image/')) return;

            const reader = new FileReader();
            reader.onload = e => {
                const wrapper = document.createElement('div');
                wrapper.className = 'cimms-preview-item';

                const img = document.createElement('img');
                img.src = e.target.result;
                img.title = 'Click to view full image';
                img.addEventListener('click', () => openFullImage(e.target.result));

                const removeBtn = document.createElement('div');
                removeBtn.className = 'cimms-preview-remove';
                removeBtn.innerHTML = '&times;';
                removeBtn.addEventListener('click', (ev) => {
                    ev.stopPropagation();
                    removeImageAtIndex(index);
                });

                wrapper.appendChild(img);
                wrapper.appendChild(removeBtn);
                previewDiv.appendChild(wrapper);
            };
            reader.readAsDataURL(file);
        });
        updateUploadButton();
    }

    evidenceInput?.addEventListener('change', mergeAndPreviewFiles);

    if (dropzone && evidenceInput) {
        dropzone.addEventListener('click', () => evidenceInput.click());
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            mergeFiles(Array.from(e.dataTransfer?.files || []));
        });
    }

    // --- Confirm modal + submit ---
    const backdrop = document.getElementById('cimmsAlertBackdrop');
    const submitBtn = document.getElementById('cimmsSubmitBtn');

    form.addEventListener('submit', e => {
        e.preventDefault();

        // The readonly Location field is exempt from native "required"
        // validation, so it gets checked here instead.
        if (!locationInput || locationInput.value.trim() === '') {
            cimmsNotify('error', 'Please select a location.');
            openCimmsMap();
            return false;
        }
        // A typed/suggested address alone isn't enough — CIMMS needs a real
        // pinned coordinate, or it has to geocode the free-text address
        // itself on its end, which can resolve to a different spot each time
        // the request is viewed. Requiring the pin here is what actually
        // fixes that instability, not just a stricter validation message.
        if (cimmsPickedLat === null || cimmsPickedLng === null) {
            cimmsNotify('error', 'Please tap the exact spot on the map to pin your location.');
            openCimmsMap();
            return false;
        }

        const val = (phoneInput?.value || '').replace(/\D/g, '');
        if (!/^09\d{9}$/.test(val)) {
            cimmsNotify('error', 'Contact number must be 11 digits and start with 09.');
            phoneInput?.focus();
            return false;
        }

        if (backdrop) {
            backdrop.classList.add('active');
            document.getElementById('cimmsAlertConfirm')?.focus();
        }
    });

    document.getElementById('cimmsAlertCancel')?.addEventListener('click', () => {
        backdrop?.classList.remove('active');
    });

    document.getElementById('cimmsAlertConfirm')?.addEventListener('click', () => {
        backdrop?.classList.remove('active');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
        }

        const infraSelect = document.getElementById('cimmsInfraSelect');
        const infraOther = document.getElementById('cimmsInfraOther');
        const infrastructure = fbCurrentInfrastructure();

        const formData = new FormData();
        formData.append('concern_type', 'maintenance');
        formData.append('infrastructure', infraSelect?.value || '');
        formData.append('infrastructure_other', (infraOther && infraOther.style.display !== 'none') ? infraOther.value.trim() : '');
        formData.append('location', document.getElementById('cimmsLocationInput')?.value.trim() || '');
        formData.append('latitude', cimmsPickedLat !== null ? String(cimmsPickedLat) : '');
        formData.append('longitude', cimmsPickedLng !== null ? String(cimmsPickedLng) : '');
        formData.append('contact_name', document.getElementById('cimmsName')?.value.trim() || '');
        formData.append('contact_phone', phoneInput?.value.trim() || '');
        formData.append('contact_email', document.getElementById('cimmsEmail')?.value.trim() || '');
        formData.append('message', document.getElementById('cimmsIssue')?.value.trim() || '');
        formData.append('category', CIMMS_CATEGORY_BY_INFRA[infrastructure] || 'complaint');
        formData.append('priority', 'medium');
        cimmsSelectedFiles.forEach(f => formData.append('photos[]', f));

        fetch(citizenUrl('citizen/api/submit-feedback.php'), {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    cimmsNotify('success', 'Maintenance request submitted successfully! Request ID: ' + (data.id || 0));
                    const chip = document.getElementById('fbTrackingChip');
                    if (chip) {
                        const cimmRef = data.cimm && data.cimm.reference ? ` · ${data.cimm.reference}` : '';
                        chip.textContent = '#FB-' + String(data.id || 0).padStart(6, '0') + cimmRef;
                    }
                    const successNote = document.getElementById('fbSuccessCimmNote');
                    if (successNote) {
                        successNote.style.display = 'block';
                        successNote.textContent = data.cimm && data.cimm.status === 'synced'
                            ? (data.cimm.reference
                                ? `Also filed in CIMMS as ${data.cimm.reference}.`
                                : 'Also forwarded to CIMMS for maintenance handling.')
                            : 'Saved in IPMS. CIMMS forwarding is pending or needs staff follow-up.';
                    }
                    cimmsResetForm();
                    loadTrackedFeedback();
                    fbGoToStep(4);
                } else {
                    cimmsNotify('error', data.message || 'Failed to submit request. Please try again.');
                }
            })
            .catch(() => {
                cimmsNotify('error', 'Failed to submit request. Please try again.');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Request';
                }
            });
    });
}

// Contact number in the CIMMS 09XX-XXX-XXXX shape: digits only, dashes
// added while typing, must be 11 digits starting with 09 when required.
function setupContactPhoneFormat() {
    const phone = document.getElementById('feedbackContactPhone');
    if (!phone) return;

    phone.addEventListener('input', () => {
        const digits = phone.value.replace(/\D/g, '').slice(0, 11);
        let formatted = digits;
        if (digits.length > 7) formatted = digits.slice(0, 4) + '-' + digits.slice(4, 7) + '-' + digits.slice(7);
        else if (digits.length > 4) formatted = digits.slice(0, 4) + '-' + digits.slice(4);
        phone.value = formatted;

        const pure = digits;
        if (pure === '' && !phone.required) {
            phone.setCustomValidity('');
        } else if (!/^09\d{9}$/.test(pure)) {
            phone.setCustomValidity('Contact number must be 11 digits (09XX-XXX-XXXX) and start with 09.');
        } else {
            phone.setCustomValidity('');
        }
    });
}

function fbToggleAnonymous() {
    const checkbox = document.getElementById('feedbackAnonymous');
    const grid = document.getElementById('fbContactGrid');
    if (!checkbox || !grid) return;
    grid.classList.toggle('fb-contact-disabled', checkbox.checked);
    grid.querySelectorAll('input').forEach(input => {
        // CIMMS still needs a callback number for maintenance reports, even if anonymous.
        if (fbConcernType === 'maintenance' && input.id === 'feedbackContactPhone') {
            input.disabled = false;
            return;
        }
        input.disabled = checkbox.checked;
    });
}

const FB_ILLUSTRATIONS = {
    project: {
        emoji: ['🏗️', '🌉', '👷'],
        title: 'Infrastructure Project Concern',
        note: 'Reviewed by the project’s assigned Engineer and, where needed, the Bids &amp; Awards Committee.',
    },
    maintenance: {
        emoji: ['💡', '🚧', '🔧'],
        title: 'Infrastructure Maintenance Issue',
        note: 'Coordinated with the Community Infrastructure Maintenance Management System (CIMMS).',
    },
};

function fbRenderIllustration(containerId, concern) {
    const el = document.getElementById(containerId);
    if (!el) return;
    const data = FB_ILLUSTRATIONS[concern] || FB_ILLUSTRATIONS.project;
    el.dataset.state = concern;
    el.innerHTML = `
        <div class="fb-illu-card fb-illu-${escapeHtml(concern)}">
            <div class="fb-illu-icons">${data.emoji.map(e => `<span>${e}</span>`).join('')}</div>
            <p class="fb-illu-title">${escapeHtml(data.title)}</p>
            <p class="fb-illu-note">${data.note}</p>
        </div>
    `;
}

function fbRenderReview() {
    const card = document.getElementById('fbReviewCard');
    if (!card) return;

    const districtSel = document.getElementById('feedbackDistrict');
    const barangaySel = document.getElementById('feedbackBarangay');
    const category = document.getElementById('feedbackCategory');
    const priority = document.getElementById('feedbackPriority');
    const message = document.getElementById('feedbackMessage');
    const projectName = document.getElementById('feedbackProjectName');
    const isAnonymous = document.getElementById('feedbackAnonymous')?.checked;
    const contactName = document.getElementById('feedbackContactName')?.value.trim();
    const contactPhone = document.getElementById('feedbackContactPhone')?.value.trim();
    const contactEmail = document.getElementById('feedbackContactEmail')?.value.trim();
    const photoInput = document.getElementById('feedbackPhotos');
    const lat = document.getElementById('feedbackLat')?.value;

    const concernLabel = fbConcernType === 'maintenance' ? 'Infrastructure Maintenance Issue' : 'Infrastructure Project Concern';
    const locationText = (districtSel?.value && barangaySel?.value)
        ? `${barangaySel.value}, ${districtSel.value}${lat ? ' — exact spot pinned' : ''}`
        : 'Not specified';
    const photoCount = photoInput ? photoInput.files.length : 0;
    const contactText = isAnonymous
        ? 'Anonymous submission'
        : ([contactName, contactPhone, contactEmail].filter(Boolean).join(' · ') || 'Not provided');

    const infrastructure = fbConcernType === 'maintenance' ? fbCurrentInfrastructure() : '';

    card.innerHTML = `
        <div class="fb-review-row"><span>Concern Type</span><strong>${escapeHtml(concernLabel)}</strong></div>
        ${infrastructure ? `<div class="fb-review-row"><span>Infrastructure Type</span><strong>${escapeHtml(infrastructure)}</strong></div>` : ''}
        ${projectName?.value ? `<div class="fb-review-row"><span>Project Name</span><strong>${escapeHtml(projectName.value)}</strong></div>` : ''}
        <div class="fb-review-row"><span>Location</span><strong>${escapeHtml(locationText)}</strong></div>
        <div class="fb-review-row"><span>Category</span><strong>${escapeHtml(category?.selectedOptions[0]?.textContent.trim() || 'Not specified')}</strong></div>
        <div class="fb-review-row"><span>Priority</span><strong>${escapeHtml(capitalizeFirst(priority?.value || ''))}</strong></div>
        <div class="fb-review-row fb-review-row-block"><span>Description</span><p>${escapeHtml(message?.value || '')}</p></div>
        <div class="fb-review-row"><span>Attachments</span><strong>${photoCount} photo${photoCount === 1 ? '' : 's'}</strong></div>
        <div class="fb-review-row"><span>Contact Information</span><strong>${escapeHtml(contactText)}</strong></div>
    `;
}

function submitFeedbackWizard() {
    const form = document.getElementById('feedbackForm');
    if (!form) return;

    const nextBtn = document.getElementById('fbNextBtn3');
    const errorBox = document.getElementById('fbSubmitError');
    if (errorBox) errorBox.style.display = 'none';
    if (nextBtn) { nextBtn.disabled = true; nextBtn.textContent = 'Submitting…'; }

    const formData = new FormData(form);

    fetch(citizenUrl('citizen/api/submit-feedback.php'), {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const chip = document.getElementById('fbTrackingChip');
                if (chip) {
                    const fbId = '#FB-' + String(data.id || 0).padStart(6, '0');
                    const cimmRef = data.cimm && data.cimm.reference ? ` · ${data.cimm.reference}` : '';
                    chip.textContent = fbId + cimmRef;
                }
                const successNote = document.getElementById('fbSuccessCimmNote');
                if (successNote) {
                    if (data.concern_type === 'maintenance' && data.cimm) {
                        if (data.cimm.status === 'synced') {
                            successNote.style.display = 'block';
                            successNote.textContent = data.cimm.reference
                                ? `Also filed in CIMMS as ${data.cimm.reference}.`
                                : 'Also forwarded to CIMMS for maintenance handling.';
                        } else {
                            successNote.style.display = 'block';
                            successNote.textContent = 'Saved in IPMS. CIMMS forwarding is pending or needs staff follow-up.';
                        }
                    } else {
                        successNote.style.display = 'none';
                    }
                }
                fbGoToStep(4);
            } else if (errorBox) {
                errorBox.textContent = data.message || 'Failed to submit feedback. Please try again.';
                errorBox.style.display = 'block';
            }
        })
        .catch(err => {
            console.error('Error:', err);
            if (errorBox) {
                errorBox.textContent = 'Something went wrong sending your report. Please check your connection and try again.';
                errorBox.style.display = 'block';
            }
        })
        .finally(() => {
            if (nextBtn) { nextBtn.disabled = false; nextBtn.textContent = 'Confirm & Submit'; }
        });
}

function resetFeedbackWizard() {
    const form = document.getElementById('feedbackForm');
    if (form) form.reset();
    cimmsResetForm();
    document.getElementById('feedbackContactPhone')?.setCustomValidity('');
    resetLocationPicker();
    renderFeedbackPhotoPreviews();
    fbToggleAnonymous();
    document.querySelectorAll('.fb-concern-card').forEach(card => card.classList.remove('selected'));
    const illu1 = document.getElementById('fbIllustration1');
    if (illu1) illu1.innerHTML = '<div class="fb-illu-empty"><span class="fb-illu-empty-icon">👆</span><p>Pick a card to see what happens next.</p></div>';
    fbConcernType = 'project';
    fbGoToStep(1);
}

function setupFeedbackWizard() {
    if (!document.getElementById('fbStepper')) return; // unverified citizens see the verify-banner instead

    document.querySelectorAll('.fb-concern-card').forEach(card => {
        card.addEventListener('click', () => fbSelectConcern(card.dataset.concern));
    });

    document.getElementById('feedbackAnonymous')?.addEventListener('change', fbToggleAnonymous);
    setupInfrastructureHybrid();
    setupContactPhoneFormat();
    setupCimmsMaintenanceForm();

    // Step 2 (Fill Information) in-panel actions
    document.getElementById('fbBackBtn2')?.addEventListener('click', () => fbGoToStep(1));
    document.getElementById('fbNextBtn2')?.addEventListener('click', () => {
        const form = document.getElementById('feedbackForm');
        if (form && !form.reportValidity()) return;

        // CIMMS requires a PH mobile number for maintenance reports.
        if (fbConcernType === 'maintenance') {
            const phoneRaw = (document.getElementById('feedbackContactPhone')?.value || '').replace(/\D/g, '')
                || (form?.dataset.profilePhone || '');
            if (!/^09\d{9}$/.test(phoneRaw)) {
                const errorBox = document.getElementById('fbSubmitError');
                const msg = 'Maintenance reports forwarded to CIMMS need a valid mobile number (09XXXXXXXXX). Add it under Contact Information, or update your Profile phone.';
                if (errorBox) {
                    errorBox.textContent = msg;
                    errorBox.style.display = 'block';
                } else {
                    alert(msg);
                }
                document.getElementById('feedbackContactPhone')?.focus();
                return;
            }
            // Ensure the resolved number travels with the POST even if the field was cleared.
            const phoneInput = document.getElementById('feedbackContactPhone');
            if (phoneInput && !phoneInput.value.trim() && phoneRaw) {
                phoneInput.value = phoneRaw;
            }
        }

        fbGoToStep(3);
    });

    // Step 3 (Review) in-panel actions
    document.getElementById('fbBackBtn3')?.addEventListener('click', () => fbGoToStep(2));
    document.getElementById('fbNextBtn3')?.addEventListener('click', () => submitFeedbackWizard());

    document.getElementById('fbBtnDashboard')?.addEventListener('click', () => changePage('dashboard'));
    document.getElementById('fbBtnTrack')?.addEventListener('click', () => changePage('track-feedback'));
    document.getElementById('fbBtnAnother')?.addEventListener('click', () => resetFeedbackWizard());
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

// Workflow statuses whose raw enum value reads poorly to the public.
const PROJECT_STATUS_LABELS = {
    on_hold: 'On Hold',
    completion_inspection: 'Final Inspection',
    turnover: 'Turned Over',
};

function projectStatusLabel(status) {
    return PROJECT_STATUS_LABELS[status] || capitalizeFirst(status);
}

// Mirrors citizen/includes/feedback-categories.php (the server-side source of truth).
const FEEDBACK_CATEGORY_LABELS = {
    complaint: 'General Complaint',
    road_damage: 'Road Damage',
    drainage_flooding: 'Drainage & Flooding',
    streetlight: 'Streetlight / Electrical',
    sidewalk_accessibility: 'Sidewalk & Accessibility',
    safety_hazard: 'Safety Hazard',
    project_delay: 'Project Delay',
    suggestion: 'Suggestion',
    inquiry: 'Inquiry',
    commendation: 'Commendation',
};

function feedbackCategoryLabel(value) {
    return FEEDBACK_CATEGORY_LABELS[value] || capitalizeFirst(value);
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
