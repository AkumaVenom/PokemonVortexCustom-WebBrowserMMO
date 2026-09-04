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
  };

  const esc = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
  }[ch]));

  function sprite(trainer, own) {
    const n = Math.max(1, Math.min(29, Number(trainer) || 1));
    const highlighted = own && n <= 28;
    return `<img class="pv-map-sprite-top" src="${cfg.spriteBase}${highlighted ? 'otop' : 'top'}${n}.gif" alt=""><img class="pv-map-sprite-bottom" src="${cfg.spriteBase}${highlighted ? 'o' : ''}${n}.gif" alt="">`;
  }

  function actor(player, own = false) {
    const x = Number(player.x) || 1;
    const y = Number(player.y) || 1;
    const ratio = Math.max(1, state.tile / state.logicalTile);
    const unit = Math.round(16 * ratio);
    const left = (x - 1) * state.tile + Math.max(0, (state.tile - unit) / 2);
    const top = (y - 1) * state.tile - unit;
    const name = own ? 'You' : esc(player.username || 'Trainer');
    const isBot = !own && Boolean(player.bot);
    const cls = own ? ' pv-map-actor-own' : (isBot ? ' pv-map-actor-bot' : '');
    const content = `${sprite(own ? state.trainer : player.trainer, own)}<b>${name}${isBot ? '<small>AI TRAINER</small>' : ''}</b>`;
    if (isBot) {
      const id = Math.max(0, Number(player.id) || 0);
      return `<a class="pv-map-actor${cls}" style="left:${left}px;top:${top}px;--pv-world-actor-unit:${unit}px" title="${name} · AI trainer · interact" href="${esc(String(cfg.botProfileBase || 'bot_trainer.php?id='))}${id}">${content}</a>`;
    }
    return `<span class="pv-map-actor${cls}" style="left:${left}px;top:${top}px;--pv-world-actor-unit:${unit}px" title="${name}">${content}</span>`;
  }

  function render() {
    let html = actor({ x: state.x, y: state.y }, true);
    state.players.forEach(player => {
      const x = Number(player.x);
      const y = Number(player.y);
      if (x >= 1 && x <= Number(cfg.columns) && y >= 1 && y <= Number(cfg.rows)) {
        html += actor(player, false);
      }
    });
    actors.innerHTML = html;
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
    setBusy(true);
    setStatus('Validating movement…', 'busy');

    try {
      const body = new URLSearchParams();
      body.set('direction', String(direction));
      body.set('csrf_token', String(cfg.csrf || ''));

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
      if (!response.ok || !data.ok) throw new Error(data.error || `HTTP ${response.status}`);
      if (data.redirect) {
        setStatus('Entering connected area…', 'success');
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
      render();
      blocked();
      center();
      scheduleCenter();
      if (encounter && data.encounter) encounter.innerHTML = data.encounter;
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
      render();
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
      image.addEventListener('load', scheduleCenter);
      image.addEventListener('error', reportArtworkError, { once: true });

      if (image.complete) {
        if (image.naturalWidth > 0 && image.naturalHeight > 0) scheduleCenter();
        else reportArtworkError();
        return;
      }

      if (typeof image.decode === 'function') {
        image.decode().then(scheduleCenter).catch(() => {
          /* A decode promise may reject while an ordinary image load remains
             valid. The image error event is the authority for hard failure. */
        });
      }
    });
  }

  render();
  blocked();
  center();
  scheduleCenter();
  timer = window.setInterval(presence, Math.max(1200, Math.min(10000, Number(cfg.presenceInterval) || 2000)));
})();
