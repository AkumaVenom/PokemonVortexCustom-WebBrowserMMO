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
    lastDirection: 2,
  };

  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;

  function spriteMarkup(trainer, own) {
    const n = Math.max(1, Math.min(29, Number(trainer) || 1));
    // Recovered trainer 29 has the normal split sprite but no historical
    // owner-highlight (`o` / `otop`) pair. Use its real sprite instead of
    // issuing two broken image requests. Trainers 1-28 retain the recovered
    // highlighted owner artwork.
    const highlighted = own && n <= 28;
    const lower = `${cfg.spriteBase}${highlighted ? 'o' : ''}${n}.gif`;
    const upper = `${cfg.spriteBase}${highlighted ? 'otop' : 'top'}${n}.gif`;
    return `<img class="pv-map-sprite-top" src="${upper}" alt=""><img class="pv-map-sprite-bottom" src="${lower}" alt="">`;
  }

  function actorKey(player, own) {
    if (own) return 'self';
    const id = Math.max(0, Number(player.id) || 0);
    return id > 0 ? `player-${id}` : `guest-${String(player.username || 'trainer')}`;
  }

  function facingFromDelta(dx, dy, fallback = 2) {
    if (Math.abs(dx) >= Math.abs(dy) && dx !== 0) return dx > 0 ? 4 : 3;
    if (dy !== 0) return dy > 0 ? 2 : 1;
    return fallback;
  }

  function facingClass(direction) {
    if (direction === 1) return 'facing-up';
    if (direction === 3) return 'facing-left';
    if (direction === 4) return 'facing-right';
    return 'facing-down';
  }

  function createActor(player, own) {
    const isBot = !own && Boolean(player.bot);
    const element = document.createElement(isBot ? 'a' : 'span');
    element.className = `pv-map-actor${own ? ' pv-map-actor-own' : ''}${isBot ? ' pv-map-actor-bot' : ''} is-spawning`;
    element.dataset.playerKey = actorKey(player, own);
    element.dataset.playerId = own ? 'self' : String(Math.max(0, Number(player.id) || 0));
    element.dataset.trainer = String(own ? state.trainer : Math.max(1, Math.min(29, Number(player.trainer) || 1)));
    element.innerHTML = `${spriteMarkup(own ? state.trainer : player.trainer, own)}<b></b>`;
    if (isBot) element.href = `${String(cfg.botProfileBase || 'bot_trainer.php?id=')}${Math.max(0, Number(player.id) || 0)}`;
    window.requestAnimationFrame(() => element.classList.remove('is-spawning'));
    return element;
  }

  function updateActor(element, player, own, initial = false) {
    const x = Number(player.x) || 1;
    const y = Number(player.y) || 1;
    const previousX = Number(element.dataset.x) || x;
    const previousY = Number(element.dataset.y) || y;
    const changed = previousX !== x || previousY !== y;
    const direction = own ? state.lastDirection : facingFromDelta(x - previousX, y - previousY, Number(element.dataset.facing) || 2);
    const isBot = !own && Boolean(player.bot);
    const username = own ? 'You' : String(player.username || 'Trainer');
    const trainer = own ? state.trainer : Math.max(1, Math.min(29, Number(player.trainer) || 1));

    if (element.dataset.trainer !== String(trainer)) {
      const label = element.querySelector('b');
      element.innerHTML = `${spriteMarkup(trainer, own)}<b></b>`;
      element.dataset.trainer = String(trainer);
      if (label) label.remove();
    }

    const label = element.querySelector('b');
    if (label) {
      label.textContent = username;
      if (isBot) {
        const small = document.createElement('small');
        small.textContent = 'AI TRAINER';
        label.appendChild(small);
      }
    }

    element.title = isBot ? `${username} · AI trainer · interact` : username;
    element.dataset.x = String(x);
    element.dataset.y = String(y);
    element.dataset.facing = String(direction);
    element.classList.remove('facing-up', 'facing-down', 'facing-left', 'facing-right', 'is-leaving');
    element.classList.add(facingClass(direction));
    element.style.setProperty('--pv-actor-x', `${(x - 1) * 16}px`);
    element.style.setProperty('--pv-actor-y', `${(y - 2) * 16}px`);

    if (changed && !initial && !reducedMotion) {
      element.classList.remove('is-walking');
      // Restart the small footfall cycle while the transform glides to the
      // server-confirmed tile. The authoritative coordinate still comes only
      // from map_move.php / map_presence.php.
      void element.offsetWidth;
      element.classList.add('is-walking');
      const token = String((Number(element.dataset.walkToken) || 0) + 1);
      element.dataset.walkToken = token;
      window.setTimeout(() => {
        if (element.dataset.walkToken === token) element.classList.remove('is-walking');
      }, 460);
    }
  }

  function renderActors(initial = false) {
    const wanted = new Set();
    const renderOne = (player, own) => {
      const key = actorKey(player, own);
      wanted.add(key);
      let element = actors.querySelector(`[data-player-key="${CSS.escape(key)}"]`);
      const isNew = !element;
      if (!element) {
        element = createActor(player, own);
        actors.appendChild(element);
      }
      updateActor(element, player, own, initial || isNew);
    };

    renderOne({x: state.x, y: state.y}, true);
    state.players.forEach(player => {
      const x = Number(player.x), y = Number(player.y);
      if (x >= 1 && x <= 30 && y >= 1 && y <= 25) renderOne(player, false);
    });

    Array.from(actors.querySelectorAll('[data-player-key]')).forEach(element => {
      if (wanted.has(element.dataset.playerKey || '')) return;
      element.classList.add('is-leaving');
      window.setTimeout(() => {
        if (!wanted.has(element.dataset.playerKey || '') && element.classList.contains('is-leaving')) element.remove();
      }, reducedMotion ? 0 : 210);
    });

    coordinates.textContent = `X ${state.x} // Y ${state.y}`;
  }

  // Center only this scroll container, never the document. Vortex's native
  // 480x400 map must keep the trainer visible in a narrow phone viewport.
  const viewport = stage.closest('.pv-map-viewport-wrap');
  let cameraFrame = 0;
  function centerTrainer() {
    if (!viewport || cameraFrame) return;
    cameraFrame = window.requestAnimationFrame(() => {
      cameraFrame = 0;
      const view = viewport.getBoundingClientRect();
      const map = stage.getBoundingClientRect();
      const x = map.left - view.left + viewport.scrollLeft + (state.x - 1) * 16 + 8;
      const y = map.top - view.top + viewport.scrollTop + (state.y - 1) * 16;
      viewport.scrollLeft = Math.max(0, Math.min(viewport.scrollWidth - viewport.clientWidth, x - viewport.clientWidth / 2));
      viewport.scrollTop = Math.max(0, Math.min(viewport.scrollHeight - viewport.clientHeight, y - viewport.clientHeight / 2));
    });
  }
  window.addEventListener('resize', centerTrainer, { passive:true });
  window.addEventListener('pageshow', centerTrainer);
  if (viewport && typeof ResizeObserver === 'function') new ResizeObserver(centerTrainer).observe(viewport);

  function bindMapArtwork() {
    if (!mapArt) return;
    const loaded = () => {
      stage.classList.remove('is-map-error');
      stage.classList.add('is-map-ready');
      centerTrainer();
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

  function refreshEncounter(markup) {
    encounter.classList.remove('is-refreshing', 'has-encounter');
    encounter.innerHTML = markup || '<div class="pv-map-quiet"><strong>No wild Pokémon appeared.</strong><span>Keep exploring.</span></div>';
    encounter.classList.toggle('has-encounter', Boolean(encounter.querySelector('form')));
    if (!reducedMotion) {
      void encounter.offsetWidth;
      encounter.classList.add('is-refreshing');
    }
  }

  async function move(direction) {
    if (state.moving) return;
    state.lastDirection = Number(direction) || state.lastDirection;
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
        stage.classList.add('is-transitioning');
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
      renderActors(false);
      centerTrainer();
      applyBlockedDirections();
      refreshEncounter(data.encounter);
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
      renderActors(false);
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
  renderActors(true);
  centerTrainer();
  applyBlockedDirections();
  startPresenceSync();
})();
