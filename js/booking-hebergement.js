
window.HebergementBooking = (function () {

  // ── CSS injecté une seule fois ────────────────────────────
  (function injectCSS() {
    if (document.getElementById('hb-style')) return;
    const s = document.createElement('style');
    s.id = 'hb-style';
    s.textContent = `
      .hb-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 1.25rem;
        margin-top: 1rem;
      }
      .hb-card {
        background: var(--white, #fff);
        border: 2px solid var(--gray-200, #e5e7eb);
        border-radius: 16px;
        overflow: hidden;
        cursor: pointer;
        transition: transform .2s, box-shadow .2s, border-color .2s;
      }
      .hb-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(0,0,0,.12);
        border-color: var(--primary-color, #0066cc);
      }
      .hb-card-img {
        width: 100%; height: 170px; object-fit: cover; display: block;
      }
      .hb-card-img-ph {
        width: 100%; height: 170px;
        background: var(--gray-100, #f3f4f6);
        display: flex; align-items: center; justify-content: center;
        font-size: 2.5rem; color: var(--gray-400, #9ca3af);
      }
      .hb-card-body { padding: 1rem; }
      .hb-card-top {
        display: flex; justify-content: space-between;
        align-items: flex-start; gap: .5rem; margin-bottom: .35rem;
      }
      .hb-card-name {
        font-weight: 700; font-size: .95rem;
        color: var(--gray-900, #111827); line-height: 1.3;
      }
      .hb-badge-type {
        font-size: .62rem; font-weight: 700;
        padding: .22rem .6rem; border-radius: 20px;
        white-space: nowrap; letter-spacing: .04em;
      }
      .hb-card-addr { font-size: .75rem; color: var(--gray-500, #6b7280); margin-bottom: .75rem; }
      .hb-card-row {
        display: flex; justify-content: space-between;
        align-items: center; margin-bottom: .5rem;
      }
      .hb-card-cap  { font-size: .83rem; color: var(--gray-600, #4b5563); }
      .hb-card-price { font-weight: 800; font-size: 1rem; color: var(--success-color, #10b981); }
      .hb-card-price span { font-weight: 400; font-size: .72rem; color: var(--gray-400, #9ca3af); }
      .hb-amenity {
        display: inline-block; font-size: .65rem; padding: .15rem .45rem;
        border-radius: 8px; background: var(--gray-100, #f3f4f6);
        color: var(--gray-600, #4b5563);
        border: 1px solid var(--gray-200, #e5e7eb); margin: .1rem;
      }
      .hb-card-btn {
        margin-top: .875rem; width: 100%; padding: .65rem 1rem;
        background: var(--primary-color, #0066cc); color: #fff;
        border: none; border-radius: 10px;
        font-size: .88rem; font-weight: 600; cursor: pointer; transition: background .18s;
      }
      .hb-card-btn:hover { background: var(--primary-dark, #0052a3); }
      .hb-back {
        display: inline-flex; align-items: center; gap: .4rem;
        font-size: .85rem; color: var(--primary-color, #0066cc);
        cursor: pointer; margin-bottom: 1.25rem;
        background: none; border: none; padding: 0; font-weight: 600;
      }
      .hb-back:hover { text-decoration: underline; }
      .hb-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
      @media (max-width: 640px) { .hb-detail-grid { grid-template-columns: 1fr; } }
      .hb-main-img {
        width: 100%; height: 230px; object-fit: cover;
        border-radius: 14px; display: block; margin-bottom: .5rem;
      }
      .hb-thumb-row { display: flex; gap: .4rem; flex-wrap: wrap; margin-bottom: .875rem; }
      .hb-thumb-sm {
        width: 54px; height: 54px; object-fit: cover;
        border-radius: 8px; border: 2px solid var(--gray-200, #e5e7eb);
        cursor: pointer; transition: border-color .15s;
      }
      .hb-thumb-sm:hover { border-color: var(--primary-color, #0066cc); }
      .hb-detail-name { font-size: 1.2rem; font-weight: 800; color: var(--gray-900, #111827); margin-bottom: .3rem; }
      .hb-detail-addr { font-size: .82rem; color: var(--gray-500, #6b7280); margin-bottom: .75rem; }
      .hb-detail-desc { font-size: .85rem; color: var(--gray-600, #4b5563); line-height: 1.55; margin-bottom: .875rem; }
      .hb-form-card {
        background: var(--white, #fff);
        border: 2px solid var(--gray-200, #e5e7eb);
        border-radius: 16px; padding: 1.25rem;
      }
      .hb-form-title { font-size: .95rem; font-weight: 700; color: var(--gray-900, #111827); margin-bottom: 1rem; }
      .hb-label { display: block; font-size: .82rem; font-weight: 600; color: var(--gray-700, #374151); margin-bottom: .35rem; }
      .hb-input {
        width: 100%; padding: .65rem .875rem;
        border: 2px solid var(--gray-200, #e5e7eb);
        border-radius: 10px; font-size: .9rem;
        color: var(--gray-900, #111827); background: var(--white, #fff);
        outline: none; transition: border-color .15s; box-sizing: border-box;
      }
      .hb-input:focus { border-color: var(--primary-color, #0066cc); }
      .hb-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; margin-bottom: .875rem; }
      .hb-avail-box { padding: .65rem .875rem; border-radius: 10px; font-size: .83rem; font-weight: 500; margin-bottom: .875rem; }
      .hb-avail-ok   { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
      .hb-avail-warn { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
      .hb-avail-err  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
      .hb-price-box {
        background: var(--gray-50, #f9fafb);
        border: 1px solid var(--gray-200, #e5e7eb);
        border-radius: 10px; padding: .875rem 1rem; margin-bottom: .875rem;
      }
      .hb-price-row { display: flex; justify-content: space-between; font-size: .85rem; color: var(--gray-600, #4b5563); margin-bottom: .3rem; }
      .hb-price-total {
        display: flex; justify-content: space-between;
        font-size: 1.1rem; font-weight: 800; color: var(--gray-900, #111827);
        border-top: 1px solid var(--gray-200, #e5e7eb);
        padding-top: .5rem; margin-top: .3rem;
      }
      .hb-price-total .price { color: var(--success-color, #10b981); }
      .hb-btn-main {
        width: 100%; padding: .875rem 1rem;
        background: var(--primary-color, #0066cc); color: #fff;
        border: none; border-radius: 12px;
        font-size: 1rem; font-weight: 700; cursor: pointer; transition: background .18s;
      }
      .hb-btn-main:hover    { background: var(--primary-dark, #0052a3); }
      .hb-btn-main:disabled { background: var(--gray-300, #d1d5db); cursor: not-allowed; }
      .hb-spinner {
        display:inline-block; width:14px; height:14px;
        border:2px solid rgba(255,255,255,.3); border-top-color:#fff;
        border-radius:50%; animation:hb-spin .6s linear infinite;
      }
      @keyframes hb-spin { to { transform: rotate(360deg); } }
      .hb-recap {
        background: var(--gray-50, #f9fafb);
        border: 1px solid var(--gray-200, #e5e7eb);
        border-radius: 10px; padding: .875rem 1rem; font-size: .85rem; margin-bottom: .875rem;
      }
      .hb-recap-row {
        display: flex; justify-content: space-between;
        padding: .25rem 0; border-bottom: 1px solid var(--gray-100, #f3f4f6);
        color: var(--gray-700, #374151);
      }
      .hb-recap-row:last-child { border-bottom: none; }
      .hb-recap-row strong { color: var(--gray-900, #111827); }
      .hb-success { text-align: center; padding: 2.5rem 1rem; }
      .hb-success-ico { font-size: 3.5rem; margin-bottom: 1rem; }
      .hb-success h3 { font-size: 1.4rem; font-weight: 800; color: var(--gray-900, #111827); margin-bottom: .5rem; }
      .hb-code {
        display: inline-block;
        background: var(--gray-100, #f3f4f6);
        border: 2px dashed var(--gray-300, #d1d5db);
        border-radius: 10px; padding: .5rem 1.25rem;
        font-size: 1.2rem; font-weight: 800;
        letter-spacing: .1em; color: var(--primary-color, #0066cc); margin: .75rem 0;
      }
    `;
    document.head.appendChild(s);
  })();

  // ── État ──────────────────────────────────────────────────
  let state = {
    activityId:     null,
    accommodations: [],
    selected:       null,
    checkIn:        null,
    checkOut:       null,
    nights:         0,
    participants:   1,
    avail:          null,
  };

  let root       = null;
  let checkTimer = null;

  // ── Helpers ───────────────────────────────────────────────
  const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  const fmt = n  => Number(n).toLocaleString('fr-DZ');

  const typeLabel = { apartment:'Appartement', villa:'Villa', room:'Studio', chalet:'Chalet' };
  const typeBg    = { apartment:'#dbeafe', villa:'#d1fae5', room:'#f3f4f6', chalet:'#fef9c3' };
  const typeColor = { apartment:'#1d4ed8', villa:'#065f46', room:'#374151', chalet:'#854d0e' };


  function getClientId() {
    if (window.HB_CLIENT_ID && parseInt(window.HB_CLIENT_ID) > 0) {
      return parseInt(window.HB_CLIENT_ID);
    }
    return null;
  }

  // ════════════════════════════════════════════════════════
  // INIT
  // ════════════════════════════════════════════════════════
  async function init(container, activityId) {
    root             = container;
    state.activityId = activityId;
    root.innerHTML   = '<div style="text-align:center;padding:2rem;color:var(--gray-400)">Chargement des hébergements…</div>';

    const r    = await fetch('api/accommodations/list.php');
    const data = await r.json();

    if (!data.success || !data.accommodations.length) {
      root.innerHTML = `<div style="text-align:center;padding:2rem;color:var(--gray-500)">
        <div style="font-size:2rem;margin-bottom:.5rem">🏠</div>
        Aucun hébergement disponible pour le moment.
      </div>`;
      return;
    }
    state.accommodations = data.accommodations;
    showGallery();
  }

  // ════════════════════════════════════════════════════════
  // ÉTAPE 1 — Galerie
  // ════════════════════════════════════════════════════════
  function showGallery() {
    state.selected = null;
    root.innerHTML = `
      <div style="margin-bottom:1rem">
        <div style="font-size:1.05rem;font-weight:800;color:var(--gray-900)">Choisir un hébergement</div>
        <div style="font-size:.82rem;color:var(--gray-500)">${state.accommodations.length} logement(s) disponible(s)</div>
      </div>
      <div class="hb-grid">
        ${state.accommodations.map(accoCard).join('')}
      </div>
    `;
  }

  function accoCard(a) {
    const thumb  = a.thumbnail;
    const am     = (a.amenities || []).slice(0, 3);
    const tLabel = typeLabel[a.type]  || 'Hébergement';
    const tBg    = typeBg[a.type]     || '#f3f4f6';
    const tCol   = typeColor[a.type]  || '#374151';
    return `
      <div class="hb-card" onclick="HebergementBooking._select(${a.id})">
        ${thumb
          ? `<img class="hb-card-img" src="${esc(thumb)}" alt="${esc(a.name)}">`
          : `<div class="hb-card-img-ph">🏠</div>`}
        <div class="hb-card-body">
          <div class="hb-card-top">
            <div class="hb-card-name">${esc(a.name)}</div>
            <span class="hb-badge-type" style="background:${tBg};color:${tCol}">${tLabel}</span>
          </div>
          <div class="hb-card-addr">📍 ${esc(a.address || 'Béjaïa')}</div>
          <div class="hb-card-row">
            <div class="hb-card-cap">👥 ${a.capacity} pers. max</div>
            <div class="hb-card-price">${fmt(a.price_per_night)} DA<span>/nuit</span></div>
          </div>
          <div style="margin-bottom:.2rem">
            ${am.map(x=>`<span class="hb-amenity">${esc(x)}</span>`).join('')}
            ${(a.amenities||[]).length>3?`<span class="hb-amenity">+${a.amenities.length-3}</span>`:''}
          </div>
          <button class="hb-card-btn">Voir les disponibilités →</button>
        </div>
      </div>
    `;
  }

  // ════════════════════════════════════════════════════════
  // ÉTAPE 2 — Détail + sélection des dates
  // ════════════════════════════════════════════════════════
  function _select(id) {
    state.selected = state.accommodations.find(a => a.id === id);
    if (!state.selected) return;
    showDetail();
  }

  function showDetail() {
    const a      = state.selected;
    const images = a.images || [];
    const ams    = a.amenities || [];
    const today  = new Date().toISOString().slice(0, 10);
    const tom    = new Date(Date.now()+86400000).toISOString().slice(0,10);

    root.innerHTML = `
      <button class="hb-back" onclick="HebergementBooking._back()">← Retour aux hébergements</button>

      <div class="hb-detail-grid">
        <!-- Photos + infos -->
        <div>
          ${images.length
            ? `<img id="hbMain" class="hb-main-img" src="${esc(images[0])}" alt="${esc(a.name)}">`
            : `<div class="hb-main-img" style="background:var(--gray-100);display:flex;align-items:center;justify-content:center;font-size:3rem">🏠</div>`}
          ${images.length > 1 ? `
            <div class="hb-thumb-row">
              ${images.map(url=>`<img class="hb-thumb-sm" src="${esc(url)}" onclick="document.getElementById('hbMain').src='${esc(url)}'">`)
                     .join('')}
            </div>` : ''}
          <div class="hb-detail-name">${esc(a.name)}</div>
          <div class="hb-detail-addr">📍 ${esc(a.address || 'Béjaïa')}</div>
          <div style="display:flex;gap:1rem;margin-bottom:.875rem;font-size:.85rem;color:var(--gray-600)">
            <span>👥 ${a.capacity} personnes max</span>
            <span>🌙 Min. ${a.min_nights} nuit(s)</span>
          </div>
          ${a.description ? `<p class="hb-detail-desc">${esc(a.description)}</p>` : ''}
          ${ams.length ? `<div style="display:flex;flex-wrap:wrap;gap:.25rem">
            ${ams.map(x=>`<span class="hb-amenity">${esc(x)}</span>`).join('')}
          </div>` : ''}
        </div>

        <!-- Formulaire dates -->
        <div>
          <div class="hb-form-card">
            <div class="hb-form-title">📅 Vos dates de séjour</div>
            <div class="hb-row2">
              <div>
                <label class="hb-label">Arrivée</label>
                <input type="date" id="hbIn"  class="hb-input" min="${today}"
                       value="${state.checkIn||''}" onchange="HebergementBooking._dates()">
              </div>
              <div>
                <label class="hb-label">Départ</label>
                <input type="date" id="hbOut" class="hb-input" min="${tom}"
                       value="${state.checkOut||''}" onchange="HebergementBooking._dates()">
              </div>
            </div>
            <div style="margin-bottom:.875rem">
              <label class="hb-label">Personnes</label>
              <input type="number" id="hbPax" class="hb-input"
                     min="1" max="${a.capacity}" value="${state.participants}" style="width:120px">
            </div>
            <div id="hbAvail"></div>
            <div id="hbPrice" style="display:none" class="hb-price-box">
              <div class="hb-price-row">
                <span id="hbNights"></span><span id="hbPPN"></span>
              </div>
              <div class="hb-price-total">
                <span>Total</span>
                <span class="price" id="hbTotal"></span>
              </div>
            </div>
            <button id="hbContinue" class="hb-btn-main" style="display:none"
                    onclick="HebergementBooking._step3()">
              Continuer →
            </button>
          </div>
        </div>
      </div>
    `;
  }

  function _dates() {
    const ci = document.getElementById('hbIn')?.value;
    const co = document.getElementById('hbOut')?.value;
    if (ci && co && co <= ci) {
      const d = new Date(ci); d.setDate(d.getDate()+1);
      document.getElementById('hbOut').value = d.toISOString().slice(0,10);
    }
    state.checkIn  = document.getElementById('hbIn')?.value  || null;
    state.checkOut = document.getElementById('hbOut')?.value || null;
    clearTimeout(checkTimer);
    if (state.checkIn && state.checkOut)
      checkTimer = setTimeout(_checkAvail, 500);
  }

  async function _checkAvail() {
    const avDiv = document.getElementById('hbAvail');
    const prDiv = document.getElementById('hbPrice');
    const btn   = document.getElementById('hbContinue');
    if (!avDiv) return;
    avDiv.innerHTML = '<div style="font-size:.82rem;color:var(--gray-400)">⏳ Vérification…</div>';

    const r    = await fetch(`api/accommodations/check_dates.php?accommodation_id=${state.selected.id}&check_in=${state.checkIn}&check_out=${state.checkOut}`);
    const data = await r.json();
    state.avail = data;

    if (!data.success) {
      avDiv.innerHTML = `<div class="hb-avail-box hb-avail-err">⚠️ ${esc(data.error)}</div>`;
      prDiv && (prDiv.style.display='none');
      btn  && (btn.style.display='none');
      return;
    }
    if (!data.meets_min_nights) {
      avDiv.innerHTML = `<div class="hb-avail-box hb-avail-warn">ℹ️ ${esc(data.message)}</div>`;
      prDiv && (prDiv.style.display='none');
      btn  && (btn.style.display='none');
      return;
    }
    if (!data.is_available) {
      avDiv.innerHTML = `<div class="hb-avail-box hb-avail-err">❌ Indisponible pour ces dates.</div>`;
      prDiv && (prDiv.style.display='none');
      btn  && (btn.style.display='none');
      return;
    }

    state.nights = data.nights;
    avDiv.innerHTML = `<div class="hb-avail-box hb-avail-ok">✅ Disponible — ${data.nights} nuit(s)</div>`;
    document.getElementById('hbNights').textContent = `${data.nights} nuit(s) × ${fmt(data.accommodation.price_per_night)} DA`;
    document.getElementById('hbPPN').textContent    = '';
    document.getElementById('hbTotal').textContent  = `${fmt(data.total_price)} DA`;
    prDiv && (prDiv.style.display='block');
    btn  && (btn.style.display='block');
  }

  // ════════════════════════════════════════════════════════
  // ÉTAPE 3 — Formulaire contact
  // ════════════════════════════════════════════════════════
  function _step3() {
    state.participants = parseInt(document.getElementById('hbPax')?.value) || 1;
    const a = state.selected;

    root.innerHTML = `
      <button class="hb-back" onclick="HebergementBooking._backToDetail()">← Retour</button>

      <div style="max-width:520px;margin:0 auto">
        <div style="font-size:1.05rem;font-weight:800;color:var(--gray-900);margin-bottom:1rem">
          Vos informations
        </div>

        <div class="hb-recap">
          <div class="hb-recap-row"><span>Hébergement</span><strong>${esc(a.name)}</strong></div>
          <div class="hb-recap-row"><span>Arrivée</span><strong>${state.checkIn}</strong></div>
          <div class="hb-recap-row"><span>Départ</span><strong>${state.checkOut}</strong></div>
          <div class="hb-recap-row"><span>Durée</span><strong>${state.nights} nuit(s)</strong></div>
          <div class="hb-recap-row"><span>Personnes</span><strong>${state.participants}</strong></div>
          <div class="hb-recap-row" style="font-size:1rem;font-weight:800">
            <span>Total</span>
            <strong style="color:var(--success-color,#10b981)">${fmt(state.avail?.total_price||0)} DA</strong>
          </div>
        </div>

        <div style="display:grid;gap:.75rem;margin-bottom:1rem">
          <div>
            <label class="hb-label">Nom complet *</label>
            <input type="text"  id="hbFName"  class="hb-input" placeholder="Votre nom et prénom">
          </div>
          <div>
            <label class="hb-label">Téléphone (WhatsApp) *</label>
            <input type="tel" id="hbFPhone" class="hb-input" placeholder="+213551234567 *"
                   oninput="this.value=this.value.replace(/[^\d+]/g,'');if(!this.value.startsWith('+'))this.value='+'+this.value.replace(/\+/g,'')">
            <small style="display:block;color:var(--gray-500,#6b7280);font-size:.75rem;margin-top:.25rem">
              Format : +213551234567 (Algérie) · +33612345678 (France)
            </small>
          </div>
          <div>
            <label class="hb-label">Email *</label>
            <input type="email" id="hbFEmail" class="hb-input" placeholder="votre@email.com">
          </div>
          <div>
            <label class="hb-label">Demandes spéciales</label>
            <textarea id="hbFNotes" class="hb-input" rows="2"
                      placeholder="Arrivée tardive, étage préféré..."></textarea>
          </div>
        </div>

        <div id="hbFErr"></div>

        <button class="hb-btn-main" onclick="HebergementBooking._submit()">
          Confirmer la réservation ✓
        </button>
      </div>
    `;
  }

  // ════════════════════════════════════════════════════════
  // SOUMISSION — CORRECTION PRINCIPALE
  // ════════════════════════════════════════════════════════
  async function _submit() {
    const name   = document.getElementById('hbFName')?.value?.trim();
    const phone  = document.getElementById('hbFPhone')?.value?.trim();
    const email  = document.getElementById('hbFEmail')?.value?.trim();
    const notes  = document.getElementById('hbFNotes')?.value?.trim();
    const errDiv = document.getElementById('hbFErr');

    if (!name || !phone || !email) {
      errDiv.innerHTML = `<div class="hb-avail-box hb-avail-err">⚠️ Remplir tous les champs obligatoires (*)</div>`;
      return;
    }

   
    if (!phone.startsWith('+') || phone.length < 10 || phone.length > 16) {
      errDiv.innerHTML = `<div class="hb-avail-box hb-avail-err">⚠️ Format requis : +213XXXXXXXXX (avec le code pays)</div>`;
      document.getElementById('hbFPhone').style.borderColor = '#ef4444';
      return;
    }
    const afterPlus = phone.slice(1);
    if (!/^\d+$/.test(afterPlus)) {
      errDiv.innerHTML = `<div class="hb-avail-box hb-avail-err">⚠️ Uniquement des chiffres après le +</div>`;
      document.getElementById('hbFPhone').style.borderColor = '#ef4444';
      return;
    }
    document.getElementById('hbFPhone').style.borderColor = '#10b981';

    const btn = root.querySelector('.hb-btn-main');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="hb-spinner"></span> Confirmation…'; }

   
    const clientId = getClientId(); 

    const payload = {
      activity_id:      state.activityId,
      accommodation_id: state.selected.id,
      check_in_date:    state.checkIn,
      check_out_date:   state.checkOut,
      participants:     state.participants,
      client_name:      name,
      client_phone:     phone,
      client_email:     email,
      special_requests: notes || ''
    };

   
    if (clientId) {
      payload.client_id = clientId;
    }

    try {
      const r    = await fetch('api/make_reservation.php', {
        method:  'POST',
        headers: {'Content-Type': 'application/json'},
        body:    JSON.stringify(payload)
      });
      const data = await r.json();

      if (data.success) {
        root.innerHTML = `
          <div class="hb-success">
            <div class="hb-success-ico">🎉</div>
            <h3>Réservation confirmée !</h3>
            <p style="color:var(--gray-500);margin-bottom:.5rem">Votre code de confirmation</p>
            <div class="hb-code">${esc(data.confirmation_code)}</div>
            <p style="color:var(--gray-700);font-weight:600;margin:.5rem 0">${esc(state.selected.name)}</p>
            <p style="color:var(--gray-500);font-size:.9rem">
              Du <strong>${data.check_in}</strong> au <strong>${data.check_out}</strong>
              — ${data.nights} nuit(s)
            </p>
            <p style="font-size:1.15rem;font-weight:800;color:var(--success-color,#10b981);margin-top:.5rem">
              Total : ${fmt(data.total_price)} DA
            </p>
            <p style="font-size:.8rem;color:var(--gray-400);margin-top:.75rem">
              Une confirmation vous sera envoyée par email.
            </p>
            <button class="hb-back"
                    style="margin-top:1rem;justify-content:center;width:100%;
                           background:var(--gray-100);padding:.6rem;border-radius:10px"
                    onclick="HebergementBooking._back()">
              ← Voir d'autres hébergements
            </button>
          </div>
        `;
      } else {
        if (btn) { btn.disabled = false; btn.innerHTML = 'Confirmer la réservation ✓'; }
        errDiv.innerHTML = `<div class="hb-avail-box hb-avail-err">❌ ${esc(data.error || 'Erreur inconnue')}</div>`;
      }
    } catch (err) {
      if (btn) { btn.disabled = false; btn.innerHTML = 'Confirmer la réservation ✓'; }
      errDiv.innerHTML = `<div class="hb-avail-box hb-avail-err">❌ Erreur de connexion au serveur</div>`;
    }
  }

  // ── Navigation ───────────────────────────────────────────
  function _back()         { showGallery(); }
  function _backToDetail() { showDetail();  }

  // ── API publique ─────────────────────────────────────────
  return { init, _select, _back, _backToDetail, _dates, _step3, _submit };

})();