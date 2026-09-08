(() => {
  'use strict';

  const cfg = window.PV_WORLD_MAP_CONFIG;
  if (!cfg) return;

  const stage = document.getElementById('pv-world-map-stage');
  const viewport = document.getElementById('pv-world-map-viewport');
  const actors = document.getElementById('pv-world-map-actors');
  const encounter = document.getElementById('pv-world-map-encounter');
  const status = document.getElementById('pv-world-map-status');
  const coordinates = document.getElementById('pv-world-coordinates');
  const mapImages = Array.from(document.querySelectorAll('#pv-world-map-stage [data-world-map-image]'));
  const buttons = Array.from(document.querySelectorAll('[data-world-direction]'));
  if (!stage || !viewport || !actors || !status || !coordinates) return;

  const state = {
    x: Number(cfg.x) || 1,
    y: Number(cfg.y) || 1,
    trainer: Number(cfg.trainer) || 1,
    tile: Math.max(8, Number(cfg.tileSize) || 16),
    logicalTile: Math.max(8, Number(cfg.logicalTileSize) || 16),
    players: Array.isArray(cfg.players) ? cfg.players : [],
    blocked: Array.isArray(cfg.blockedDirections) ? cfg.blockedDirections.map(Number) : [],
    moving: false,
    lastDirection: 2,
  };

  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;

  function sprite(trainer, own) {
    const n = Math.max(1, Math.min(29, Number(trainer) || 1));
    const highlighted = own && n <= 28;
    return `<img class="pv-map-sprite-top" src="${cfg.spriteBase}${highlighted ? 'otop' : 'top'}${n}.gif" alt=""><img class="pv-map-sprite-bottom" src="${cfg.spriteBase}${highlighted ? 'o' : ''}${n}.gif" alt="">`;
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
    element.innerHTML = `${sprite(own ? state.trainer : player.trainer, own)}<b></b>`;
    if (isBot) element.href = `${String(cfg.botProfileBase || 'bot_trainer.php?id=')}${Math.max(0, Number(player.id) || 0)}`;
    window.requestAnimationFrame(() => element.classList.remove('is-spawning'));
    return element;
  }

  function updateActor(element, player, own, initial) {
    const x = Number(player.x) || 1;
    const y = Number(player.y) || 1;
    const ratio = Math.max(1, state.tile / state.logicalTile);
    const unit = Math.round(16 * ratio);
    // New maps subdivide source metatiles for precise collision. A sprite can
    // span two movement cells: center its feet on the authoritative cell.
    const left = (x - 1) * state.tile + (state.tile - unit) / 2;
    const top = y * state.tile - unit * 2;
    const previousX = Number(element.dataset.x) || x;
    const previousY = Number(element.dataset.y) || y;
    const changed = previousX !== x || previousY !== y;
    const direction = own ? state.lastDirection : facingFromDelta(x - previousX, y - previousY, Number(element.dataset.facing) || 2);
    const isBot = !own && Boolean(player.bot);
    const name = own ? 'You' : String(player.username || 'Trainer');
    const trainer = own ? state.trainer : Math.max(1, Math.min(29, Number(player.trainer) || 1));

    if (element.dataset.trainer !== String(trainer)) {
      element.innerHTML = `${sprite(trainer, own)}<b></b>`;
      element.dataset.trainer = String(trainer);
    }

    const label = element.querySelector('b');
    if (label) {
      label.textContent = name;
      if (isBot) {
        const small = document.createElement('small');
        small.textContent = 'AI TRAINER';
        label.appendChild(small);
      }
    }

    element.title = isBot ? `${name} · AI trainer · interact` : name;
    element.dataset.x = String(x);
    element.dataset.y = String(y);
    element.dataset.facing = String(direction);
    element.classList.remove('facing-up', 'facing-down', 'facing-left', 'facing-right', 'is-leaving');
    element.classList.add(facingClass(direction));
    element.style.setProperty('--pv-world-actor-unit', `${unit}px`);
    element.style.setProperty('--pv-actor-x', `${left}px`);
    element.style.setProperty('--pv-actor-y', `${top}px`);

    if (changed && !initial && !reducedMotion) {
      element.classList.remove('is-walking');
      void element.offsetWidth;
      element.classList.add('is-walking');
      const token = String((Number(element.dataset.walkToken) || 0) + 1);
      element.dataset.walkToken = token;
      window.setTimeout(() => {
        if (element.dataset.walkToken === token) element.classList.remove('is-walking');
      }, 560);
    }
  }

  function render(initial = false) {
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

    renderOne({ x: state.x, y: state.y }, true);
    state.players.forEach(player => {
      const x = Number(player.x);
      const y = Number(player.y);
      if (x >= 1 && x <= Number(cfg.columns) && y >= 1 && y <= Number(cfg.rows)) renderOne(player, false);
    });

    Array.from(actors.querySelectorAll('[data-player-key]')).forEach(element => {
      if (wanted.has(element.dataset.playerKey || '')) return;
      element.classList.add('is-leaving');
      window.setTimeout(() => {
        if (!wanted.has(element.dataset.playerKey || '') && element.classList.contains('is-leaving')) element.remove();
      }, reducedMotion ? 0 : 220);
    });

    coordinates.textContent = `X ${state.x} // Y ${state.y}`;
  }

  function blocked() {
    buttons.forEach(button => {
      const direction = Number(button.dataset.worldDirection);
      const isBlocked = state.blocked.includes(direction);
      button.disabled = state.moving || isBlocked;
      button.classList.toggle('is-path-blocked', isBlocked);
      button.setAttribute('aria-disabled', isBlocked ? 'true' : 'false');
    });
  }

  function setBusy(value) {
    state.moving = value;
    blocked();
    stage.classList.toggle('is-moving', value);
  }

  function setStatus(message, tone = '') {
    status.textContent = message;
    status.dataset.tone = tone;
  }

  function refreshEncounter(markup) {
    if (!encounter) return;
    encounter.classList.remove('is-refreshing', 'has-encounter');
    if (markup) encounter.innerHTML = markup;
    encounter.classList.toggle('has-encounter', Boolean(encounter.querySelector('form')));
    if (!reducedMotion) {
      void encounter.offsetWidth;
      encounter.classList.add('is-refreshing');
    }
  }

  function clamp(value, minimum, maximum) {
    return Math.min(Math.max(value, minimum), Math.max(minimum, maximum));
  }

  /*
   * v22.3.1 camera contract
   * -----------------------
   * Chromium/Edge can restore or anchor an overflow element's scroll offset
   * after DOM/layout work.  Camera coordinates therefore use the stage's
   * actual layout origin inside the scrollport instead of assuming it begins
   * at scroll coordinate 0, and camera writes are deliberately instantaneous.
   * This keeps Firefox behaviour correct while making Edge deterministic.
   */
  function cameraTarget() {
    const viewportRect = viewport.getBoundingClientRect();
    const stageRect = stage.getBoundingClientRect();
    const stageLeft = stageRect.left - viewportRect.left + viewport.scrollLeft;
    const stageTop = stageRect.top - viewportRect.top + viewport.scrollTop;
    const playerX = stageLeft + (state.x - 0.5) * state.tile;
    const playerY = stageTop + (state.y - 0.5) * state.tile;
    const maxLeft = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
    const maxTop = Math.max(0, viewport.scrollHeight - viewport.clientHeight);

    return {
      left: clamp(Math.round(playerX - viewport.clientWidth / 2), 0, maxLeft),
      top: clamp(Math.round(playerY - viewport.clientHeight / 2), 0, maxTop),
    };
  }

  function center() {
    const target = cameraTarget();
    const previousInlineBehavior = viewport.style.scrollBehavior;

    /* CSS scroll-behavior:smooth is retained for the presentation layer, but
       camera correction must finish in the same frame on every browser. */
    viewport.style.scrollBehavior = 'auto';
    try {
      if (typeof viewport.scrollTo === 'function') {
        viewport.scrollTo({ left: target.left, top: target.top, behavior: 'instant' });
      } else {
        viewport.scrollLeft = target.left;
        viewport.scrollTop = target.top;
      }
    } catch (error) {
      /* Older engines that reject behavior:"instant" still receive an exact
         synchronous fallback while the inline scroll behavior is auto. */
      viewport.scrollLeft = target.left;
      viewport.scrollTop = target.top;
    } finally {
      viewport.style.scrollBehavior = previousInlineBehavior;
    }
  }

  let cameraFrame = 0;
  function scheduleCenter() {
    if (cameraFrame) window.cancelAnimationFrame(cameraFrame);
    cameraFrame = window.requestAnimationFrame(() => {
      cameraFrame = window.requestAnimationFrame(() => {
        cameraFrame = 0;
        center();
      });
    });
  }

  async function move(direction) {
    if (state.moving) return;
    state.lastDirection = Number(direction) || state.lastDirection;
    setBusy(true);
    setStatus('Validating movement…', 'busy');

    try {
      const body = new URLSearchParams();
      body.set('direction', String(direction));
      body.set('csrf_token', String(cfg.csrf || ''));
      body.set('world', String(cfg.world || ''));
      body.set('area', String(cfg.area || ''));

      const response = await fetch(cfg.moveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest',
        },
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
      if (data.error === 'area_changed') {
        setStatus('Your trainer entered another area. Reload this page to return here.', 'notice');
        return;
      }
      if (!response.ok || !data.ok) throw new Error(data.error || `HTTP ${response.status}`);
      if (data.redirect) {
        setStatus('Entering connected area…', 'success');
        stage.classList.add('is-transitioning');
        window.location.href = data.redirect;
        return;
      }
      if (!data.moved) {
        if (Array.isArray(data.blockedDirections)) state.blocked = data.blockedDirections.map(Number);
        blocked();
        setStatus('That path is blocked. Choose another direction.', 'blocked');
        stage.classList.add('is-blocked');
        window.setTimeout(() => stage.classList.remove('is-blocked'), 180);
        return;
      }

      state.x = Number(data.x) || state.x;
      state.y = Number(data.y) || state.y;
      state.players = Array.isArray(data.players) ? data.players : state.players;
      if (Array.isArray(data.blockedDirections)) state.blocked = data.blockedDirections.map(Number);
      render(false);
      blocked();
      center();
      scheduleCenter();
      refreshEncounter(data.encounter);
      setStatus('Movement confirmed.', 'success');
    } catch (error) {
      console.error('World movement failed', error);
      setStatus('The exploration link did not respond. Try again.', 'error');
    } finally {
      setBusy(false);
    }
  }

  const keys = new Map([
    ['ArrowUp', 1], ['w', 1], ['W', 1], ['8', 1],
    ['ArrowDown', 2], ['s', 2], ['S', 2], ['2', 2],
    ['ArrowLeft', 3], ['a', 3], ['A', 3], ['4', 3],
    ['ArrowRight', 4], ['d', 4], ['D', 4], ['6', 4],
    ['7', 5], ['1', 6], ['9', 7], ['3', 8],
  ]);

  let timer = 0;
  let active = false;
  async function presence() {
    if (active || state.moving || document.visibilityState !== 'visible') return;
    active = true;
    try {
      const response = await fetch(String(cfg.presenceUrl || ''), {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (response.status === 401) return;
      const data = await response.json();
      if (!response.ok || !data.ok || String(data.world) !== String(cfg.world) || String(data.area) !== String(cfg.area)) return;
      state.players = Array.isArray(data.players) ? data.players : [];
      render(false);
    } catch (error) {
      console.debug('World presence refresh skipped', error);
    } finally {
      active = false;
    }
  }

  buttons.forEach(button => button.addEventListener('click', () => move(Number(button.dataset.worldDirection))));
  document.addEventListener('keydown', event => {
    const target = event.target;
    if (target && /^(INPUT|TEXTAREA|SELECT|BUTTON)$/.test(target.tagName)) return;
    const direction = keys.get(event.key);
    if (!direction) return;
    event.preventDefault();
    move(direction);
  });

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      presence();
      scheduleCenter();
    }
  });
  window.addEventListener('resize', scheduleCenter, { passive: true });
  window.addEventListener('load', scheduleCenter, { once: true });
  window.addEventListener('pageshow', scheduleCenter);

  if (typeof ResizeObserver === 'function') {
    const cameraResizeObserver = new ResizeObserver(scheduleCenter);
    cameraResizeObserver.observe(viewport);
  }

  if (document.fonts && document.fonts.ready && typeof document.fonts.ready.then === 'function') {
    document.fonts.ready.then(scheduleCenter).catch(() => {});
  }

  if (mapImages.length) {
    let artworkErrorReported = false;
    const reportArtworkError = () => {
      if (artworkErrorReported) return;
      artworkErrorReported = true;
      stage.classList.add('is-map-error');
      setStatus(`This area image could not be rendered. Return to the ${String(cfg.worldLabel || 'world')} area list.`, 'error');
    };

    mapImages.forEach(image => {
      image.addEventListener('load', () => {
        stage.classList.add('is-map-ready');
        scheduleCenter();
      });
      image.addEventListener('error', reportArtworkError, { once: true });

      if (image.complete) {
        if (image.naturalWidth > 0 && image.naturalHeight > 0) {
          stage.classList.add('is-map-ready');
          scheduleCenter();
        } else reportArtworkError();
        return;
      }

      if (typeof image.decode === 'function') {
        image.decode().then(() => {
          stage.classList.add('is-map-ready');
          scheduleCenter();
        }).catch(() => {
          /* A decode promise may reject while an ordinary image load remains
             valid. The image error event is the authority for hard failure. */
        });
      }
    });
  }

  render(true);
  blocked();
  center();
  scheduleCenter();
  timer = window.setInterval(presence, Math.max(1200, Math.min(10000, Number(cfg.presenceInterval) || 2000)));
})();
