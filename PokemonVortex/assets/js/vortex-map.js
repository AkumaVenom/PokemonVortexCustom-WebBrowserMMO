(() => {
  'use strict';
  const cfg = window.PV_MAP_CONFIG;
  if (!cfg) return;

  const stage = document.getElementById('pv-map-stage');
  const actors = document.getElementById('pv-map-actors');
  const encounter = document.getElementById('pv-map-encounter');
  const status = document.getElementById('pv-map-status');
  const coordinates = document.getElementById('pv-map-coordinates');
  const mapArt = document.getElementById('pv-map-art');
  const buttons = Array.from(document.querySelectorAll('[data-map-direction]'));
  if (!stage || !actors || !encounter || !status || !coordinates) return;

  const state = {
    x: Number(cfg.x) || 1,
    y: Number(cfg.y) || 1,
    trainer: Number(cfg.trainer) || 1,
    players: Array.isArray(cfg.players) ? cfg.players : [],
    blockedDirections: Array.isArray(cfg.blockedDirections) ? cfg.blockedDirections.map(Number) : [],
    moving: false,
  };

  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));

  function spriteMarkup(trainer, own) {
    const n = Math.max(1, Math.min(29, Number(trainer) || 1));
    // Recovered trainer 29 has the normal split sprite but no historical
    // owner-highlight (`o` / `otop`) pair.  Use its real sprite instead of
    // issuing two broken image requests. Trainers 1-28 retain the recovered
    // highlighted owner artwork.
    const highlighted = own && n <= 28;
    const lower = `${cfg.spriteBase}${highlighted ? 'o' : ''}${n}.gif`;
    const upper = `${cfg.spriteBase}${highlighted ? 'otop' : 'top'}${n}.gif`;
    return `<img class="pv-map-sprite-top" src="${upper}" alt=""><img class="pv-map-sprite-bottom" src="${lower}" alt="">`;
  }

  function actorMarkup(player, own = false) {
    const x = Number(player.x) || 1;
    const y = Number(player.y) || 1;
    const left = (x - 1) * 16;
    const top = (y - 2) * 16;
    const username = own ? 'You' : esc(player.username || 'Trainer');
    const isBot = !own && Boolean(player.bot);
    const cls = own ? ' pv-map-actor-own' : (isBot ? ' pv-map-actor-bot' : '');
    const content = `${spriteMarkup(own ? state.trainer : player.trainer, own)}<b>${username}${isBot ? '<small>AI TRAINER</small>' : ''}</b>`;
    if (isBot) {
      const id = Math.max(0, Number(player.id) || 0);
      return `<a class="pv-map-actor${cls}" style="left:${left}px;top:${top}px" title="${username} · AI trainer · interact" data-player-id="${id}" href="${esc(String(cfg.botProfileBase || 'bot_trainer.php?id='))}${id}">${content}</a>`;
    }
    return `<span class="pv-map-actor${cls}" style="left:${left}px;top:${top}px" title="${username}" data-player-id="${own ? 'self' : Number(player.id) || 0}">${content}</span>`;
  }


  function bindMapArtwork() {
    if (!mapArt) return;
    const loaded = () => {
      stage.classList.remove('is-map-error');
      if (!state.moving) setStatus('Ready. Use WASD, arrow keys, numpad or the movement pad.', 'success');
    };
    const failed = () => {
      stage.classList.add('is-map-error');
      setStatus('This region could not be rendered. Return to the map list and try again.', 'error');
    };
    mapArt.addEventListener('load', loaded, {once:true});
    mapArt.addEventListener('error', failed, {once:true});
    if (mapArt.complete) {
      if (mapArt.naturalWidth > 0) loaded(); else failed();
    }
  }

  function renderActors() {
    let html = actorMarkup({x: state.x, y: state.y}, true);
    state.players.forEach(player => {
      const x = Number(player.x), y = Number(player.y);
      if (x >= 1 && x <= 30 && y >= 1 && y <= 25) html += actorMarkup(player, false);
    });
    actors.innerHTML = html;
    coordinates.textContent = `X ${state.x} // Y ${state.y}`;
  }

  function applyBlockedDirections() {
    buttons.forEach(btn => {
      const direction = Number(btn.dataset.mapDirection);
      const blocked = state.blockedDirections.includes(direction);
      btn.disabled = state.moving || blocked;
      btn.classList.toggle('is-path-blocked', blocked);
      btn.setAttribute('aria-disabled', blocked ? 'true' : 'false');
      btn.title = blocked ? 'Path blocked' : 'Move';
    });
  }

  function setBusy(value) {
    state.moving = value;
    buttons.forEach(btn => btn.setAttribute('aria-busy', value ? 'true' : 'false'));
    applyBlockedDirections();
    stage.classList.toggle('is-moving', value);
  }

  function setStatus(message, tone = '') {
    status.textContent = message;
    status.dataset.tone = tone;
  }

  async function move(direction) {
    if (state.moving) return;
    setBusy(true);
    setStatus('Validating movement and scanning the area…', 'busy');

    try {
      const body = new URLSearchParams();
      body.set('direction', String(direction));
      body.set('csrf_token', String(cfg.csrf || ''));
      const response = await fetch(cfg.moveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'X-Requested-With':'XMLHttpRequest'},
        body: body.toString(),
      });

      if (response.status === 401) {
        window.location.href = `${cfg.base}/login.php?expired=1`;
        return;
      }
      if (response.status === 429) {
        setStatus('Movement input received too quickly. Ready.', 'notice');
        return;
      }
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || `HTTP ${response.status}`);
      if (data.redirect) {
        setStatus('Entering connected region…', 'success');
        window.location.href = data.redirect;
        return;
      }
      if (!data.moved) {
        if (Array.isArray(data.blockedDirections)) state.blockedDirections = data.blockedDirections.map(Number);
        applyBlockedDirections();
        setStatus('That path is blocked. Choose another direction.', 'blocked');
        stage.classList.add('is-blocked');
        window.setTimeout(() => stage.classList.remove('is-blocked'), 180);
        return;
      }

      state.x = Number(data.x) || state.x;
      state.y = Number(data.y) || state.y;
      state.players = Array.isArray(data.players) ? data.players : state.players;
      if (Array.isArray(data.blockedDirections)) state.blockedDirections = data.blockedDirections.map(Number);
      renderActors();
      applyBlockedDirections();
      encounter.innerHTML = data.encounter || '<div class="pv-map-quiet"><strong>No wild Pokémon appeared.</strong><span>Keep exploring.</span></div>';
      setStatus('Movement confirmed. Area scan complete.', 'success');

      const battleButton = encounter.querySelector('input[type="submit"], button[type="submit"]');
      if (battleButton) battleButton.focus({preventScroll:true});
    } catch (error) {
      console.error('Map movement failed', error);
      setStatus('The exploration link did not respond. Try the move again.', 'error');
    } finally {
      setBusy(false);
    }
  }

  const keyDirections = new Map([
    ['ArrowUp',1], ['w',1], ['W',1], ['8',1],
    ['ArrowDown',2], ['s',2], ['S',2], ['2',2],
    ['ArrowLeft',3], ['a',3], ['A',3], ['4',3],
    ['ArrowRight',4], ['d',4], ['D',4], ['6',4],
    ['7',5], ['1',6], ['9',7], ['3',8],
  ]);

  let presenceTimer = 0;
  let presenceRequestActive = false;

  async function refreshPresence() {
    if (presenceRequestActive || state.moving || document.visibilityState !== 'visible') return;
    presenceRequestActive = true;
    try {
      const response = await fetch(String(cfg.presenceUrl || ''), {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {'X-Requested-With':'XMLHttpRequest'},
      });
      if (response.status === 401) return;
      const data = await response.json();
      if (!response.ok || !data.ok || Number(data.map) !== Number(cfg.map)) return;
      state.players = Array.isArray(data.players) ? data.players : [];
      renderActors();
    } catch (error) {
      // Presence refresh is supplemental; movement remains fully usable if a
      // transient request fails. Avoid noisy player-facing errors.
      console.debug('Map presence refresh skipped', error);
    } finally {
      presenceRequestActive = false;
    }
  }

  function startPresenceSync() {
    const interval = Math.max(1200, Math.min(10000, Number(cfg.presenceInterval) || 2000));
    if (presenceTimer) window.clearInterval(presenceTimer);
    presenceTimer = window.setInterval(refreshPresence, interval);
  }

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') refreshPresence();
  });

  buttons.forEach(btn => btn.addEventListener('click', () => move(Number(btn.dataset.mapDirection))));
  document.addEventListener('keydown', event => {
    const target = event.target;
    if (target && /^(INPUT|TEXTAREA|SELECT|BUTTON)$/.test(target.tagName)) return;
    const direction = keyDirections.get(event.key);
    if (!direction) return;
    event.preventDefault();
    move(direction);
  });

  bindMapArtwork();
  renderActors();
  applyBlockedDirections();
  startPresenceSync();
})();
