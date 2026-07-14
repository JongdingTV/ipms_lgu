// Citizen Portal JavaScript
const CITIZEN_BASE_PATH = window.BASE_PATH || '/';

function citizenUrl(path) {
    return CITIZEN_BASE_PATH + path.replace(/^\/+/, '');
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the page
    loadDashboardData();
    setupEventListeners();
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

    setupIdUpload();
    setupUserMenu();
    setupLocationPicker();
    setupFeedbackPhotos();
    setupChangePassword();
}

// ===== Feedback proof photos (max 3, 3MB each) =====
const FEEDBACK_MAX_PHOTOS = 3;
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
    }

    // Scroll to top
    document.querySelector('.content').scrollTop = 0;
}

function loadDashboardData() {
    fetch(citizenUrl('citizen/api/dashboard.php'))
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

    fetch(citizenUrl('citizen/api/projects.php') + '?' + params)
        .then(res => res.json())
        .then(data => {
            displayProjects(data.projects);
        })
        .catch(err => console.error('Error loading projects:', err));
}

function loadProjectStatus() {
    fetch(citizenUrl('citizen/api/project-status.php'))
        .then(res => res.json())
        .then(data => {
            displayProjectStatus(data.projects);
        })
        .catch(err => console.error('Error loading project status:', err));
}

function loadTrackedFeedback() {
    fetch(citizenUrl('citizen/api/my-feedback.php'))
        .then(res => res.json())
        .then(data => {
            displayTrackedFeedback(data.feedback);
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

            displayExpenses(data.expenses);
        })
        .catch(err => console.error('Error loading transparency data:', err));
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
                <img class="status-photo" src="${citizenUrl(escapeHtml(project.latest_photo_path))}" alt="${escapeHtml(project.latest_photo_title || project.name)}">
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

    const photos = Array.isArray(item.photos) ? item.photos : [];

    return `
        <div class="feedback-item">
            <div>
                <div class="feedback-header">
                    <div class="feedback-title">${escapeHtml(item.project_name || 'Community Concern')}</div>
                    <span class="feedback-status ${statusClass}">${capitalizeFirst(item.status)}</span>
                </div>
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
                        ${capitalizeFirst(item.category)}
                    </span>
                    ${item.barangay ? `
                    <span>
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" class="meta-icon"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                        Brgy. ${escapeHtml(item.barangay)}${item.district ? ', ' + escapeHtml(item.district) : ''}
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

    fetch(citizenUrl('citizen/api/submit-feedback.php'), {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Thank you! Your feedback has been submitted successfully.');
            e.target.reset();
            resetLocationPicker();
            renderFeedbackPhotoPreviews();
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
