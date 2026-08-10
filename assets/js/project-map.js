/* ============================================================
   assets/js/project-map.js — the ONE pin/popup/district-tint design
   shared by every project map in the app. Paired with
   assets/css/project-map.css. Requires Leaflet to already be loaded.

   This module only ever builds markers/icons/HTML and hands them back —
   it never fetches data, opens a modal itself, or owns a map instance.
   Each portal keeps its own data-loading and its own "open full detail"
   function; this file only makes every map look and feel the same.

   NOT used by the CIMMS maintenance-report picker (citizen.js) — that
   stays exactly as it was, untouched, per explicit instruction.
   ============================================================ */
(function (global) {
  'use strict';

  // Canonical status → {color, icon}. Same 5-bucket palette that already
  // existed identically in two places (GIS_STATUS_COLORS in script.js,
  // QC_PROJECT_STATUS_COLORS in citizen.js) — unified here, values unchanged.
  var ICONS = {
    cone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l5 15H7l5-15z"/><path d="M5 21h14"/><path d="M9 13h6"/></svg>',
    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg>',
    warn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4l9 16H3l9-16z"/><path d="M12 10v4"/><circle cx="12" cy="17" r=".6" fill="currentColor" stroke="none"/></svg>',
    x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
    pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="2.6"/><path d="M12 21s6-5.7 6-11a6 6 0 10-12 0c0 5.3 6 11 6 11z"/></svg>',
    photo: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="M21 15l-5-5-4 4-3-3-6 6"/></svg>',
    star: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.5 1.3 6.6L12 17.3l-5.9 3.2 1.3-6.6-4.9-4.5 6.6-.8L12 2.5z"/></svg>',
  };

  var PROJECT_MAP_STATUS = {
    completed: { color: '#22c55e', icon: ICONS.check },
    turnover: { color: '#16a34a', icon: ICONS.check },
    delayed: { color: '#ef4444', icon: ICONS.warn },
    cancelled: { color: '#94a3b8', icon: ICONS.x },
  };
  var PROJECT_MAP_DEFAULT_STATUS = { color: '#3b82f6', icon: ICONS.cone }; // active, assigned, bidding, on_hold, etc.
  var PROJECT_MAP_NEUTRAL = { color: '#64748b', icon: ICONS.pin }; // "placing a location" pin — no project behind it yet

  // Unchanged from citizen.js's existing QC_DISTRICT_COLORS.
  var PROJECT_MAP_DISTRICT_COLORS = {
    'District 1': '#2563eb', 'District 2': '#16a34a', 'District 3': '#9333ea',
    'District 4': '#ea580c', 'District 5': '#0d9488', 'District 6': '#db2777',
  };

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
  }

  function statusMeta(status, neutral) {
    if (neutral) return PROJECT_MAP_NEUTRAL;
    return PROJECT_MAP_STATUS[status] || PROJECT_MAP_DEFAULT_STATUS;
  }

  /** Leaflet divIcon for a project pin. Pass {neutral:true} for the "picking a location" marker (no status, no pulse, no popup expected). Pass {color, icon} to bypass the status lookup entirely — e.g. the Admin Dashboard's map colors by health score (healthy/warning/critical), a different axis from workflow status, so it supplies its own color/icon pair while still getting the same pin shape/pulse everyone else uses. */
  function projectMapPinIcon(status, opts) {
    opts = opts || {};
    var meta = (opts.color || opts.icon)
      ? { color: opts.color || PROJECT_MAP_DEFAULT_STATUS.color, icon: opts.icon || PROJECT_MAP_DEFAULT_STATUS.icon }
      : statusMeta(status, opts.neutral);
    return L.divIcon({
      className: 'proj-map-pin',
      html:
        (opts.neutral ? '' : '<span class="pin-pulse" style="background:' + meta.color + '"></span>') +
        '<span class="pin-body" style="background:' + meta.color + '">' +
          '<span class="pin-icon">' + meta.icon + '</span>' +
        '</span>',
      iconSize: [34, 44],
      iconAnchor: [17, 40],
      popupAnchor: [0, -38],
    });
  }

  /** Popup HTML for a project's hover-preview card. `project` fields: name, project_code, location, status, progress, budget, cover_photo (optional), rating_average/rating_count (optional). `photoUrlPrefix` resolves a relative file_path to a servable URL (each portal's own base path). */
  function projectMapPopupHtml(project, photoUrlPrefix) {
    var meta = statusMeta(project.status, false);
    var progress = Math.min(100, Math.max(0, Number(project.progress) || 0));
    var statusLabel = (project.status_label) || String(project.status || '').replace(/_/g, ' ');
    var photo = project.cover_photo
      ? '<img src="' + escapeHtml((photoUrlPrefix || '') + project.cover_photo) + '" alt="">'
      : ICONS.photo;

    var ratingHtml = '';
    var ratingCount = Number(project.rating_count) || 0;
    if (ratingCount > 0) {
      var avg = Number(project.rating_average) || 0;
      var full = Math.round(avg);
      var stars = '';
      for (var i = 0; i < 5; i++) {
        stars += '<span style="opacity:' + (i < full ? 1 : .3) + '">' + ICONS.star + '</span>';
      }
      ratingHtml = '<div class="pmp-stars">' + stars + '<small>' + avg.toFixed(1) + ' (' + ratingCount + ')</small></div>';
    }

    return (
      '<div class="proj-map-popup">' +
        '<div class="pmp-photo">' + photo +
          '<span class="pmp-status" style="background:' + meta.color + '">' + escapeHtml(statusLabel) + '</span>' +
        '</div>' +
        '<strong class="pmp-name">' + escapeHtml(project.name) + '</strong>' +
        '<div class="pmp-meta">' + escapeHtml(project.project_code || '') + (project.location ? ' — ' + escapeHtml(project.location) : '') + '</div>' +
        '<div class="pmp-progress"><span style="width:' + progress + '%;background:' + meta.color + '"></span></div>' +
        '<div class="pmp-row"><span class="pmp-budget">' + escapeHtml(project.budget_label || '') + '</span><span>' + progress + '% complete</span></div>' +
        ratingHtml +
      '</div>'
    );
  }

  /** Wires the shared interaction model onto an existing Leaflet marker: hover opens/closes the preview popup (desktop), click calls onOpenDetail(project.id) directly — a tap on touch devices (no hover) goes straight there too, same as a click. */
  function projectMapBindPin(marker, project, onOpenDetail) {
    marker.on('mouseover', function () { marker.openPopup(); });
    marker.on('mouseout', function () { marker.closePopup(); });
    marker.on('click', function () {
      if (typeof onOpenDetail === 'function') onOpenDetail(Number(project.id));
    });
  }

  /** Builds a barangay-name → district lookup from window.QC_DISTRICTS (already injected by both admin/dashboard.php and citizen/dashboard.php for their location pickers). */
  function districtIndex() {
    var index = {};
    var districts = global.QC_DISTRICTS || {};
    Object.keys(districts).forEach(function (district) {
      (districts[district] || []).forEach(function (entry) {
        index[entry.geo || entry.name] = district;
      });
    });
    return index;
  }

  /** A Leaflet geoJSON `style` callback with the light per-district fill (same treatment already used and approved on the citizen map). Exposed on its own — not just via districtLayer() below — so a caller that needs its own onEachFeature (e.g. the Admin project-location picker's click-to-select-barangay handler) can keep that logic untouched and just swap in this style function. */
  function projectMapDistrictStyle(feature, opts) {
    opts = opts || {};
    var index = opts.index || districtIndex();
    var district = index[feature.properties.adm4_en];
    var color = district ? PROJECT_MAP_DISTRICT_COLORS[district] : '#94a3b8';
    return { color: '#ffffff', weight: 1, fillColor: color, fillOpacity: opts.light === false ? 0.35 : 0.18 };
  }

  /** Renders the QC barangay boundary GeoJSON with the light per-district fill onto `map`, for callers that don't need their own onEachFeature. Returns the Leaflet layer. */
  function projectMapDistrictLayer(map, geojson, opts) {
    opts = opts || {};
    var index = districtIndex();
    return L.geoJSON(geojson, {
      style: function (feature) { return projectMapDistrictStyle(feature, { light: opts.light, index: index }); },
      interactive: !!opts.interactive,
    }).addTo(map);
  }

  global.ProjectMap = {
    STATUS: PROJECT_MAP_STATUS,
    DISTRICT_COLORS: PROJECT_MAP_DISTRICT_COLORS,
    ICONS: ICONS, // glyph strings (check/warn/x/cone/pin/...) for callers with their own color axis (e.g. health, not workflow status)
    pinIcon: projectMapPinIcon,
    popupHtml: projectMapPopupHtml,
    bindPin: projectMapBindPin,
    districtLayer: projectMapDistrictLayer,
    districtStyle: projectMapDistrictStyle,
  };
})(window);
