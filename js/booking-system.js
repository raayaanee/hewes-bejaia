
class BookingSystem {
    constructor() {
        this.activity     = null;
        this.date         = null;
        this.slot         = null;
        this.participants = 1;
        this.currentMonth = new Date();
        this._lastSlots   = [];   // ← stockage des slots pour _selectSlot()
        // Auth — rempli via setAuth()
        this.isLoggedIn   = false;
        this.clientId     = null;
        this.clientName   = '';
        this.clientEmail  = '';
        this.clientPhone  = '';

        this._injectStyles();
        this._createModal();
    }

    // ── Auth (appelé depuis index.php via PHP) ───────────────
    setAuth(isLoggedIn, clientId, clientName, clientEmail, clientPhone) {
        this.isLoggedIn  = isLoggedIn;
        this.clientId    = clientId;
        this.clientName  = clientName  || '';
        this.clientEmail = clientEmail || '';
        this.clientPhone = clientPhone || '';
    }

    // ════════════════════════════════════════════════════════
    // OUVRIR LE MODAL
    // ════════════════════════════════════════════════════════
    openModal(activity) {
        if (!this.isLoggedIn) {
            sessionStorage.setItem('pending_booking', JSON.stringify(activity));
            openAuthModal();
            return;
        }
        this.activity     = activity;
        this.date         = null;
        this.slot         = null;
        this.participants = 1;
        this.currentMonth = new Date();
        this._lastSlots   = [];

        document.getElementById('bsModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        this._goToStep(1);
        this._updateActivityHeader();
    }

    closeModal() {
        document.getElementById('bsModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    // ════════════════════════════════════════════════════════
    // ÉTAPES
    // ════════════════════════════════════════════════════════
    _goToStep(step) {
        this._currentStep = step;
        document.querySelectorAll('.bs-step').forEach(s => s.style.display = 'none');
        document.getElementById('bs-step-' + step).style.display = 'block';
        const pct = ((step - 1) / 3) * 100;
        document.getElementById('bsProgressFill').style.width = pct + '%';
        document.querySelectorAll('.bs-step-dot').forEach((d, i) => {
            d.classList.toggle('active',    i + 1 === step);
            d.classList.toggle('completed', i + 1 < step);
        });
    }

    // ── Étape 1 : Calendrier ─────────────────────────────────
    _renderCalendar() {
        const y = this.currentMonth.getFullYear();
        const m = this.currentMonth.getMonth();
        const monthNames = ['Janvier','Février','Mars','Avril','Mai','Juin',
                            'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        document.getElementById('bsCalMonth').textContent = monthNames[m] + ' ' + y;

        const firstDow = new Date(y, m, 1).getDay();
        const daysInM  = new Date(y, m + 1, 0).getDate();
        const today    = new Date(); today.setHours(0,0,0,0);

        let html = '';
        ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'].forEach(d =>
            html += `<div class="bs-cal-lbl">${d}</div>`);
        for (let i = 0; i < firstDow; i++)
            html += '<div class="bs-cal-day disabled"></div>';
        for (let d = 1; d <= daysInM; d++) {
            const dt  = new Date(y, m, d);
            const ds  = this._fmt(dt);
            const past = dt < today;
            const sel  = ds === this.date;
            html += `<div class="bs-cal-day${past?' disabled':''}${sel?' selected':''}"
                         ${past?'':` onclick="bookingSystem._selectDate('${ds}')"`}>${d}</div>`;
        }
        document.getElementById('bsCalGrid').innerHTML = html;
    }

    changeMonth(dir) {
        this.currentMonth.setMonth(this.currentMonth.getMonth() + dir);
        this._renderCalendar();
    }

    async _selectDate(ds) {
        this.date = ds;
        this.slot = null;
        this._lastSlots = [];
        this._renderCalendar();
        const slotArea = document.getElementById('bsSlotArea');
        slotArea.innerHTML = `<div class="bs-loading"><i class="fas fa-spinner fa-spin"></i> Chargement des créneaux...</div>`;

        try {
            // ── Même API que booking.php ──────────────────────────────────────
            const r    = await fetch(`api/get_availability_for_date.php?activity_id=${this.activity.id}&date=${ds}`);
            const data = await r.json();
            this._renderSlots(data);
        } catch(e) {
            slotArea.innerHTML = `<div class="bs-error"><i class="fas fa-exclamation-triangle"></i> Erreur de chargement</div>`;
        }
    }

    /**
     * Normalise un slot issu de get_availability_for_date.php vers le format
     * attendu par le reste du système :
     *   - id              → availability_id
     *   - time_slot_id
     *   - start_time / end_time  (ex: "09:00:00")
     *   - max_participants
     *   - available_spots = max_participants - current_reservations
     *   - price           = price_override ?? activity.price
     *   - label           = "09:00 – 11:00"
     */
    _normalizeSlot(raw) {
        const available = (parseInt(raw.max_participants) || 0) - (parseInt(raw.current_reservations) || 0);
        const price     = parseFloat(raw.price_override ?? raw.price ?? this.activity?.price ?? 0);
        const st        = raw.start_time || '00:00:00';
        const et        = raw.end_time   || '00:00:00';
        return {
            availability_id:  raw.id,
            time_slot_id:     raw.time_slot_id,
            start_time:       st,
            end_time:         et,
            label:            st.slice(0,5) + ' – ' + et.slice(0,5),
            max_participants: parseInt(raw.max_participants) || 0,
            available_spots:  Math.max(0, available),
            price:            price
        };
    }

    _renderSlots(data) {
        const area = document.getElementById('bsSlotArea');

        // get_availability_for_date.php retourne { success, slots: [...] }
        if (!data.success || !data.slots || !data.slots.length) {
            area.innerHTML = `<div class="bs-no-slot"><i class="fas fa-calendar-times"></i><p>Aucun créneau disponible pour cette date</p></div>`;
            return;
        }

        // Normaliser tous les slots et stocker dans _lastSlots
        this._lastSlots = data.slots.map(s => this._normalizeSlot(s));

        area.innerHTML = `<h4 class="bs-slot-title">Créneaux disponibles</h4>
            <div class="bs-slots-grid">${this._lastSlots.map((s,i) => this._slotCard(s, i)).join('')}</div>`;
    }

    _slotCard(slot, i) {
        const filled = slot.max_participants > 0
            ? Math.round((slot.max_participants - slot.available_spots) / slot.max_participants * 100)
            : 0;
        const col  = filled >= 100 ? '#ef4444' : filled >= 70 ? '#f59e0b' : '#10b981';
        const full = slot.available_spots <= 0;
        return `
            <div class="bs-slot${full?' bs-slot-full':''}" ${full?'':'onclick="bookingSystem._selectSlot(this, ' + i + ')"'} data-idx="${i}">
                <div class="bs-slot-time">
                    <i class="fas fa-clock"></i>
                    ${slot.label}
                </div>
                <div class="bs-slot-avail">
                    <div class="bs-avail-bar"><div class="bs-avail-fill" style="width:${filled}%;background:${col}"></div></div>
                    <span style="color:#6b7280;font-size:.85rem;white-space:nowrap">${slot.available_spots}/${slot.max_participants}</span>
                </div>
                <div class="bs-slot-price">${slot.price ? slot.price.toLocaleString('fr-DZ') + ' DA' : ''}</div>
                ${full ? '<p class="bs-slot-full-label">Complet</p>' : ''}
            </div>`;
    }

    _selectSlot(el, idx) {
        document.querySelectorAll('.bs-slot').forEach(s => s.classList.remove('selected'));
        el.classList.add('selected');

        // ── CORRECTION 1 : lire depuis _lastSlots (qui contient start_time, end_time, etc.) ──
        if (this._lastSlots && this._lastSlots[idx]) {
            this.slot = this._lastSlots[idx];
        } else {
            // Fallback minimal (ne devrait plus arriver)
            const timeText = el.querySelector('.bs-slot-time').textContent.trim().replace(/\s+/g,' ');
            const price    = parseFloat(el.querySelector('.bs-slot-price').textContent) || this.activity.price;
            this.slot = { label: timeText, price, start_time: null, end_time: null };
        }

        setTimeout(() => this._goToStep(3), 250);
        this._updateParticipantsStep();
    }

    // ── Étape 3 : Participants ───────────────────────────────
    _updateParticipantsStep() {
        if (!this.activity) return;
        const isPrivate  = this.activity.booking_type === 'private';
        const maxPerRes  = parseInt(this.activity.max_participants) || 10;
        const available  = this.slot?.available_spots ?? maxPerRes;
        const max        = isPrivate ? maxPerRes : Math.min(available, maxPerRes);

        this.participants = 1;
        document.getElementById('bsPaxCount').textContent = 1;
        document.getElementById('bsPaxMax').textContent   = max;

        const banner = document.getElementById('bsPaxBanner');
        if (isPrivate) {
            banner.style.cssText = 'background:#fee2e2;border-left:4px solid #ef4444;padding:1rem;border-radius:.5rem;margin-bottom:1rem;font-size:.9rem';
            banner.innerHTML     = `<i class="fas fa-lock" style="color:#ef4444;margin-right:.4rem"></i>
                <strong>Réservation Privative</strong> — vous réservez l'activité complète pour votre groupe.<br>
                <small>Capacité max : ${maxPerRes} personnes</small>`;
        } else {
            banner.style.cssText = 'background:#fef3c7;border-left:4px solid #f59e0b;padding:1rem;border-radius:.5rem;margin-bottom:1rem;font-size:.9rem';
            banner.innerHTML     = `<i class="fas fa-users" style="color:#f59e0b;margin-right:.4rem"></i>
                <strong>Activité Partagée</strong> — ${available} place(s) disponible(s)`;
        }

        this._paxMax    = max;
        this._isPrivate = isPrivate;
    }

    changePax(dir) {
        const n = this.participants + dir;
        if (n >= 1 && n <= (this._paxMax || 10)) {
            this.participants = n;
            document.getElementById('bsPaxCount').textContent = n;
        }
    }

    // ── Étape 4 : Confirmation ───────────────────────────────
    _showConfirmation() {
        const act   = this.activity;
        const price = this.slot?.price ?? act.price;
        const total = this._isPrivate ? price : price * this.participants;
        const dateF = this._fmtFR(this.date);
        const time  = this.slot?.label || '—';

        document.getElementById('bsSummary').innerHTML = `
            <div class="bs-summary-row"><span>Activité</span><strong>${act.name}</strong></div>
            <div class="bs-summary-row"><span>Type</span><strong>
                <i class="fas fa-${this._isPrivate ? 'lock' : 'users'}" style="margin-right:.3rem"></i>
                ${this._isPrivate ? 'Privatif' : 'Partagé'}</strong></div>
            <div class="bs-summary-row"><span>Date</span><strong>${dateF}</strong></div>
            <div class="bs-summary-row"><span>Créneau</span><strong>${time}</strong></div>
            <div class="bs-summary-row"><span>Participants</span><strong>${this.participants}</strong></div>
            <div class="bs-summary-row bs-summary-total">
                <span>TOTAL</span>
                <strong style="color:#10b981;font-size:1.2rem">${total.toLocaleString('fr-DZ')} DA</strong>
            </div>`;

        document.getElementById('bsName').value  = this.clientName;
        document.getElementById('bsEmail').value = this.clientEmail;
        if (this.clientPhone) {
            const p = this.clientPhone.startsWith('+') ? this.clientPhone : '+' + this.clientPhone;
            document.getElementById('bsPhone').value = p;
        }
    }

    // ── Soumission ───────────────────────────────────────────
    async _submit() {
        const name  = document.getElementById('bsName').value.trim();
        const email = document.getElementById('bsEmail').value.trim();
        const phone = document.getElementById('bsPhone').value.trim();
        const notes = document.getElementById('bsNotes').value.trim();
        const errEl = document.getElementById('bsFormErr');
        errEl.textContent = '';

        if (!name)  { errEl.textContent = 'Le nom est requis'; return; }
        if (!email) { errEl.textContent = "L'email est requis"; return; }
        if (!phone.startsWith('+') || phone.length < 10 || phone.length > 16) {
            errEl.textContent = 'Format requis : +213XXXXXXXXX (avec le code pays)';
            document.getElementById('bsPhone').style.borderColor = '#ef4444';
            return;
        }
        const afterPlus = phone.slice(1);
        if (!/^\d+$/.test(afterPlus)) {
            errEl.textContent = 'Uniquement des chiffres après le +';
            return;
        }
        document.getElementById('bsPhone').style.borderColor = '#10b981';

        const btn = document.getElementById('bsSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Confirmation...';

        const act   = this.activity;
        const price = this.slot?.price ?? act.price;
        const total = this._isPrivate ? price : price * this.participants;

        // ── CORRECTION 2 : start_time et end_time viennent de this.slot (désormais peuplé) ──
        const payload = {
            activity_id:       act.id,
            availability_id:   this.slot?.availability_id  ?? null,
            time_slot_id:      this.slot?.time_slot_id      ?? null,
            start_time:        this.slot?.start_time        ?? null,
            end_time:          this.slot?.end_time          ?? null,
            date:              this.date,
            participants:      this.participants,
            client_name:       name,
            client_email:      email,
            client_phone:      phone,
            client_id:         this.clientId,
            special_requests:  notes,
            booking_type:      act.booking_type
        };

        try {
            const r    = await fetch('api/make_reservation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await r.json();

            if (data.success) {
                document.getElementById('bs-step-4').innerHTML = `
                    <div style="text-align:center;padding:2rem 1rem">
                        <div style="font-size:3.5rem;margin-bottom:1rem">🎉</div>
                        <h3 style="font-size:1.4rem;font-weight:800;color:#111827;margin-bottom:.5rem">Réservation confirmée !</h3>
                        <p style="color:#6b7280">Code de confirmation :</p>
                        <div style="display:inline-block;background:#f3f4f6;border:2px dashed #d1d5db;
                                    border-radius:10px;padding:.5rem 1.5rem;font-size:1.3rem;
                                    font-weight:800;letter-spacing:.1em;color:#0066cc;margin:.75rem 0">
                            ${data.confirmation_code}
                        </div>
                        <p style="color:#374151;font-weight:600;margin:.5rem 0">${act.name}</p>
                        <p style="color:#6b7280;font-size:.9rem">Le ${this._fmtFR(this.date)} — ${this.slot?.label || ''}</p>
                        <p style="font-size:1.1rem;font-weight:800;color:#10b981;margin:.75rem 0">
                            Total : ${total.toLocaleString('fr-DZ')} DA
                        </p>
                        <p style="font-size:.8rem;color:#9ca3af;margin-bottom:1.5rem">
                            Confirmation WhatsApp envoyée sur ${phone}
                        </p>
                        <button onclick="bookingSystem.closeModal()"
                                style="background:#0066cc;color:#fff;border:none;padding:.75rem 2rem;
                                       border-radius:10px;font-weight:700;cursor:pointer;font-size:1rem">
                            Fermer
                        </button>
                    </div>`;

                if (data.whatsapp_url) {
                    setTimeout(() => window.open(data.whatsapp_url, '_blank'), 500);
                }
            } else {
                errEl.textContent = data.error || 'Erreur lors de la réservation';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirmer la réservation';
            }
        } catch(e) {
            errEl.textContent = 'Erreur de connexion au serveur';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirmer la réservation';
        }
    }

    // ── Reload pending after login ───────────────────────────
    checkPendingBooking() {
        if (!this.isLoggedIn) return;
        const stored = sessionStorage.getItem('pending_booking');
        if (stored) {
            sessionStorage.removeItem('pending_booking');
            try {
                const activity = JSON.parse(stored);
                setTimeout(() => this.openModal(activity), 400);
            } catch(e) {}
        }
    }

    // ════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════
    _updateActivityHeader() {
        const a = this.activity;
        document.getElementById('bsActImg').src           = a.image_url || '';
        document.getElementById('bsActName').textContent  = a.name;
        const dur = a.duration_minutes
            ? Math.floor(a.duration_minutes/60) + 'h' + (a.duration_minutes%60 > 0 ? a.duration_minutes%60+'min' : '')
            : '';
        document.getElementById('bsActMeta').textContent =
            [dur, a.max_participants ? 'Max ' + a.max_participants + ' pers.' : ''].filter(Boolean).join(' · ');
        document.getElementById('bsActPrice').textContent =
            a.price > 0
                ? a.price.toLocaleString('fr-DZ') + ' DA / ' + (a.booking_type === 'private' ? 'groupe' : 'pers.')
                : 'Sur demande';
    }

    _fmt(dt) {
        return dt.getFullYear() + '-'
            + String(dt.getMonth()+1).padStart(2,'0') + '-'
            + String(dt.getDate()).padStart(2,'0');
    }

    _fmtFR(ds) {
        if (!ds) return '—';
        const [y, m, d] = ds.split('-').map(Number);
        return new Date(y, m-1, d).toLocaleDateString('fr-FR', {
            weekday:'long', year:'numeric', month:'long', day:'numeric'
        });
    }

    // ════════════════════════════════════════════════════════
    // CRÉATION DU MODAL HTML
    // ════════════════════════════════════════════════════════
    _createModal() {
        const el = document.createElement('div');
        el.id = 'bsModal';
        el.style.cssText = `display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,.7);z-index:99999;align-items:flex-start;
            justify-content:center;overflow-y:auto;padding:20px;box-sizing:border-box`;
        el.addEventListener('click', e => { if (e.target === el) this.closeModal(); });

        el.innerHTML = `
        <div class="bs-wrap">
            <!-- Header activité -->
            <div class="bs-act-header">
                <img id="bsActImg" src="" alt="" class="bs-act-img">
                <div class="bs-act-info">
                    <div id="bsActName" class="bs-act-name"></div>
                    <div id="bsActMeta" class="bs-act-meta"></div>
                    <div id="bsActPrice" class="bs-act-price"></div>
                </div>
                <button onclick="bookingSystem.closeModal()" class="bs-close">✕</button>
            </div>

            <!-- Progress -->
            <div class="bs-progress">
                <div class="bs-progress-bar"><div id="bsProgressFill" class="bs-progress-fill" style="width:0%"></div></div>
                <div class="bs-dots">
                    ${['Date','Créneau','Participants','Confirmation'].map((l,i)=>`
                    <div class="bs-step-dot${i===0?' active':''}">
                        <div class="bs-dot-circle">${i+1}</div>
                        <div class="bs-dot-label">${l}</div>
                    </div>`).join('')}
                </div>
            </div>

            <!-- ── ÉTAPE 1 : Calendrier ── -->
            <div class="bs-step" id="bs-step-1">
                <h3 class="bs-step-title">Choisissez une date</h3>
                <div class="bs-cal-nav">
                    <button class="bs-cal-btn" onclick="bookingSystem.changeMonth(-1)">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span id="bsCalMonth" class="bs-cal-month"></span>
                    <button class="bs-cal-btn" onclick="bookingSystem.changeMonth(1)">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div id="bsCalGrid" class="bs-cal-grid"></div>
                <div id="bsSlotArea" style="margin-top:1.5rem"></div>
            </div>

            <!-- ── ÉTAPE 3 : Participants ── -->
            <div class="bs-step" id="bs-step-3" style="display:none">
                <h3 class="bs-step-title">Nombre de participants</h3>
                <div id="bsPaxBanner"></div>
                <div class="bs-pax-box">
                    <button class="bs-pax-btn" onclick="bookingSystem.changePax(-1)">
                        <i class="fas fa-minus"></i>
                    </button>
                    <div id="bsPaxCount" class="bs-pax-num">1</div>
                    <button class="bs-pax-btn" onclick="bookingSystem.changePax(1)">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <p style="text-align:center;color:#6b7280;font-size:.9rem">
                    Maximum <span id="bsPaxMax">10</span> personnes
                </p>
                <div class="bs-actions">
                    <button class="bs-btn-back" onclick="bookingSystem._goToStep(1)">
                        <i class="fas fa-arrow-left"></i> Retour
                    </button>
                    <button class="bs-btn-next" onclick="bookingSystem._goToStep(4);bookingSystem._showConfirmation()">
                        Continuer <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- ── ÉTAPE 4 : Confirmation ── -->
            <div class="bs-step" id="bs-step-4" style="display:none">
                <h3 class="bs-step-title">Confirmer la réservation</h3>
                <div id="bsSummary" class="bs-summary"></div>

                <!-- Hint WhatsApp -->
                <div class="bs-wa-hint">
                    <i class="fab fa-whatsapp" style="font-size:1.3rem"></i>
                    <div>
                        <strong>Confirmation WhatsApp</strong><br>
                        <small>Entrez votre numéro avec le code pays : +213XXXXXXXXX</small>
                    </div>
                </div>

                <div class="bs-form">
                    <div class="bs-fg">
                        <i class="fas fa-user"></i>
                        <input type="text" id="bsName" placeholder="Nom complet *" required>
                    </div>
                    <div class="bs-fg">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="bsEmail" placeholder="Email *" required>
                    </div>
                    <div class="bs-fg">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="bsPhone" placeholder="+213551234567 *"
                               oninput="this.value=this.value.replace(/[^\\d+]/g,'');
                                        if(!this.value.startsWith('+'))this.value='+'+this.value.replace(/\\+/g,'')">
                    </div>
                    <small style="display:block;color:#6b7280;font-size:.78rem;margin:-1rem 0 1rem;padding-left:.2rem">
                        Format : +213551234567 (Algérie), +33612345678 (France)...
                    </small>
                    <div class="bs-fg" style="--icon-top:1rem">
                        <i class="fas fa-comment" style="top:1.1rem"></i>
                        <textarea id="bsNotes" placeholder="Demandes spéciales (optionnel)" rows="3"></textarea>
                    </div>
                </div>

                <div id="bsFormErr" style="color:#ef4444;font-size:.85rem;margin-bottom:.5rem;font-weight:500"></div>

                <div class="bs-actions">
                    <button class="bs-btn-back" onclick="bookingSystem._goToStep(3)">
                        <i class="fas fa-arrow-left"></i> Retour
                    </button>
                    <button id="bsSubmitBtn" class="bs-btn-next" onclick="bookingSystem._submit()">
                        <i class="fas fa-check-circle"></i> Confirmer la réservation
                    </button>
                </div>
            </div>
        </div>`;

        document.body.appendChild(el);

        setTimeout(() => this._renderCalendar(), 100);
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') this.closeModal();
        });
    }

    // ════════════════════════════════════════════════════════
    // STYLES
    // ════════════════════════════════════════════════════════
    _injectStyles() {
        if (document.getElementById('bs-styles')) return;
        const s = document.createElement('style');
        s.id = 'bs-styles';
        s.textContent = `
        .bs-wrap {
            background:#fff; border-radius:20px; max-width:720px; width:100%;
            margin:40px auto; box-shadow:0 20px 60px rgba(0,0,0,.3); overflow:hidden;
        }
        .bs-act-header {
            background:linear-gradient(135deg,#0066cc,#0052a3);
            display:flex; align-items:center; gap:1rem; padding:1.25rem 1.5rem;
            position:relative;
        }
        .bs-act-img {
            width:64px; height:64px; border-radius:12px; object-fit:cover;
            border:2px solid rgba(255,255,255,.3);
        }
        .bs-act-name  { font-weight:800; font-size:1.1rem; color:#fff; }
        .bs-act-meta  { font-size:.8rem; color:rgba(255,255,255,.75); margin:.2rem 0; }
        .bs-act-price { font-size:.9rem; color:#93c5fd; font-weight:700; }
        .bs-close {
            position:absolute; top:12px; right:14px;
            background:rgba(255,255,255,.15); border:none; color:#fff;
            width:32px; height:32px; border-radius:50%; cursor:pointer;
            font-size:1rem; display:flex; align-items:center; justify-content:center;
            transition:.15s;
        }
        .bs-close:hover { background:rgba(255,255,255,.3); }
        .bs-progress { padding:1.25rem 1.5rem; border-bottom:1px solid #f3f4f6; }
        .bs-progress-bar { background:#e5e7eb; border-radius:99px; height:4px; margin-bottom:.875rem; }
        .bs-progress-fill { background:linear-gradient(90deg,#0066cc,#0052a3); height:4px; border-radius:99px; transition:.4s; }
        .bs-dots { display:flex; justify-content:space-between; }
        .bs-step-dot { text-align:center; flex:1; }
        .bs-dot-circle {
            width:34px; height:34px; border-radius:50%; background:#e5e7eb;
            color:#9ca3af; font-weight:700; font-size:.9rem;
            display:flex; align-items:center; justify-content:center;
            margin:0 auto .3rem; transition:.3s;
        }
        .bs-step-dot.active    .bs-dot-circle { background:#0066cc; color:#fff; box-shadow:0 0 0 4px rgba(0,102,204,.15); }
        .bs-step-dot.completed .bs-dot-circle { background:#10b981; color:#fff; }
        .bs-dot-label { font-size:.72rem; color:#9ca3af; font-weight:600; }
        .bs-step-dot.active    .bs-dot-label { color:#0066cc; }
        .bs-step-dot.completed .bs-dot-label { color:#10b981; }
        .bs-step { padding:1.5rem; }
        .bs-step-title { font-size:1.2rem; font-weight:800; color:#111827; margin-bottom:1.25rem; }
        .bs-cal-nav { display:flex; justify-content:space-between; align-items:center; margin-bottom:.875rem; }
        .bs-cal-btn {
            background:none; border:2px solid #0066cc; color:#0066cc;
            width:36px; height:36px; border-radius:8px; cursor:pointer;
            display:flex; align-items:center; justify-content:center; transition:.2s;
        }
        .bs-cal-btn:hover { background:#0066cc; color:#fff; }
        .bs-cal-month { font-size:1.1rem; font-weight:700; color:#111827; }
        .bs-cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; }
        .bs-cal-lbl { text-align:center; font-size:.72rem; font-weight:700; color:#9ca3af; padding:.3rem 0; }
        .bs-cal-day {
            aspect-ratio:1; display:flex; align-items:center; justify-content:center;
            border-radius:8px; font-size:.88rem; font-weight:600; cursor:pointer;
            border:1px solid #e5e7eb; color:#374151; transition:.15s;
        }
        .bs-cal-day:hover:not(.disabled):not(.selected) { border-color:#0066cc; color:#0066cc; background:#eff6ff; }
        .bs-cal-day.selected  { background:#0066cc; color:#fff; border-color:#0066cc; }
        .bs-cal-day.disabled  { opacity:.3; cursor:not-allowed; background:#f9fafb; }
        .bs-slot-title { font-size:.9rem; font-weight:700; color:#374151; margin-bottom:.75rem; }
        .bs-slots-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:.75rem; }
        .bs-slot {
            border:2px solid #e5e7eb; border-radius:12px; padding:.875rem 1rem;
            cursor:pointer; transition:.2s;
        }
        .bs-slot:hover:not(.bs-slot-full) { border-color:#0066cc; background:#eff6ff; }
        .bs-slot.selected      { border-color:#0066cc; background:#eff6ff; }
        .bs-slot.bs-slot-full  { opacity:.5; cursor:not-allowed; }
        .bs-slot-time   { font-weight:700; font-size:.95rem; color:#0066cc; margin-bottom:.5rem;
                          display:flex; align-items:center; gap:.4rem; }
        .bs-slot-avail  { display:flex; align-items:center; gap:.5rem; margin-bottom:.3rem; }
        .bs-avail-bar   { flex:1; height:5px; background:#e5e7eb; border-radius:99px; overflow:hidden; }
        .bs-avail-fill  { height:5px; border-radius:99px; transition:.3s; }
        .bs-slot-price  { font-size:.82rem; font-weight:700; color:#10b981; }
        .bs-slot-full-label { font-size:.8rem; color:#ef4444; margin-top:.25rem; }
        .bs-loading { text-align:center; padding:2rem; color:#9ca3af; font-size:.9rem; }
        .bs-no-slot { text-align:center; padding:2rem; color:#9ca3af; }
        .bs-no-slot i { font-size:2rem; margin-bottom:.5rem; display:block; opacity:.4; }
        .bs-error   { text-align:center; padding:1rem; color:#ef4444; font-size:.9rem; }
        .bs-pax-box { display:flex; align-items:center; justify-content:center; gap:2rem; margin:1.5rem 0; }
        .bs-pax-btn {
            width:52px; height:52px; border-radius:12px; background:#0066cc;
            color:#fff; border:none; font-size:1.2rem; cursor:pointer; transition:.2s;
        }
        .bs-pax-btn:hover { background:#0052a3; transform:scale(1.05); }
        .bs-pax-num { font-size:2.5rem; font-weight:800; color:#0066cc; min-width:80px; text-align:center; }
        .bs-summary {
            background:#f9fafb; border-radius:12px; padding:1rem 1.25rem;
            margin-bottom:1.25rem;
        }
        .bs-summary-row {
            display:flex; justify-content:space-between; align-items:center;
            padding:.4rem 0; border-bottom:1px solid #f3f4f6; font-size:.9rem; color:#4b5563;
        }
        .bs-summary-row:last-child { border-bottom:none; border-top:2px solid #e5e7eb; padding-top:.75rem; margin-top:.25rem; }
        .bs-summary-total { font-size:1rem; font-weight:700; color:#111827; }
        .bs-wa-hint {
            background:linear-gradient(135deg,#25d366,#128c7e);
            color:#fff; padding:.875rem 1rem; border-radius:10px;
            display:flex; align-items:center; gap:.875rem; margin-bottom:1.25rem;
            font-size:.85rem;
        }
        .bs-form { display:grid; gap:.875rem; }
        .bs-fg { position:relative; }
        .bs-fg i { position:absolute; left:.875rem; top:50%; transform:translateY(-50%); color:#9ca3af; pointer-events:none; }
        .bs-fg input, .bs-fg textarea {
            width:100%; padding:.875rem .875rem .875rem 2.75rem;
            border:2px solid #e5e7eb; border-radius:10px; font-size:.95rem;
            color:#111827; background:#fff; outline:none; transition:.2s; box-sizing:border-box;
            font-family:inherit;
        }
        .bs-fg input:focus, .bs-fg textarea:focus { border-color:#0066cc; box-shadow:0 0 0 3px rgba(0,102,204,.1); }
        .bs-fg textarea { padding-top:.875rem; resize:vertical; }
        .bs-actions { display:flex; gap:.875rem; justify-content:flex-end; margin-top:1.5rem; }
        .bs-btn-back {
            padding:.75rem 1.5rem; border-radius:10px; border:none;
            background:#f3f4f6; color:#374151; font-weight:600; cursor:pointer; font-size:.95rem; transition:.2s;
        }
        .bs-btn-back:hover { background:#e5e7eb; }
        .bs-btn-next {
            padding:.75rem 1.75rem; border-radius:10px; border:none;
            background:linear-gradient(135deg,#0066cc,#0052a3); color:#fff;
            font-weight:700; cursor:pointer; font-size:.95rem; transition:.2s;
            display:flex; align-items:center; gap:.5rem;
        }
        .bs-btn-next:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,102,204,.35); }
        .bs-btn-next:disabled { opacity:.5; cursor:not-allowed; transform:none; box-shadow:none; }
        @media(max-width:600px) {
            .bs-wrap { margin:10px; border-radius:14px; }
            .bs-slots-grid { grid-template-columns:1fr; }
            .bs-actions { flex-direction:column; }
            .bs-btn-back, .bs-btn-next { width:100%; justify-content:center; }
        }`;
        document.head.appendChild(s);
    }
}

// ── Initialiser ───────────────────────────────────────────────
const bookingSystem = new BookingSystem();

function openBookingModal(activity) {
    bookingSystem.openModal(activity);
}