// Citizen Portal JavaScript
const CITIZEN_BASE_PATH = window.BASE_PATH || '/';

function citizenUrl(path) {
    return CITIZEN_BASE_PATH + path.replace(/^\/+/, '');
}

// "Project Status" used to be its own page; it's now the Cards view of the
// merged Public Projects page. Old bookmarks/links to #project-status still
// land somewhere real instead of silently doing nothing.
const PAGE_HASH_ALIASES = { 'project-status': 'projects', 'announcements': 'dashboard' };
function resolvePageHash(page) {
    return PAGE_HASH_ALIASES[page] || page;
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the page
    loadDashboardData();
    loadProjectSlideshow();
    loadAnnouncements();
    setupEventListeners();

    // Deep-linking: #projects, #profile, etc. restore the page on load…
    const initialPage = resolvePageHash((location.hash || '').replace(/^#/, ''));
    if (initialPage && document.getElementById('page-' + initialPage)) {
        changePage(initialPage);
    }
});

// …and browser back/forward moves between pages.
let currentPageName = 'dashboard';
window.addEventListener('hashchange', function() {
    const page = resolvePageHash((location.hash || '').replace(/^#/, '') || 'dashboard');
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
    setupViewToggles();

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
    setupFeedbackDetailModal();
    setupAnnouncementDetailModal();
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
let qcProjectMarkers = [];  // ongoing-project pins, separate layer from the barangay polygons

let tfMap = null;           // Track Feedback page's browse-only ongoing-projects map
let tfMapLoading = false;
let tfProjectMarkers = [];

// Pin icon/color/popup design lives in assets/js/project-map.js (window.ProjectMap)
// — shared by every project map in the app, so a status looks the same
// everywhere. See loadQcProjectPins() below for where it's used.

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
                            layer.setStyle({ weight: 2, fillOpacity: 0.35 });
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
        loadQcProjectPins();

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
    // Kept light so street/place names on the basemap underneath stay readable.
    return { color: '#ffffff', weight: 1, fillColor: color, fillOpacity: 0.18 };
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
            qcLayersByGeo[geoName].setStyle({ fillOpacity: 0.05, weight: 0.6 });
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
            qcLayersByGeo[geoName].setStyle({ fillOpacity: 0.28, weight: 1.2 });
            districtLayers.push(qcLayersByGeo[geoName]);
        } else {
            qcLayersByGeo[geoName].setStyle({ fillOpacity: 0.05, weight: 0.6 });
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
    layer.setStyle({ fillOpacity: 0.45, weight: 2.5, color: '#1e293b' });
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

// Ongoing-project pins — separate Leaflet layer from the barangay polygons,
// so a citizen can see exactly where active infrastructure work is happening
// while picking their own feedback location. Icon/popup design comes from
// the shared window.ProjectMap (assets/js/project-map.js) so it matches
// every other project map in the app; hovering previews the project
// (photo + rating when it has one), clicking opens the existing citizen
// project-detail modal (openProjectDetail) rather than duplicating it.
function loadQcProjectPins() {
    if (!qcMap || !window.ProjectMap) return;
    fetch(citizenUrl('citizen/api/projects.php'))
        .then(res => res.json())
        .then(data => {
            const projects = data.projects || [];
            qcProjectMarkers.forEach(m => qcMap.removeLayer(m));
            qcProjectMarkers = [];

            projects.forEach(p => {
                if (p.latitude === null || p.longitude === null || p.latitude === undefined || p.longitude === undefined) return;
                const lat = Number(p.latitude);
                const lng = Number(p.longitude);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
                // Same QC bounding box the map itself is clamped to — skip stray
                // out-of-city coordinates rather than plot a wrong pin.
                if (lat < 14.55 || lat > 14.82 || lng < 120.96 || lng > 121.16) return;

                const marker = L.marker([lat, lng], {
                    icon: window.ProjectMap.pinIcon(p.status),
                }).addTo(qcMap);

                marker.bindPopup(window.ProjectMap.popupHtml({
                    ...p,
                    status_label: projectStatusLabel(p.status),
                    budget_label: formatCurrency(p.budget),
                }, citizenUrl('')));
                window.ProjectMap.bindPin(marker, p, openProjectDetail);

                qcProjectMarkers.push(marker);
            });
        })
        .catch(() => { /* Pins are a bonus on top of the barangay picker — a failed fetch shouldn't block feedback submission. */ });
}

// Track Feedback page's read-only browse map — no barangay picker, no pin
// dropping, just ongoing-project pins so a citizen can see what's happening
// near them. Same caching pattern as initQcMap(): the Leaflet instance is
// created once and reused (invalidateSize on revisit), never rebuilt, since
// this page never re-renders its DOM.
const TF_ONGOING_STATUSES = ['approved', 'bidding', 'awarded', 'assigned', 'active', 'delayed', 'on_hold', 'completion_inspection'];

function initTrackFeedbackMap() {
    const container = document.getElementById('tfMap');
    if (!container) return;

    if (tfMap) {
        setTimeout(() => tfMap.invalidateSize(), 120);
        loadTfProjectPins();
        return;
    }
    if (tfMapLoading) return;
    tfMapLoading = true;

    Promise.all([
        loadLeafletOnce(),
        window.QC_GEOJSON_URL ? fetch(window.QC_GEOJSON_URL).then(res => res.json()).catch(() => null) : Promise.resolve(null),
    ])
        .then(([, geojson]) => {
            tfMap = L.map('tfMap', { minZoom: 11, maxZoom: 17, zoomSnap: 0.25 }).setView([14.676, 121.043], 12);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                className: 'qc-basemap-tiles'
            }).addTo(tfMap);

            // Same light per-district tint every other project map in the app
            // uses (window.ProjectMap, assets/js/project-map.js) — purely
            // decorative here, just for visual consistency with the rest of
            // the portal.
            if (geojson && window.ProjectMap) {
                window.ProjectMap.districtLayer(tfMap, geojson, { light: true });
            }

            const loading = container.querySelector('.qc-map-loading');
            if (loading) loading.remove();

            loadTfProjectPins();
        })
        .catch(err => {
            console.error('Track Feedback map failed to load:', err);
            const loading = container.querySelector('.qc-map-loading');
            if (loading) loading.textContent = 'Map could not be loaded right now.';
        })
        .finally(() => { tfMapLoading = false; });
}

function loadTfProjectPins() {
    if (!tfMap || !window.ProjectMap) return;
    fetch(citizenUrl('citizen/api/projects.php'))
        .then(res => res.json())
        .then(data => {
            const projects = (data.projects || []).filter(p => TF_ONGOING_STATUSES.includes(p.status));
            tfProjectMarkers.forEach(m => tfMap.removeLayer(m));
            tfProjectMarkers = [];

            projects.forEach(p => {
                if (p.latitude === null || p.longitude === null || p.latitude === undefined || p.longitude === undefined) return;
                const lat = Number(p.latitude);
                const lng = Number(p.longitude);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
                if (lat < 14.55 || lat > 14.82 || lng < 120.96 || lng > 121.16) return;

                const marker = L.marker([lat, lng], {
                    icon: window.ProjectMap.pinIcon(p.status),
                }).addTo(tfMap);

                marker.bindPopup(window.ProjectMap.popupHtml({
                    ...p,
                    status_label: projectStatusLabel(p.status),
                    budget_label: formatCurrency(p.budget),
                }, citizenUrl('')));
                window.ProjectMap.bindPin(marker, p, openProjectDetail);

                tfProjectMarkers.push(marker);
            });

            if (tfProjectMarkers.length) {
                tfMap.fitBounds(L.featureGroup(tfProjectMarkers).getBounds().pad(0.2), { maxZoom: 15 });
            }
        })
        .catch(() => { /* Pins are a bonus on the tracking page — a failed fetch shouldn't block viewing reports. */ });
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

// ===== Project highlights slideshow (dashboard hero card) =====
let slideshowState = { projects: [], index: 0, timer: null, intervalMs: 6000 };

function loadProjectSlideshow() {
    fetch(citizenUrl('citizen/api/project-gallery.php'))
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.projects || !data.projects.length) return;
            slideshowState.projects = data.projects;
            renderProjectSlideshow();
        })
        .catch(() => {});
}

function renderProjectSlideshow() {
    const card = document.getElementById('projectSlideshowCard');
    const track = document.getElementById('projectSlideshowTrack');
    const dots = document.getElementById('projectSlideshowDots');
    if (!card || !track || !dots) return;

    const projects = slideshowState.projects;
    if (!projects.length) return;

    track.innerHTML = projects.map(p => `
        <div class="slideshow-slide" onclick="openProjectDetail(${p.id})">
            <img src="${citizenUrl(escapeHtml(p.cover_photo))}" alt="${escapeHtml(p.cover_title || p.name)}" loading="lazy">
            ${p.rating_count > 0 ? `<span class="slideshow-rating-badge">★ ${p.rating_average.toFixed(1)} <span class="slideshow-rating-count">(${p.rating_count})</span></span>` : ''}
            <div class="slideshow-caption">
                <span class="project-badge badge-${escapeHtml(p.status)}">${projectStatusLabel(p.status)}</span>
                ${p.category ? `<span class="slideshow-category-chip">${escapeHtml(p.category)}</span>` : ''}
                <h3>${escapeHtml(p.name)}</h3>
                <p>${escapeHtml(p.project_code || '')}${p.location ? ' · ' + escapeHtml(p.location) : ''}</p>
                <div class="slideshow-progress">
                    <div class="progress-bar"><div class="progress-fill" style="width:${Number(p.progress || 0)}%"></div></div>
                    <span>${Number(p.progress || 0)}% complete</span>
                </div>
            </div>
        </div>
    `).join('');

    dots.innerHTML = projects.map((_, i) => `<button type="button" class="slideshow-dot" onclick="goToSlide(${i})" aria-label="Go to slide ${i + 1}"></button>`).join('');

    card.style.display = 'block';
    goToSlide(0);
    card.addEventListener('mouseenter', stopSlideshowAutoplay);
    card.addEventListener('mouseleave', startSlideshowAutoplay);
    startSlideshowAutoplay();
}

function goToSlide(index) {
    const track = document.getElementById('projectSlideshowTrack');
    const count = slideshowState.projects.length;
    if (!track || !count) return;

    slideshowState.index = ((index % count) + count) % count;
    track.style.transform = `translateX(-${slideshowState.index * 100}%)`;
    document.querySelectorAll('#projectSlideshowDots .slideshow-dot').forEach((dot, i) => {
        dot.classList.toggle('active', i === slideshowState.index);
    });
}

function nextSlide() {
    goToSlide(slideshowState.index + 1);
    startSlideshowAutoplay(); // manual click resets the idle timer instead of getting instantly overridden
}

function startSlideshowAutoplay() {
    stopSlideshowAutoplay();
    if (slideshowState.projects.length < 2) return;
    slideshowState.timer = setInterval(() => goToSlide(slideshowState.index + 1), slideshowState.intervalMs);
}

function stopSlideshowAutoplay() {
    if (slideshowState.timer) {
        clearInterval(slideshowState.timer);
        slideshowState.timer = null;
    }
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
            // Role-only: this page never names individual staff (only the
            // contractor's business name), so the timeline follows the same
            // convention — see citizen/api/project-timeline.php.
            renderProjectTimeline('projectTimelineSection', projectId, {
                endpoint: 'citizen/api/project-timeline.php',
                showActorName: false,
            });
            if (data.project && data.project.project_code && typeof renderProjectQR === 'function') {
                renderProjectQR('citizenProjectQRTarget', data.project.project_code, 110);
            }
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
    const galleryPhotos = data.gallery_photos || [];
    const ratingSummary = data.rating_summary || { count: 0, average: 0 };
    const ratings = data.ratings || [];
    const ownRating = data.own_rating || null;
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

        <div class="detail-section" style="display:flex;align-items:center;gap:14px;">
            <div id="citizenProjectQRTarget" style="padding:8px;background:#fff;border-radius:8px;border:1px solid var(--border);flex-shrink:0;"></div>
            <div>
                <h5 style="margin-bottom:4px;">Scan to Share</h5>
                <p class="empty-state-compact" style="margin:0;">This QR code opens this project's public transparency page — anyone can scan or open the link, no account needed.</p>
            </div>
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

        ${galleryPhotos.length ? `
        <div class="detail-section">
            <h5>Blueprints &amp; Gallery</h5>
            <div class="detail-photos">
                ${galleryPhotos.map(ph => `
                    <a href="${citizenUrl(escapeHtml(ph.file_path))}" target="_blank" rel="noopener" title="${escapeHtml(ph.title || 'Project photo')}" style="position:relative;">
                        <img src="${citizenUrl(escapeHtml(ph.file_path))}" alt="${escapeHtml(ph.title || 'Project photo')}" loading="lazy">
                        ${Number(ph.is_cover) === 1 ? '<span class="gallery-cover-tag">Cover</span>' : ''}
                    </a>
                `).join('')}
            </div>
        </div>` : ''}

        <div class="detail-section">
            <h5>Activity Timeline</h5>
            <div id="projectTimelineSection"></div>
        </div>

        <div class="detail-section">
            <h5>Ratings &amp; Feedback</h5>
            <div class="rating-summary">
                <div class="rating-summary-score">${ratingSummary.average || 0}</div>
                <div>
                    <div class="star-display">${renderStarIcons(Math.round(ratingSummary.average || 0))}</div>
                    <span class="profile-label">${ratingSummary.count} rating${ratingSummary.count === 1 ? '' : 's'}</span>
                </div>
            </div>

            ${data.citizen_verified && data.rating_eligible ? `
            <form id="projectRatingForm" class="rating-form" onsubmit="submitProjectRating(event, ${p.id})">
                ${ownRating && ownRating.status !== 'approved' ? `<div class="rating-own-status">Your review: <strong>${ratingStatusLabel(ownRating.status)}</strong>${(ownRating.status === 'rejected' || ownRating.status === 'flagged') && ownRating.decision_remarks ? ' — ' + escapeHtml(ownRating.decision_remarks) : ''}</div>` : ''}
                <div class="star-input" id="ratingStarInput">
                    ${[1, 2, 3, 4, 5].map(n => `
                        <button type="button" class="star-input-btn${ownRating && Number(ownRating.rating) >= n ? ' active' : ''}" data-star="${n}" onclick="setRatingStars(${n})" aria-label="${n} star${n === 1 ? '' : 's'}">★</button>
                    `).join('')}
                </div>
                <textarea class="form-input" name="comment" rows="3" maxlength="1000" placeholder="Share your experience with this project (optional)">${ownRating && ownRating.comment ? escapeHtml(ownRating.comment) : ''}</textarea>
                <label class="rating-anon-toggle">
                    <input type="checkbox" name="anonymous" value="1" ${ownRating && Number(ownRating.is_anonymous) === 1 ? 'checked' : ''}>
                    Post as Anonymous (your name won't be shown on this review)
                </label>
                <div class="rating-form-actions">
                    <button class="btn-primary" type="submit">${ownRating ? 'Update Rating' : 'Submit Rating'}</button>
                    ${ownRating ? `<button type="button" class="btn-secondary" onclick="deleteProjectRating(${p.id})">Delete</button>` : ''}
                </div>
                <p class="rating-form-status" id="ratingFormStatus"></p>
            </form>
            ` : (!data.citizen_verified
                ? '<p class="empty-state empty-state-compact">Verify your account in Profile to rate this project.</p>'
                : '<p class="empty-state empty-state-compact">Ratings open once this project is actively underway or completed.</p>')}

            <div class="rating-list">
                ${ratings.length ? ratings.map(r => `
                    <div class="rating-item">
                        <div class="rating-item-head">
                            <span class="star-display">${renderStarIcons(Number(r.rating))}</span>
                            <strong>${Number(r.is_anonymous) === 1 ? 'Anonymous' : escapeHtml(r.citizen_name)}</strong>
                            <span class="update-date">${formatDate(r.created_at)}</span>
                        </div>
                        ${r.comment ? `<p class="rating-item-comment">${escapeHtml(r.comment)}</p>` : ''}
                    </div>
                `).join('') : '<p class="empty-state empty-state-compact">No ratings yet. Be the first to rate this project.</p>'}
            </div>
        </div>
    `;
}

function renderStarIcons(count) {
    return [1, 2, 3, 4, 5].map(n => `<span class="star${n <= count ? ' filled' : ''}">★</span>`).join('');
}

function ratingStatusLabel(status) {
    return {
        pending: 'Pending review',
        approved: 'Approved — visible to the public',
        rejected: 'Not approved',
        flagged: 'Flagged for review',
        archived: 'Archived',
    }[status] || status;
}

function setRatingStars(n) {
    document.querySelectorAll('#ratingStarInput .star-input-btn').forEach(btn => {
        btn.classList.toggle('active', Number(btn.dataset.star) <= n);
    });
}

function submitProjectRating(event, projectId) {
    event.preventDefault();
    const formEl = event.target;
    const statusEl = document.getElementById('ratingFormStatus');
    const stars = document.querySelectorAll('#ratingStarInput .star-input-btn.active').length;

    if (!stars) {
        statusEl.textContent = 'Please select a star rating.';
        statusEl.className = 'rating-form-status error';
        return;
    }

    const formData = new FormData(formEl);
    formData.append('project_id', projectId);
    formData.append('rating', stars);
    formData.append('_csrf', window.CSRF_TOKEN || '');

    fetch(citizenUrl('citizen/api/project-rating.php') + '?action=submit', {
        method: 'POST',
        body: formData,
    })
        .then(res => res.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Unable to save your rating.');
            openProjectDetail(projectId);
        })
        .catch(error => {
            statusEl.textContent = error.message;
            statusEl.className = 'rating-form-status error';
        });
}

function deleteProjectRating(projectId) {
    if (!window.confirm('Delete your rating for this project?')) return;

    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('_csrf', window.CSRF_TOKEN || '');

    fetch(citizenUrl('citizen/api/project-rating.php') + '?action=delete', {
        method: 'POST',
        body: formData,
    })
        .then(res => res.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Unable to delete your rating.');
            openProjectDetail(projectId);
        })
        .catch(error => {
            window.alert(error.message);
        });
}

// ===== Feedback detail modal (full view of a citizen's own submitted report) =====
// Reuses the array already fetched by loadTrackedFeedback() / listStates —
// no extra round trip needed since the citizen can only ever view their own
// reports, which are already sitting in the Track Complaints list cache.
function setupFeedbackDetailModal() {
    const modal = document.getElementById('feedbackDetailModal');
    if (!modal) return;

    const closeModal = () => { modal.style.display = 'none'; };
    document.getElementById('feedbackDetailClose').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
    });
}

function openFeedbackDetail(feedbackId) {
    const modal = document.getElementById('feedbackDetailModal');
    const body = document.getElementById('feedbackDetailBody');
    if (!modal || !body) return;

    const item = (listStates.trackedFeedback?.data || []).find(f => Number(f.id) === Number(feedbackId));
    if (!item) {
        body.innerHTML = '<p class="empty-state">Could not find this report. Please refresh and try again.</p>';
    } else {
        body.innerHTML = renderFeedbackDetail(item);
    }
    modal.style.display = 'flex';
}

function renderFeedbackDetail(f) {
    const photos = Array.isArray(f.photos) ? f.photos : [];
    const isMaintenance = f.concern_type === 'maintenance';
    const title = f.project_name || (isMaintenance ? 'Maintenance Concern' : 'Community Concern');

    const cimmBadge = isMaintenance ? (() => {
        const sync = f.cimm_sync_status || 'none';
        const ref = f.cimm_reference ? ` · ${escapeHtml(f.cimm_reference)}` : '';
        if (sync === 'synced') return `<span class="fb-cimm-chip fb-cimm-synced">Sent to CIMMS${ref}</span>`;
        if (sync === 'failed' || sync === 'pending') return `<span class="fb-cimm-chip fb-cimm-pending">CIMMS sync: ${escapeHtml(sync)}</span>`;
        return `<span class="fb-cimm-chip">CIMMS route</span>`;
    })() : '';

    return `
        <div class="detail-header">
            <span class="feedback-status status-${escapeHtml(f.status)}">${capitalizeFirst(f.status)}</span>
            <h4 class="detail-name">${escapeHtml(title)}</h4>
            <p class="detail-code">${feedbackCategoryLabel(f.category)} · Submitted ${formatDate(f.created_at)}</p>
            ${cimmBadge}
        </div>

        <div class="detail-stats">
            <div class="detail-stat"><span class="profile-label">Priority</span><strong><span class="priority-dot priority-${escapeHtml(f.priority)}"></span> ${capitalizeFirst(f.priority)}</strong></div>
            <div class="detail-stat"><span class="profile-label">Location</span><strong>${f.barangay ? (f.district ? 'Brgy. ' + escapeHtml(f.barangay) + ', ' + escapeHtml(f.district) : escapeHtml(f.barangay)) : (f.location ? escapeHtml(f.location) : 'Not specified')}</strong></div>
        </div>

        <div class="detail-section">
            <h5>Full Report</h5>
            <p class="detail-desc">${escapeHtml(f.message || '')}</p>
        </div>

        ${f.latitude && f.longitude ? `
        <div class="detail-section">
            <h5>Pinned Location</h5>
            <a class="feedback-pin-link" href="https://www.openstreetmap.org/?mlat=${encodeURIComponent(f.latitude)}&mlon=${encodeURIComponent(f.longitude)}#map=18/${encodeURIComponent(f.latitude)}/${encodeURIComponent(f.longitude)}" target="_blank" rel="noopener">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" class="meta-icon"><path fill-rule="evenodd" d="M12 1.586l-4 4v12.828l4-4V1.586zM3.707 3.293A1 1 0 002 4v10a1 1 0 00.293.707L6 18.414V5.586L3.707 3.293zM17.707 5.293L14 1.586v12.828l2.293 2.293A1 1 0 0018 16V6a1 1 0 00-.293-.707z" clip-rule="evenodd"/></svg>
                View on map
            </a>
        </div>` : ''}

        ${photos.length ? `
        <div class="detail-section">
            <h5>Attached Photos</h5>
            <div class="detail-photos">
                ${photos.map(p => `
                    <a href="${citizenUrl(escapeHtml(p))}" target="_blank" rel="noopener" title="Report photo">
                        <img src="${citizenUrl(escapeHtml(p))}" alt="Report photo" loading="lazy">
                    </a>
                `).join('')}
            </div>
        </div>` : '<p class="empty-state empty-state-compact">No photos attached to this report.</p>'}
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
    } else if (pageName === 'track-feedback') {
        loadTrackedFeedback();
        initTrackFeedbackMap();
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
    // cfg.views (optional) lets one list key render into more than one
    // container with its own rowHtml/columns — e.g. Public Projects' Cards
    // vs Table toggle — while sharing the same fetched data, search query,
    // filter, and pager. Falls back to a single implicit view built from
    // cfg itself when cfg.views isn't set, so trackedFeedback/expenses (one
    // view each) don't need to change.
    const views = cfg.views || { default: cfg };
    const defaultView = cfg.views ? (loadListViewPref(key) || cfg.defaultView || Object.keys(views)[0]) : 'default';
    listStates[key] = { cfg, views, data: [], page: 1, view: defaultView };
    if (cfg.views) applyListViewUI(key);

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

// ===== Multi-view lists (e.g. Public Projects' Cards/Table toggle) =====
// The chosen view is remembered per list key across visits — a citizen who
// prefers the table shouldn't have to re-toggle it every time they come back.
function listViewPrefKey(key) {
    return `ipms_citizen_list_view_${key}`;
}
function loadListViewPref(key) {
    try { return localStorage.getItem(listViewPrefKey(key)); } catch { return null; }
}
function saveListViewPref(key, view) {
    try { localStorage.setItem(listViewPrefKey(key), view); } catch { /* private browsing, etc. — fine to skip */ }
}

// Shows the active view's container (hides the others) and syncs the
// toggle buttons' active state — used both on first render and on switch.
function applyListViewUI(key) {
    const state = listStates[key];
    if (!state) return;

    Object.entries(state.views).forEach(([v, viewCfg]) => {
        const wrap = document.getElementById(viewCfg.wrapId || viewCfg.bodyId);
        if (wrap) wrap.style.display = v === state.view ? '' : 'none';
    });
    document.querySelectorAll(`.view-toggle-btn[data-list="${key}"]`).forEach(btn => {
        btn.classList.toggle('active', btn.dataset.view === state.view);
    });
}

function setListView(key, view) {
    const state = listStates[key];
    if (!state || !state.views[view] || state.view === view) return;

    state.view = view;
    state.page = 1;
    saveListViewPref(key, view);
    applyListViewUI(key);
    renderListControl(key);
}

function renderListControl(key) {
    const state = listStates[key];
    if (!state) return;
    const { cfg } = state;
    const activeView = state.views[state.view] || Object.values(state.views)[0];
    const body = document.getElementById(activeView.bodyId);
    if (!body) return;

    const query = (document.getElementById(cfg.searchId)?.value || '').trim().toLowerCase();
    const filterVal = cfg.filterId ? (document.getElementById(cfg.filterId)?.value || '') : '';

    const filtered = state.data.filter(item => {
        if (filterVal && cfg.matchesFilter && !cfg.matchesFilter(item, filterVal)) return false;
        if (query && !cfg.searchText(item).toLowerCase().includes(query)) return false;
        return true;
    });

    const pageSize = activeView.pageSize || cfg.pageSize || LIST_PAGE_SIZE;
    const total = filtered.length;
    const lastPage = Math.max(1, Math.ceil(total / pageSize));
    state.page = Math.min(Math.max(1, state.page), lastPage);
    const start = (state.page - 1) * pageSize;
    const end = Math.min(start + pageSize, total);
    const pageItems = filtered.slice(start, end);

    // activeView.columns marks a <tbody> target; without it the control
    // renders into a plain list container (e.g. the tracker cards view).
    const emptyMsg = state.data.length ? 'No results match your search.' : cfg.emptyText;
    body.innerHTML = pageItems.length
        ? pageItems.map(activeView.rowHtml).join('')
        : (activeView.columns
            ? `<tr><td colspan="${activeView.columns}" class="table-empty">${emptyMsg}</td></tr>`
            : `<p class="empty-state">${emptyMsg}</p>`);

    const info = document.getElementById(cfg.infoId);
    if (info) info.textContent = total ? `${start + 1}–${end} of ${total}` : '0 of 0';
    const prev = document.getElementById(cfg.prevId);
    if (prev) prev.disabled = state.page <= 1;
    const next = document.getElementById(cfg.nextId);
    if (next) next.disabled = end >= total;
}

function setupListControls() {
    // Public Projects: one dataset (citizen/api/project-status.php), two
    // presentations. Cards (default) walks each project's workflow stage;
    // Table is the dense scanning view the old standalone "Public Projects"
    // page used. Toggled by setListView('projects', ...) — see
    // setupViewToggles() — search/filter/pager stay shared across both.
    initListControl('projects', {
        searchId: 'projectSearch', filterId: 'statusFilter',
        infoId: 'projectsPagerInfo', prevId: 'projectsPagerPrev', nextId: 'projectsPagerNext',
        emptyText: 'No projects found',
        searchText: p => `${p.name} ${p.location || ''} ${p.description || ''}`,
        matchesFilter: (p, status) => p.status === status,
        defaultView: 'cards',
        views: {
            cards: {
                bodyId: 'projectsCardBody', pageSize: 5,
                rowHtml: createTrackerCard,
            },
            table: {
                bodyId: 'projectsTableBody', wrapId: 'projectsTableWrap', columns: 5,
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
            },
        },
    });

    initListControl('announcements', {
        bodyId: 'announcementsBody', searchId: 'annSearch', filterId: 'annCategoryFilter',
        infoId: 'annPagerInfo', prevId: 'annPagerPrev', nextId: 'annPagerNext',
        pageSize: 9, emptyText: 'No announcements yet — check back soon.',
        searchText: a => `${a.title} ${a.body || ''} ${a.project_name || ''}`,
        matchesFilter: (a, category) => a.category === category,
        rowHtml: createAnnouncementCard,
    });

    initListControl('trackedFeedback', {
        bodyId: 'trackedFeedbackBody', searchId: 'tfSearch',
        infoId: 'tfPagerInfo', prevId: 'tfPagerPrev', nextId: 'tfPagerNext',
        columns: 7, emptyText: 'No feedback submissions yet',
        searchText: f => `${f.project_name || ''} ${f.message || ''} ${feedbackCategoryLabel(f.category)} ${f.barangay || ''} ${f.district || ''} ${f.location || ''}`,
        rowHtml: f => `
            <tr>
                <td>
                    <div class="cell-title">${escapeHtml(f.project_name || 'Community Concern')}</div>
                    <div class="cell-sub cell-clamp" title="${escapeHtml(f.message || '')}">${escapeHtml(f.message || '')}</div>
                </td>
                <td class="cell-nowrap">${feedbackCategoryLabel(f.category)}</td>
                <td class="cell-nowrap"><span class="priority-dot priority-${escapeHtml(f.priority)}"></span>${capitalizeFirst(f.priority)}</td>
                <td class="cell-nowrap">${f.barangay ? (f.district ? 'Brgy. ' + escapeHtml(f.barangay) : escapeHtml(f.barangay)) : (f.location ? escapeHtml(f.location) : '—')}</td>
                <td class="cell-nowrap">${formatDate(f.created_at)}</td>
                <td><span class="feedback-status status-${escapeHtml(f.status)}">${capitalizeFirst(f.status)}</span></td>
                <td class="cell-nowrap"><button type="button" class="btn-outline" style="padding:6px 14px;font-size:.78rem;" onclick="openFeedbackDetail(${Number(f.id)})">View</button></td>
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

// Delegated so it works for any current/future .view-toggle-btn without
// needing a per-button listener registered by hand.
function setupViewToggles() {
    document.querySelectorAll('.view-toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const listKey = btn.dataset.list;
            const view = btn.dataset.view;
            if (listKey && view) setListView(listKey, view);
        });
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

// ===== Announcements (events, new-project spotlights, official notices/posters) =====
const ANNOUNCEMENT_CATEGORY_LABELS = { event: 'Event', new_project: 'New Project', notice: 'Notice', general: 'General' };
const ANNOUNCEMENT_CATEGORY_CLASS = { event: 'ann-badge-event', new_project: 'ann-badge-project', notice: 'ann-badge-notice', general: 'ann-badge-general' };

function announcementCategoryBadge(category) {
    return `<span class="ann-badge ${ANNOUNCEMENT_CATEGORY_CLASS[category] || 'ann-badge-general'}">${ANNOUNCEMENT_CATEGORY_LABELS[category] || category}</span>`;
}

function loadAnnouncements() {
    fetch(citizenUrl('citizen/api/announcements.php'))
        .then(res => res.json())
        .then(data => {
            setListData('announcements', data.announcements);
        })
        .catch(err => console.error('Error loading announcements:', err));
}

function createAnnouncementCard(a) {
    const rawBody = a.body || '';
    const excerpt = rawBody.length > 160 ? rawBody.slice(0, 160).trim() + '…' : rawBody;
    const isEvent = a.category === 'event' && a.event_date;

    const pinned = Number(a.is_pinned) === 1;
    return `
        <article class="announcement-card row-click${pinned ? ' announcement-card-pinned' : ''}" onclick="openAnnouncementDetail(${Number(a.id)})" title="View announcement">
            ${a.poster_path ? `<div class="announcement-card-poster"><img src="${citizenUrl(escapeHtml(a.poster_path))}" alt="" loading="lazy"></div>` : ''}
            <div class="announcement-card-body">
                <div class="announcement-card-head">
                    ${announcementCategoryBadge(a.category)}
                    ${Number(a.is_pinned) === 1 ? '<span class="ann-pin" title="Pinned">📌</span>' : ''}
                </div>
                <h3 class="announcement-card-title">${escapeHtml(a.title)}</h3>
                ${isEvent ? `
                <p class="announcement-card-meta">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" class="meta-icon"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
                    ${formatDate(a.event_date)}${a.event_time ? ' · ' + escapeHtml(a.event_time.slice(0, 5)) : ''}${a.event_location ? ' · ' + escapeHtml(a.event_location) : ''}
                </p>` : ''}
                <p class="announcement-card-excerpt">${escapeHtml(excerpt)}</p>
                <div class="announcement-card-foot">
                    ${a.project_name ? `<span class="announcement-card-project">${escapeHtml(a.project_name)}</span>` : '<span></span>'}
                    <span class="announcement-card-date">${formatDate(a.published_at || a.created_at)}</span>
                </div>
            </div>
        </article>`;
}

function setupAnnouncementDetailModal() {
    const modal = document.getElementById('announcementDetailModal');
    if (!modal) return;

    const closeModal = () => { modal.style.display = 'none'; };
    document.getElementById('announcementDetailClose').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
    });
}

function openAnnouncementDetail(id) {
    const modal = document.getElementById('announcementDetailModal');
    const body = document.getElementById('announcementDetailBody');
    if (!modal || !body) return;

    const item = (listStates.announcements?.data || []).find(a => Number(a.id) === Number(id));
    body.innerHTML = item
        ? renderAnnouncementDetail(item)
        : '<p class="empty-state">Could not find this announcement. Please refresh and try again.</p>';
    modal.style.display = 'flex';
}

function renderAnnouncementDetail(a) {
    const isEvent = a.category === 'event' && a.event_date;

    return `
        <div class="detail-header">
            ${announcementCategoryBadge(a.category)}
            <h4 class="detail-name">${escapeHtml(a.title)}</h4>
            <p class="detail-code">${formatDate(a.published_at || a.created_at)}</p>
        </div>
        ${a.poster_path ? `
        <div class="detail-section">
            <img src="${citizenUrl(escapeHtml(a.poster_path))}" alt="" style="width:100%;border-radius:8px;border:1px solid var(--border, #dbe4ef);">
        </div>` : ''}
        ${isEvent ? `
        <div class="detail-stats">
            <div class="detail-stat"><span class="profile-label">Date</span><strong>${formatDate(a.event_date)}</strong></div>
            ${a.event_time ? `<div class="detail-stat"><span class="profile-label">Time</span><strong>${escapeHtml(a.event_time.slice(0, 5))}</strong></div>` : ''}
            ${a.event_location ? `<div class="detail-stat"><span class="profile-label">Location</span><strong>${escapeHtml(a.event_location)}</strong></div>` : ''}
        </div>` : ''}
        <div class="detail-section">
            <p class="detail-desc" style="white-space:pre-wrap;">${escapeHtml(a.body)}</p>
        </div>
        ${a.project_id ? `
        <div class="detail-section">
            <button type="button" class="btn-outline" onclick="document.getElementById('announcementDetailModal').style.display='none'; openProjectDetail(${Number(a.project_id)});">
                View Related Project: ${escapeHtml(a.project_name || '')}
            </button>
        </div>` : ''}
    `;
}

// Public Projects (merged Cards/Table view) is backed by project-status.php
// rather than projects.php — it's the richer superset (milestone counts,
// spend-to-date, delay reports, latest photo) the Cards view needs, and the
// Table view simply ignores the extra fields it doesn't use.
function loadProjects() {
    fetch(citizenUrl('citizen/api/project-status.php'))
        .then(res => res.json())
        .then(data => {
            setListData('projects', data.projects);
        })
        .catch(err => console.error('Error loading projects:', err));
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
                    ${item.barangay || item.location ? `
                    <span>
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" class="meta-icon"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                        ${item.barangay ? (item.district ? 'Brgy. ' + escapeHtml(item.barangay) + ', ' + escapeHtml(item.district) : escapeHtml(item.barangay)) : escapeHtml(item.location)}
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
    const addr = document.getElementById('cimmsManualAddress');
    if (addr) addr.value = '';
    const suggestions = document.getElementById('cimmsMapSearchDropdown');
    if (suggestions) suggestions.classList.remove('open');
    const districtInfo = document.getElementById('cimmsDistrictInfo');
    if (districtInfo) { districtInfo.style.display = 'none'; districtInfo.className = 'cimms-district-info'; }
    const comboboxLabel = document.getElementById('cimmsComboboxLabel');
    if (comboboxLabel) { comboboxLabel.textContent = 'Select Barangay (Quezon City)'; comboboxLabel.classList.remove('selected'); }
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

    // --- Location: the readonly field opens a replica of CIMMS' own full-
    // screen "Select Location" modal (satellite basemap + street toggle,
    // GPS button, address search, and a district badge that auto-detects
    // from wherever the pin lands, same as citizenrepform.php). Every path
    // ends up with a pinned lat/lng — CIMMS has to geocode a free-text
    // address itself otherwise, which can drift to a different spot each
    // time the request is viewed. ---
    const locationInput = document.getElementById('cimmsLocationInput');
    const mapBackdrop = document.getElementById('cimmsMapBackdrop');
    const manualAddress = document.getElementById('cimmsManualAddress');
    const mapSearch = document.getElementById('cimmsMapSearch');
    const suggestionBox = document.getElementById('cimmsMapSearchDropdown');
    const gpsBtn = document.getElementById('cimmsGpsBtn');
    const labelToggleBtn = document.getElementById('cimmsLabelToggle');
    const layerToggleBtn = document.getElementById('cimmsLayerToggle');
    const districtInfo = document.getElementById('cimmsDistrictInfo');
    const comboboxWrap = document.getElementById('cimmsBarangayCombobox');
    const comboboxDisplay = document.getElementById('cimmsComboboxDisplay');
    const comboboxLabel = document.getElementById('cimmsComboboxLabel');
    const comboboxDropdown = document.getElementById('cimmsComboboxDropdown');
    const comboboxSearch = document.getElementById('cimmsComboboxSearch');
    const comboboxList = document.getElementById('cimmsComboboxList');
    let cimmsMap = null;
    let cimmsSatelliteLayer = null;
    let cimmsStreetLayer = null;
    let cimmsUsingSatellite = true;
    let cimmsLabelsEnabled = true;
    let cimmsLocationLabels = [];
    let cimmsMapMarker = null;
    let cimmsGeoLayer = null;
    let cimmsPickedAddress = '';
    let cimmsPickedLat = null;
    let cimmsPickedLng = null;
    let cimmsDetectedDistrict = '';
    let cimmsSelectedBarangay = '';
    let cimmsGeoJsonCache = null;
    let debounceTimer = null;

    // Same hand-picked major-locations list CIMMS' own map uses (its own
    // satellite basemap has no built-in labels either) — kept identical so
    // the maintenance path reads exactly like the real CIMMS request form.
    const CIMMS_MAJOR_LOCATIONS = [
        { name: 'Fairview', lat: 14.7234, lng: 121.0667 }, { name: 'Novaliches', lat: 14.7267, lng: 121.0512 },
        { name: 'Commonwealth', lat: 14.7045, lng: 121.1156 }, { name: 'San Martin de Porres', lat: 14.7423, lng: 121.0312 },
        { name: 'Lagro', lat: 14.7189, lng: 121.0778 }, { name: 'Sauyo', lat: 14.7289, lng: 121.0612 },
        { name: 'Talipapa', lat: 14.7234, lng: 121.0534 }, { name: 'Batasan Hills', lat: 14.6883, lng: 121.1089 },
        { name: 'Payatas', lat: 14.7138, lng: 121.1034 }, { name: 'UP Diliman', lat: 14.6538, lng: 121.0682 },
        { name: 'Cubao', lat: 14.6223, lng: 121.0500 }, { name: 'Project 6', lat: 14.6423, lng: 121.0447 },
        { name: 'Project 8', lat: 14.6467, lng: 121.0334 }, { name: 'Tandang Sora', lat: 14.6777, lng: 121.0557 },
        { name: 'Kamuning', lat: 14.6234, lng: 121.0371 }, { name: 'Loyola Heights', lat: 14.6398, lng: 121.0775 },
        { name: 'Libis', lat: 14.6345, lng: 121.0612 }, { name: 'White Plains', lat: 14.6267, lng: 121.0589 },
        { name: 'Blue Ridge', lat: 14.6956, lng: 121.0500 }, { name: 'Novaliches West', lat: 14.7167, lng: 121.0378 },
        { name: 'Sangandaan', lat: 14.6534, lng: 121.0156 }, { name: 'Araneta Center', lat: 14.6178, lng: 121.0523 },
        { name: 'Katipunan', lat: 14.6612, lng: 121.0443 }, { name: 'Teachers Village', lat: 14.6240, lng: 121.0501 }
    ];

    function cimmsAddLocationLabels() {
        cimmsLocationLabels.forEach(label => cimmsMap && cimmsMap.removeLayer && cimmsMap.removeLayer(label));
        cimmsLocationLabels = CIMMS_MAJOR_LOCATIONS.map(loc =>
            L.marker([loc.lat, loc.lng], { icon: L.divIcon({ className: 'cimms-map-label', html: loc.name, iconSize: null }), interactive: false })
        );
        if (cimmsUsingSatellite && cimmsMap && cimmsLabelsEnabled) {
            cimmsLocationLabels.forEach(label => label.addTo(cimmsMap));
        }
    }

    function cimmsUpdateLabelsVisibility() {
        if (!cimmsMap) return;
        if (cimmsUsingSatellite && cimmsLabelsEnabled) {
            cimmsLocationLabels.forEach(label => { if (!cimmsMap.hasLayer(label)) label.addTo(cimmsMap); });
        } else {
            cimmsLocationLabels.forEach(label => { if (cimmsMap.hasLayer(label)) cimmsMap.removeLayer(label); });
        }
        cimmsUpdateLabelToggleButton();
    }

    function cimmsUpdateLabelToggleButton() {
        if (!labelToggleBtn) return;
        if (!cimmsUsingSatellite) {
            labelToggleBtn.classList.add('disabled');
            labelToggleBtn.disabled = true;
            labelToggleBtn.title = 'Labels only available in satellite view';
        } else {
            labelToggleBtn.classList.remove('disabled');
            labelToggleBtn.disabled = false;
            labelToggleBtn.title = cimmsLabelsEnabled ? 'Hide location labels' : 'Show location labels';
        }
    }

    function cimmsSetAddress(address) {
        cimmsPickedAddress = address;
        if (manualAddress) manualAddress.value = address;
        if (locationInput) locationInput.value = address;
    }

    function cimmsReverseGeocode(latlng) {
        if (manualAddress) manualAddress.value = 'Looking up address…';
        const fallback = latlng.lat.toFixed(5) + ', ' + latlng.lng.toFixed(5);
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}&addressdetails=1`)
            .then(res => res.json())
            .then(data => cimmsSetAddress(data.display_name || fallback))
            .catch(() => cimmsSetAddress(fallback));
    }

    // Ray-casting point-in-polygon test against the QC barangay GeoJSON,
    // used to auto-detect (and color) the district the pin landed in —
    // mirrors CIMMS' own districtInfo banner.
    function cimmsPointInRing(lat, lng, ring) {
        let inside = false;
        for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            const xi = ring[i][0], yi = ring[i][1];
            const xj = ring[j][0], yj = ring[j][1];
            const intersect = ((yi > lat) !== (yj > lat)) &&
                (lng < (xj - xi) * (lat - yi) / (yj - yi) + xi);
            if (intersect) inside = !inside;
        }
        return inside;
    }

    function cimmsPointInFeature(lat, lng, feature) {
        const geom = feature.geometry;
        if (!geom) return false;
        const polys = geom.type === 'Polygon' ? [geom.coordinates] : (geom.type === 'MultiPolygon' ? geom.coordinates : []);
        return polys.some(poly => poly.length > 0 && cimmsPointInRing(lat, lng, poly[0]));
    }

    function cimmsLoadGeoJson() {
        if (cimmsGeoJsonCache) return Promise.resolve(cimmsGeoJsonCache);
        if (!window.QC_GEOJSON_URL) return Promise.resolve(null);
        return fetch(window.QC_GEOJSON_URL).then(res => res.json()).then(data => {
            cimmsGeoJsonCache = data;
            return data;
        }).catch(() => null);
    }

    const CIMMS_DISTRICT_BADGE_CLASS = {
        'District 1': 'd1', 'District 2': 'd2', 'District 3': 'd3',
        'District 4': 'd4', 'District 5': 'd5', 'District 6': 'd6'
    };

    function cimmsShowDistrict(district) {
        cimmsDetectedDistrict = district || '';
        if (!districtInfo) return;
        districtInfo.className = 'cimms-district-info';
        if (!district) {
            districtInfo.style.display = 'none';
            return;
        }
        const badgeClass = CIMMS_DISTRICT_BADGE_CLASS[district];
        if (badgeClass) districtInfo.classList.add(badgeClass);
        districtInfo.textContent = '📌 ' + district;
        districtInfo.style.display = 'block';
    }

    function cimmsHighlightBarangay(geoName, district) {
        if (!cimmsGeoLayer) return;
        cimmsGeoLayer.eachLayer(layer => cimmsGeoLayer.resetStyle(layer));
        if (!geoName) return;
        const color = QC_DISTRICT_COLORS[district] || '#2b6cb0';
        cimmsGeoLayer.eachLayer(layer => {
            if (layer.feature && layer.feature.properties && layer.feature.properties.adm4_en === geoName) {
                layer.setStyle({ color: color, weight: 3, fillColor: color, fillOpacity: 0.35, dashArray: null });
                if (layer.bringToFront) layer.bringToFront();
            }
        });
    }

    function cimmsDetectDistrict(latlng) {
        cimmsLoadGeoJson().then(geojson => {
            if (!geojson) return;
            const barangayIndex = buildBarangayIndex();
            const match = geojson.features.find(f => cimmsPointInFeature(latlng.lat, latlng.lng, f));
            const info = match ? barangayIndex[match.properties.adm4_en] : null;
            cimmsShowDistrict(info ? info.district : '');
            cimmsHighlightBarangay(match ? match.properties.adm4_en : null, info ? info.district : '');
            cimmsSetBarangayLabel(info ? info.name : '', info ? info.district : '');
        });
    }

    // --- Barangay quick-jump combobox — same as CIMMS' own barangaySelect:
    // picking an entry moves the pin to that barangay's polygon centroid,
    // fits the map to it, and fills the address/district the same way a
    // map tap would. ---
    function cimmsSetBarangayLabel(name, district) {
        if (!comboboxLabel) return;
        if (!name) {
            comboboxLabel.textContent = 'Select Barangay (Quezon City)';
            comboboxLabel.classList.remove('selected');
            return;
        }
        comboboxLabel.textContent = name + ' (' + district + ')';
        comboboxLabel.classList.add('selected');
    }

    function cimmsBarangayCentroid(geoName) {
        return cimmsLoadGeoJson().then(geojson => {
            if (!geojson) return null;
            const feature = geojson.features.find(f => f.properties && f.properties.adm4_en === geoName);
            if (!feature) return null;
            try {
                return L.geoJSON(feature).getBounds().getCenter();
            } catch (e) {
                return null;
            }
        });
    }

    function cimmsBuildBarangayList() {
        const list = [];
        Object.keys(window.QC_DISTRICTS || {}).forEach(district => {
            window.QC_DISTRICTS[district].forEach(entry => {
                list.push({ name: entry.name, district: district, geo: entry.geo || entry.name });
            });
        });
        return list;
    }

    function cimmsSetupBarangayCombobox() {
        if (!comboboxDisplay || !comboboxDropdown || !comboboxSearch || !comboboxList) return;
        const allBarangays = cimmsBuildBarangayList();
        let isOpen = false;
        let highlightedIndex = -1;
        let filtered = allBarangays;

        function renderList(data) {
            comboboxList.innerHTML = '';
            highlightedIndex = -1;
            if (!data.length) {
                comboboxList.innerHTML = '<div class="cimms-combobox-no-results">No results found</div>';
                return;
            }
            data.forEach(b => {
                const item = document.createElement('div');
                item.className = 'cimms-combobox-option' + (b.name === cimmsSelectedBarangay ? ' selected-option' : '');
                item.innerHTML = '<span class="opt-name"></span><span class="opt-district"></span>';
                item.querySelector('.opt-name').textContent = b.name;
                item.querySelector('.opt-district').textContent = b.district;
                item.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    cimmsSelectBarangay(b);
                });
                comboboxList.appendChild(item);
            });
        }

        function filterList(query) {
            const q = query.toLowerCase().trim();
            filtered = q ? allBarangays.filter(b => b.name.toLowerCase().includes(q) || b.district.toLowerCase().includes(q)) : allBarangays;
            renderList(filtered);
        }

        function openDropdown() {
            if (isOpen) return;
            isOpen = true;
            comboboxDisplay.classList.add('open');
            comboboxDropdown.classList.add('open');
            comboboxSearch.value = '';
            filterList('');
            setTimeout(() => comboboxSearch.focus(), 50);
        }

        function closeDropdown() {
            if (!isOpen) return;
            isOpen = false;
            comboboxDisplay.classList.remove('open');
            comboboxDropdown.classList.remove('open');
            comboboxSearch.value = '';
            highlightedIndex = -1;
        }

        function updateHighlight() {
            const items = comboboxList.querySelectorAll('.cimms-combobox-option');
            items.forEach((el, i) => {
                el.classList.toggle('highlighted', i === highlightedIndex);
                if (i === highlightedIndex) el.scrollIntoView({ block: 'nearest' });
            });
        }

        function cimmsSelectBarangay(b) {
            cimmsSelectedBarangay = b.name;
            cimmsSetBarangayLabel(b.name, b.district);
            closeDropdown();
            cimmsBarangayCentroid(b.geo).then(center => {
                if (!center || !cimmsMap) return;
                cimmsMap.setView(center, 15);
                cimmsPlacePin(center);
            });
        }

        comboboxDisplay.addEventListener('click', () => (isOpen ? closeDropdown() : openDropdown()));
        comboboxSearch.addEventListener('input', () => filterList(comboboxSearch.value));
        comboboxSearch.addEventListener('keydown', (e) => {
            const items = comboboxList.querySelectorAll('.cimms-combobox-option');
            if (!items.length) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1); updateHighlight(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); highlightedIndex = Math.max(highlightedIndex - 1, 0); updateHighlight(); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                if (highlightedIndex >= 0 && filtered[highlightedIndex]) cimmsSelectBarangay(filtered[highlightedIndex]);
                else if (filtered.length === 1) cimmsSelectBarangay(filtered[0]);
            } else if (e.key === 'Escape') {
                closeDropdown();
            }
        });
        document.addEventListener('click', (e) => {
            if (comboboxWrap && !comboboxWrap.contains(e.target)) closeDropdown();
        });
    }
    cimmsSetupBarangayCombobox();

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
                cimmsDetectDistrict(dragged);
            });
        } else {
            cimmsMapMarker.setLatLng(latlng);
        }
        cimmsPickedLat = latlng.lat;
        cimmsPickedLng = latlng.lng;
        if (knownAddress) cimmsSetAddress(knownAddress);
        else cimmsReverseGeocode(latlng);
        cimmsDetectDistrict(latlng);
    }

    // Satellite (ArcGIS World Imagery) is CIMMS' default view; the toggle
    // button switches to plain OpenStreetMap streets, same as their
    // #mapLayerToggle button.
    function cimmsSetLayer(useSatellite) {
        if (!cimmsMap || !cimmsSatelliteLayer || !cimmsStreetLayer) return;
        cimmsUsingSatellite = useSatellite;
        if (useSatellite) {
            if (cimmsMap.hasLayer(cimmsStreetLayer)) cimmsMap.removeLayer(cimmsStreetLayer);
            if (!cimmsMap.hasLayer(cimmsSatelliteLayer)) cimmsSatelliteLayer.addTo(cimmsMap);
        } else {
            if (cimmsMap.hasLayer(cimmsSatelliteLayer)) cimmsMap.removeLayer(cimmsSatelliteLayer);
            if (!cimmsMap.hasLayer(cimmsStreetLayer)) cimmsStreetLayer.addTo(cimmsMap);
        }
        if (layerToggleBtn) layerToggleBtn.textContent = useSatellite ? 'Street' : 'Satellite';
        cimmsUpdateLabelsVisibility();
    }

    function openCimmsMap() {
        if (!mapBackdrop) return;
        mapBackdrop.classList.add('active');
        loadLeafletOnce().then(() => {
            if (!cimmsMap) {
                cimmsMap = L.map('cimmsMapCanvas', { minZoom: 11, maxZoom: 19 }).setView([14.6760, 121.0437], 12); // Quezon City

                cimmsSatelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    maxZoom: 19,
                    attribution: 'Tiles &copy; Esri'
                });
                cimmsStreetLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                });
                cimmsAddLocationLabels();
                cimmsSetLayer(true);

                cimmsMap.on('click', (e) => cimmsPlacePin(e.latlng));

                // Faint dashed QC boundary (all barangays outlined) — the
                // pin's own barangay gets recolored solid once detected.
                cimmsLoadGeoJson().then(geojson => {
                    if (!geojson || !cimmsMap) return;
                    cimmsGeoLayer = L.geoJSON(geojson, {
                        style: { color: '#2b6cb0', weight: 1, fillColor: '#3b82f6', fillOpacity: 0.03, dashArray: '4, 4', interactive: false }
                    }).addTo(cimmsMap);
                });
            }
            // Leaflet mis-sizes maps created while hidden; re-measure now that the modal is open.
            setTimeout(() => cimmsMap.invalidateSize(), 80);
        }).catch(() => {
            mapBackdrop.classList.remove('active');
            cimmsNotify('error', 'Could not load the map. Please try again.');
        });
    }

    locationInput?.addEventListener('click', openCimmsMap);
    layerToggleBtn?.addEventListener('click', () => cimmsSetLayer(!cimmsUsingSatellite));
    labelToggleBtn?.addEventListener('click', () => {
        if (!cimmsUsingSatellite) return;
        cimmsLabelsEnabled = !cimmsLabelsEnabled;
        cimmsUpdateLabelsVisibility();
    });
    manualAddress?.addEventListener('input', () => { cimmsPickedAddress = manualAddress.value; });
    document.getElementById('cimmsMapCancel')?.addEventListener('click', () => {
        mapBackdrop?.classList.remove('active');
    });
    document.getElementById('cimmsMapUse')?.addEventListener('click', () => {
        const finalAddress = (manualAddress?.value || '').trim();
        if (!finalAddress) {
            cimmsNotify('error', 'Please select or enter a location.');
            return;
        }
        if (locationInput) locationInput.value = finalAddress;
        cimmsPickedAddress = finalAddress;
        mapBackdrop?.classList.remove('active');
    });

    // Search box inside the modal — picking a result jumps the map there
    // and drops the pin with the suggestion's own address text.
    if (mapSearch && suggestionBox) {
        mapSearch.addEventListener('input', () => {
            const query = mapSearch.value.trim();
            clearTimeout(debounceTimer);

            if (query.length < 3) {
                suggestionBox.classList.remove('open');
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
                            suggestionBox.classList.remove('open');
                            return;
                        }

                        qcResults.forEach(place => {
                            const div = document.createElement('div');
                            div.className = 'cimms-map-search-item';
                            div.textContent = place.display_name;
                            div.onclick = () => {
                                suggestionBox.classList.remove('open');
                                mapSearch.value = place.display_name;
                                const latlng = { lat: parseFloat(place.lat), lng: parseFloat(place.lon) };
                                if (cimmsMap) cimmsMap.setView(latlng, 17);
                                cimmsPlacePin(latlng, place.display_name);
                            };
                            suggestionBox.appendChild(div);
                        });

                        suggestionBox.classList.add('open');
                    })
                    .catch(() => {
                        suggestionBox.classList.remove('open');
                    });
            }, 350);
        });

        document.addEventListener('click', e => {
            if (!e.target.closest('.cimms-map-search-wrap')) {
                suggestionBox.classList.remove('open');
            }
        });
    }

    // "My location" — GPS pin with the same reverse-geocode + district fill.
    gpsBtn?.addEventListener('click', () => {
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

        if (cimmsSelectedFiles.length === 0) {
            cimmsNotify('error', 'Please attach at least one photo as evidence.');
            dropzone?.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
        // Auto-detected from where the pin landed (point-in-polygon against the
        // QC barangay GeoJSON) — informational only, never required, since
        // CIMMS' own form has no district field for citizens to fill in.
        formData.append('district', cimmsDetectedDistrict || '');
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
