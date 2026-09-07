(() => {
  'use strict';
  document.documentElement.classList.add('pv-js');

  const topbar = document.querySelector('.pv-topbar');
  const navToggle = document.querySelector('[data-pv-nav-toggle]');
  const nav = document.getElementById('pv-primary-nav');

  const closeNav = () => {
    if (!topbar || !navToggle) return;
    topbar.classList.remove('pv-nav-open');
    navToggle.setAttribute('aria-expanded', 'false');
  };

  if (topbar && navToggle && nav) {
    navToggle.addEventListener('click', () => {
      const open = !topbar.classList.contains('pv-nav-open');
      topbar.classList.toggle('pv-nav-open', open);
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    nav.addEventListener('click', (event) => {
      if (event.target.closest('a')) closeNav();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') closeNav();
    });
    window.addEventListener('resize', () => {
      if (window.innerWidth > 900) closeNav();
    }, { passive: true });
  }

  // Make legacy and modern server messages accessible to assistive technology.
  document.querySelectorAll('.actionMsg,.successMsg,.noticeMsg,.errorMsg,.pv-system-error,.pv-flash').forEach((el) => {
    const urgent = el.classList.contains('errorMsg') || el.classList.contains('error');
    el.setAttribute('role', urgent ? 'alert' : 'status');
    el.setAttribute('aria-live', urgent ? 'assertive' : 'polite');
  });

  // Loading feedback without changing legacy form POST semantics.
  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
      const btn = form.querySelector('button[type="submit"],input[type="submit"]');
      if (!btn || btn.dataset.pvSubmitting === '1') return;
      btn.dataset.pvSubmitting = '1';
      btn.setAttribute('aria-busy', 'true');
    });
  });

  // Live trainer appearance preview on account creation.
  const trainerSelect = document.querySelector('[data-trainer-select]');
  const trainerPreview = document.querySelector('[data-trainer-preview]');
  if (trainerSelect && trainerPreview) {
    const syncTrainer = () => {
      const base = trainerSelect.dataset.spriteBase || '';
      trainerPreview.src = `${base}${trainerSelect.value}.gif`;
      trainerPreview.alt = `Trainer appearance ${trainerSelect.value}`;
    };
    trainerSelect.addEventListener('change', syncTrainer);
    syncTrainer();
  }

  // Large collection/Pokédex screens contain thousands of small sprites.
  // Lazy-load them and replace missing Pokémon art with the bundled Poké Ball.
  document.querySelectorAll('img').forEach((img) => {
    if (!img.hasAttribute('loading')) img.loading = 'lazy';
    if (!img.hasAttribute('decoding')) img.decoding = 'async';
    img.addEventListener('error', () => {
      if (img.dataset.pvFallback === '1') return;
      const src = img.getAttribute('src') || '';
      if (!/\/images\/pokemon\//i.test(src)) return;
      const fallback = src.replace(/\/images\/pokemon\/.*$/i, '/images/Pokeball.PNG');
      if (fallback === src) return;
      img.dataset.pvFallback = '1';
      img.classList.add('pv-image-fallback');
      img.src = fallback;
      if (!img.alt) img.alt = 'Pokémon sprite unavailable';
    });
  });

  // External targets opened by old pages must not retain opener access.
  document.querySelectorAll('a[target="_BLANK"],a[target="_blank"]').forEach((a) => {
    a.rel = 'noopener noreferrer';
  });

  document.documentElement.classList.add('pv-ready');
})();

/* v24.0.0 — coherent 2026 motion, interaction and battle-feedback layer. */
(() => {
  'use strict';

  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
  document.documentElement.classList.add(reducedMotion ? 'pv-reduced-motion' : 'pv-motion-enabled');

  const revealSelector = [
    '.pv-page-head', '.pv-hero', '.pv-section-heading', '.pv-world-status',
    '.pv-map-console', '.pv-map-sidebar > section', '.pv-arena-summary-grid > div',
    '.pv-trade-overview > a', '.pv-network-summary > *', '.pv-dashboard-grid > *',
    '.pv-public-card', '.pv-auth-card', '.pv-empty-state'
  ].join(',');
  const revealTargets = Array.from(document.querySelectorAll(revealSelector));
  revealTargets.forEach((element, index) => {
    element.classList.add('pv-motion-reveal');
    element.style.setProperty('--pv-reveal-index', String(Math.min(index % 12, 11)));
  });

  if (!reducedMotion && 'IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -6% 0px', threshold: 0.06 });
    revealTargets.forEach(element => revealObserver.observe(element));
  } else {
    revealTargets.forEach(element => element.classList.add('is-visible'));
  }

  const interactiveSelector = [
    '.pv-map-card', '.pv-world-card', '.pv-trade-card', '.pv-collection-card',
    '.pv-dex-card', '.pv-shop-card', '.pv-trainer-card', '.pv-pokemon-select-card',
    '.pv-arena-opponent', '.pv-live-trainer-list article', '.pv-live-offer-list article',
    '.pv-card', '.pv-side-menu a'
  ].join(',');
  document.querySelectorAll(interactiveSelector).forEach(element => element.classList.add('pv-interactive-surface'));

  const celebrate = (root, tone = 'victory') => {
    if (!root || reducedMotion || root.dataset.pvCelebrated === '1') return;
    root.dataset.pvCelebrated = '1';
    const layer = document.createElement('span');
    layer.className = `pv-celebration pv-celebration-${tone}`;
    layer.setAttribute('aria-hidden', 'true');
    for (let i = 0; i < 22; i += 1) {
      const particle = document.createElement('i');
      const lane = ((i * 37) % 100);
      const drift = (((i * 53) % 70) - 35);
      const delay = ((i * 47) % 500);
      const duration = 1450 + ((i * 83) % 900);
      particle.style.setProperty('--pv-particle-x', `${lane}%`);
      particle.style.setProperty('--pv-particle-drift', `${drift}px`);
      particle.style.setProperty('--pv-particle-delay', `${delay}ms`);
      particle.style.setProperty('--pv-particle-duration', `${duration}ms`);
      layer.appendChild(particle);
    }
    root.appendChild(layer);
    window.setTimeout(() => layer.remove(), 3200);
  };

  window.PVMotion = Object.freeze({ reducedMotion, celebrate });
  document.querySelectorAll('.pv-wild-result-won').forEach(root => celebrate(root, 'victory'));
  // v24.1 capture celebration is fired after the staged Ball choreography completes.

  document.addEventListener('submit', event => {
    const form = event.target instanceof HTMLFormElement ? event.target : null;
    if (!form || !form.closest('.pv-wildbattle-page,.pv-combat-modern-surface')) return;
    const button = form.querySelector('button[type="submit"],input[type="submit"]');
    if (button instanceof HTMLElement) button.classList.add('is-commanding');
    document.body.classList.add('pv-battle-command-sent');
  }, true);
})();

/* v11 — secure trade interactions */
(() => {
  'use strict';

  document.addEventListener('submit', (event) => {
    const form = event.target instanceof HTMLFormElement ? event.target : null;
    if (!form) return;
    const message = form.getAttribute('data-pv-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
      event.stopImmediatePropagation();
      form.querySelectorAll('[aria-busy="true"]').forEach((el) => {
        el.removeAttribute('aria-busy');
        delete el.dataset.pvSubmitting;
      });
      return;
    }

    if (form.hasAttribute('data-pv-offer-form')) {
      const checked = form.querySelectorAll('[data-pv-offer-item]:checked');
      if (checked.length < 1 || checked.length > 6) {
        event.preventDefault();
        event.stopImmediatePropagation();
        const counter = form.querySelector('[data-pv-offer-count]');
        if (counter) counter.closest('small')?.classList.add('pv-limit-warning');
      }
    }
  }, true);

  document.querySelectorAll('[data-pv-offer-form]').forEach((form) => {
    const items = [...form.querySelectorAll('[data-pv-offer-item]')];
    const counter = form.querySelector('[data-pv-offer-count]');
    const sync = (changed) => {
      let checked = items.filter((input) => input.checked);
      if (checked.length > 6 && changed) {
        changed.checked = false;
        checked = items.filter((input) => input.checked);
      }
      if (counter) {
        counter.textContent = String(checked.length);
        counter.closest('small')?.classList.toggle('pv-limit-warning', checked.length === 0 || checked.length > 6);
      }
      items.forEach((input) => {
        input.closest('.pv-pokemon-select-card')?.classList.toggle('pv-offer-disabled', !input.checked && checked.length >= 6);
      });
    };
    items.forEach((input) => input.addEventListener('change', () => sync(input)));
    sync(null);
  });
})();

/* v22.7.2 — modern shell + Wild-Battle-style presentation for NPC/Live PvP. */
(() => {
  'use strict';
  const body = document.body;
  if (!body || (!body.classList.contains('pv-legacy-battle-page') && !body.classList.contains('pv-legacy-live-battle-page'))) return;

  const container = document.getElementById('container');
  const contentContainer = document.getElementById('contentContainer');
  const content = document.getElementById('content');
  const ajax = document.getElementById('ajax');
  if (!container || !contentContainer || !content || !ajax) return;

  const userLink = document.querySelector('#usernav a');
  const trainer = (userLink?.textContent || 'Trainer').trim() || 'Trainer';
  const initial = Array.from(trainer)[0]?.toUpperCase() || 'T';
  const isLive = body.classList.contains('pv-legacy-live-battle-page');

  const make = (tag, cls, html = '') => {
    const el = document.createElement(tag);
    if (cls) el.className = cls;
    if (html) el.innerHTML = html;
    return el;
  };
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const normalize = (value) => String(value ?? '').replace(/\s+/g, ' ').trim();

  const header = make('header', 'pv-topbar pv-combat-topbar');
  header.innerHTML = `
    <a class="pv-brand" href="dashboard.php"><span class="pv-brand-mark">PV</span><span class="pv-brand-copy">POKÉMON VORTEX<small>ONLINE BATTLE RPG</small></span></a>
    <button class="pv-nav-toggle" type="button" aria-expanded="false" aria-controls="pv-combat-primary-nav" data-pv-nav-toggle><span></span><span></span><span></span><b>Menu</b></button>
    <nav class="pv-nav" id="pv-combat-primary-nav" aria-label="Primary">
      <a href="dashboard.php">Dashboard</a><a href="map_select.php">Explore</a><a class="active" href="battle_select.php">Battle</a><a href="your_pokemon.php">Pokémon</a><a href="trade.php">Trade</a><a href="community.php">Community</a>
      <a class="pv-trainer-chip" href="your_account.php"><span>${esc(initial)}</span><em></em></a><a class="danger" href="logout.php">Log Out</a>
    </nav>`;
  const chip = header.querySelector('.pv-trainer-chip em');
  if (chip) chip.textContent = trainer;

  const strip = make('div', 'pv-command-strip pv-combat-command-strip', `
    <span class="pv-command-state"><i></i>TRAINER SESSION ACTIVE</span><span>BATTLE // ${isLive ? 'LIVE PVP' : 'NPC NETWORK'}</span><span>MODE // SERVER AUTHORITATIVE</span><span class="pv-command-tail">SELECT · RESOLVE · PERSIST</span>`);

  const side = make('aside', 'pv-side-menu pv-combat-side-menu');
  const profile = make('div', 'pv-side-profile');
  profile.innerHTML = '<span class="pv-side-signal"><i></i>ONLINE</span><strong></strong><small>TRAINER NETWORK</small>';
  profile.querySelector('strong').textContent = trainer;
  side.appendChild(profile);
  const groups = [
    ['Adventure', [['dashboard.php','Overview'],['map_select.php','World Maps'],['battle_select.php','Battle Arena'],['sidequest.php','Sidequests']]],
    ['Collection', [['your_pokemon.php','Your Pokémon'],['change_team.php','Change Team'],['pokedex.php','Pokédex'],['items.php','Items & Shop'],['trade.php','Trade Center']]],
    ['Trainer Network', [['community.php','Community'],['messages.php','Messages'],['clans.php','Clans'],['members.php','Trainers']]],
    ['Account', [['your_account.php','Your Account'],['options.php','Options']]],
  ];
  groups.forEach(([label, links]) => {
    const group = make('div', 'pv-side-group');
    const title = make('div', 'pv-side-label'); title.textContent = label; group.appendChild(title);
    links.forEach(([href, text]) => {
      const a = make('a', href === 'battle_select.php' ? 'active' : ''); a.href = href;
      const s = document.createElement('span'); s.textContent = text; const i = document.createElement('i'); i.textContent = '›';
      a.append(s, i); group.appendChild(a);
    });
    side.appendChild(group);
  });

  const banner = make('section', 'pv-combat-runtime-banner');
  banner.innerHTML = `<div><span class="pv-eyebrow">BATTLE NETWORK // ${isLive ? 'TRAINER VS TRAINER' : 'TRAINER CHALLENGE'}</span><h1>${isLive ? 'Live Battle' : 'Trainer Battle'}</h1><p>${isLive ? 'Battle another trainer live with synchronized turns, team switches and shared results.' : 'Take on League, Event, Sidequest and rival trainers in the full animated battle arena.'}</p></div><div class="pv-combat-runtime-signal"><i></i><strong>BATTLE READY</strong><span>CONNECTED</span></div>`;

  const footer = make('footer', 'pv-footer pv-combat-footer', '<div class="pv-footer-main"><strong>Pokémon Vortex</strong><span>Unified Combat Runtime</span></div><div class="pv-footer-links"><a href="battle_select.php">Battle Arena</a><a href="contactus.php">Support</a><a href="terms.php">Terms</a><a href="privacy.php">Privacy</a></div><div class="pv-footer-signal"><i></i><span>VORTEX NETWORK</span></div>');

  const legacyHeader = document.getElementById('header');
  const legacyUser = document.getElementById('usernav');
  const legacySide = document.getElementById('sidebar');
  if (legacyHeader) legacyHeader.setAttribute('aria-hidden', 'true');
  if (legacyUser) legacyUser.setAttribute('aria-hidden', 'true');
  if (legacySide) legacySide.setAttribute('aria-hidden', 'true');

  container.insertBefore(header, container.firstChild);
  header.after(strip);
  contentContainer.insertBefore(side, contentContainer.firstChild);
  ajax.insertBefore(banner, ajax.firstChild);
  container.appendChild(footer);
  body.classList.add('pv-combat-shell-upgraded');

  // Re-run the responsive nav behavior for the shell inserted after the base
  // script initialized. This is presentation-only; combat form semantics stay untouched.
  const toggle = header.querySelector('[data-pv-nav-toggle]');
  const nav = header.querySelector('.pv-nav');
  if (toggle && nav) {
    const close = () => { header.classList.remove('pv-nav-open'); toggle.setAttribute('aria-expanded','false'); };
    toggle.addEventListener('click', () => {
      const open = !header.classList.contains('pv-nav-open');
      header.classList.toggle('pv-nav-open', open); toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    nav.addEventListener('click', (e) => { if (e.target.closest('a')) close(); });
    window.addEventListener('resize', () => { if (window.innerWidth > 900) close(); }, { passive: true });
  }

  const directTable = (form) => Array.from(form?.children || []).find(el => el.tagName === 'TABLE') || form?.querySelector('table') || null;
  const submitFormFor = (input) => {
    const form = input?.form;
    if (!form) return;
    input.checked = true;
    const submit = form.querySelector('button[type="submit"]:not([disabled]),input[type="submit"]:not([disabled])');
    if (typeof form.requestSubmit === 'function') form.requestSubmit(submit || undefined);
    else if (submit) submit.click();
    else form.submit();
  };
  const parseFighter = (nameCell, hpCell, playerSide) => {
    const heading = normalize(nameCell?.querySelector('h3')?.textContent || 'Pokémon');
    const img = nameCell?.querySelector('img[src*="/pokemon/"]');
    const levelMatch = normalize(nameCell?.textContent).match(/Level:\s*(\d+)/i);
    const hpText = normalize(hpCell?.textContent);
    const hpMatch = hpText.match(/HP:\s*(\d+)/i);
    const bar = hpCell?.querySelector('img[src*="hpbar"]');
    let pct = Number.parseFloat(bar?.getAttribute('width') || bar?.style?.width || '100');
    if (!Number.isFinite(pct)) pct = 100;
    pct = Math.max(0, Math.min(100, pct));
    const current = hpMatch ? Number.parseInt(hpMatch[1], 10) : 0;
    const max = pct > 0 && current > 0 ? Math.max(current, Math.round(current / (pct / 100))) : current;
    const src = img?.getAttribute('src') || '';
    const srcName = src.split('/').pop()?.replace(/\.(?:gif|png|webp)$/i, '') || '';
    let species = '';
    try { species = decodeURIComponent(srcName); } catch (_) { species = srcName; }
    let display = heading;
    if (playerSide) display = display.replace(/^Your\s+/i, '');
    if (!species) species = display.replace(/^Your\s+/i, '').replace(/^.+?'s\s+/i, '');
    return { heading, display, species, src, level: levelMatch ? Number(levelMatch[1]) : 1, current, max, pct };
  };
  const fighterCard = (fighter, enemy, labelOverride = '') => {
    const article = make('article', `pv-wild-fighter ${enemy ? 'pv-wild-enemy' : 'pv-wild-player'} pv-combat-fighter-card`);
    const roleLabel = labelOverride || (enemy ? (isLive ? 'OPPOSING TRAINER' : 'NPC TARGET') : 'ACTIVE PARTNER');
    const hud = `<div class="pv-wild-fighter-hud"><div><small>${esc(roleLabel)}</small><h2>${esc(fighter.display)}</h2><span>Lv. ${fighter.level} · ${enemy ? (isLive ? 'Live opponent' : 'Trainer battle') : trainer}</span></div><strong>${fighter.max > 0 ? `${fighter.current} / ${fighter.max} HP` : `${fighter.current} HP`}</strong></div><div class="pv-wild-hp"><i style="width:${fighter.pct}%"></i></div>`;
    const side = enemy ? 'enemy' : 'player';
    const sprite = `<div class="pv-wild-sprite-zone" data-pv-fighter="${side}" data-pv-fighter-hp="${Math.max(0, Number(fighter.current) || 0)}" data-pv-fighter-level="${Math.max(1, Number(fighter.level) || 1)}"><span class="pv-wild-scan-ring"></span><img src="${esc(fighter.src)}" alt="${esc(fighter.display)}"></div>`;
    article.innerHTML = enemy ? `${hud}${sprite}` : `${sprite}${hud}`;
    return article;
  };
  const buildArena = (battleTable) => {
    const rows = Array.from(battleTable?.rows || []);
    if (rows.length < 2 || rows[0].cells.length < 2 || rows[1].cells.length < 2) return null;
    const player = parseFighter(rows[0].cells[0], rows[1].cells[0], true);
    const enemy = parseFighter(rows[0].cells[1], rows[1].cells[1], false);
    const applyAuthoritativeHp = (fighter, currentRaw, maxRaw) => {
      const current = Number(currentRaw);
      const max = Number(maxRaw);
      if (Number.isFinite(current) && Number.isFinite(max) && max > 0) {
        fighter.current = Math.max(0, current);
        fighter.max = Math.max(1, max);
        fighter.pct = Math.max(0, Math.min(100, (fighter.current / fighter.max) * 100));
      }
    };
    applyAuthoritativeHp(player, battleTable?.dataset?.pvPlayerCurrentHp, battleTable?.dataset?.pvPlayerMaxHp);
    applyAuthoritativeHp(enemy, battleTable?.dataset?.pvEnemyCurrentHp, battleTable?.dataset?.pvEnemyMaxHp);
    if (!player.src || !enemy.src) return null;
    const arena = make('div', 'pv-wild-arena pv-combat-modern-arena');
    arena.appendChild(fighterCard(enemy, true));
    const vs = make('div', 'pv-wild-versus', '<span>VS</span><i></i>');
    arena.appendChild(vs);
    arena.appendChild(fighterCard(player, false));
    return { arena, rows, player, enemy };
  };
  const parseRosterFighter = (table) => {
    if (!table) return null;
    const img = table.querySelector('img[src*="/pokemon/"]');
    const text = normalize(table.textContent);
    const link = table.querySelector('strong a, strong');
    const display = normalize(link?.textContent || 'Pokémon');
    const level = Number(text.match(/Level:\s*(\d+)/i)?.[1] || 1);
    const current = Math.max(0, Number(table.dataset.pvRosterCurrentHp ?? text.match(/HP:\s*(\d+)/i)?.[1] ?? 0));
    const max = Math.max(1, Number(table.dataset.pvRosterMaxHp || current || 1));
    const pct = Math.max(0, Math.min(100, (current / max) * 100));
    const src = img?.getAttribute('src') || '';
    if (!src) return null;
    return {display, species:display, src, level, current, max, pct};
  };
  const logFromTable = (rows) => {
    const entries = [];
    const sharedEvent = isLive ? normalize(ajax.querySelector('[data-pv-live-sync-event]')?.textContent || '') : '';
    if (sharedEvent) entries.push(sharedEvent);
    rows.slice(2).forEach(row => {
      const cells = Array.from(row.cells || []);
      const sources = cells.length ? cells : [row];
      sources.forEach(cell => {
        if (cell.querySelector?.('input[name="attack"],input[name="item"],input[name="active_pokemon"],input[type="submit"],button[type="submit"]')) return;
        const text = normalize(cell.textContent);
        if (!text || /Select an attack|Attacks:|Quantity:|Item:/i.test(text)) return;
        if (!entries.includes(text)) entries.push(text);
      });
    });
    return entries.slice(-10);
  };
  const buildLogPanel = (entries) => {
    const panel = make('section', 'pv-wild-log-panel');
    panel.innerHTML = '<div class="pv-map-panel-label">BATTLE LOG</div>';
    const log = make('div', 'pv-wild-log');
    (entries.length ? entries : [isLive ? 'Live combat channel synchronized.' : 'Trainer combat channel ready.']).forEach((text, index) => {
      const row = make('div', index === 0 ? 'tone-accent' : 'tone-muted');
      row.innerHTML = '<i></i><span></span>'; row.querySelector('span').textContent = text; log.appendChild(row);
    });
    panel.appendChild(log); return panel;
  };
  const buttonTab = (name, target, active = false) => {
    const btn = make('button', active ? 'active' : ''); btn.type = 'button'; btn.dataset.pvBattleTab = target; btn.textContent = name; return btn;
  };
  const wireTabs = (root, views) => {
    const buttons = Array.from(root.querySelectorAll('[data-pv-battle-tab]'));
    buttons.forEach(btn => btn.addEventListener('click', () => {
      buttons.forEach(b => b.classList.toggle('active', b === btn));
      views.forEach(v => v.classList.toggle('active', v.dataset.pvBattleView === btn.dataset.pvBattleTab));
    }));
  };
  const moveNames = (form) => {
    const radios = Array.from(form.querySelectorAll('input[name="attack"]'));
    const parent = radios[0]?.parentElement;
    const lines = (parent?.innerText || '').split(/\n+/).map(normalize).filter(Boolean);
    const bySlot = new Map();
    lines.forEach(line => {
      const m = line.match(/^(\d+)\.\s*(.+)$/); if (m) bySlot.set(m[1], m[2]);
    });
    return radios.map((radio, index) => ({ radio, slot: String(radio.value || index + 1), name: bySlot.get(String(radio.value)) || `Move ${index + 1}` }));
  };
  const buildControlPanel = (attackForm) => {
    const panel = make('section', 'pv-wild-control-panel');
    panel.innerHTML = '<div class="pv-map-panel-label">BATTLE COMMAND</div>';
    const tabs = make('div', 'pv-wild-command-tabs pv-combat-command-tabs');
    const views = [];

    const moves = moveNames(attackForm);
    if (moves.length) {
      tabs.appendChild(buttonTab('Fight', 'moves', true));
      const view = make('div', 'pv-wild-command-view active'); view.dataset.pvBattleView = 'moves';
      const grid = make('div', 'pv-wild-move-grid');
      moves.forEach(({radio, slot, name}) => {
        const wrap = document.createElement('div');
        const btn = make('button'); btn.type = 'button';
        const type = normalize(radio.dataset.pvMoveType || 'Move');
        const power = normalize(radio.dataset.pvMovePower || '');
        const accuracy = normalize(radio.dataset.pvMoveAccuracy || '');
        const meta = [type, power ? `${power} PWR` : '', accuracy ? `${accuracy}% ACC` : ''].filter(Boolean).join(' · ');
        btn.innerHTML = `<small>${esc(meta || `MOVE SLOT ${slot}`)}</small><strong>${esc(radio.dataset.pvMoveName || name)}</strong><span>CHOOSE MOVE</span>`;
        btn.addEventListener('click', () => submitFormFor(radio)); wrap.appendChild(btn); grid.appendChild(wrap);
      });
      view.appendChild(grid); panel.appendChild(view); views.push(view);
    }

    const itemInputs = Array.from(ajax.querySelectorAll('input[name="item"]'));
    if (itemInputs.length) {
      tabs.appendChild(buttonTab('Items', 'items', !moves.length));
      const view = make('div', `pv-wild-command-view${moves.length ? '' : ' active'}`); view.dataset.pvBattleView = 'items';
      const grid = make('div', 'pv-wild-item-grid');
      itemInputs.forEach(input => {
        const tr = input.closest('tr');
        const text = normalize(tr?.textContent || input.value);
        const countMatch = text.match(/(\d+)\s*$/);
        const count = countMatch ? Number(countMatch[1]) : null;
        const btn = make('button'); btn.type = 'button'; btn.disabled = input.disabled;
        btn.innerHTML = `${esc(input.value)}<small>${count === null ? 'Battle item' : `${count.toLocaleString()} owned`}</small>`;
        btn.addEventListener('click', () => submitFormFor(input)); grid.appendChild(btn);
      });
      view.appendChild(grid); panel.appendChild(view); views.push(view);
    }

    const teamInputs = Array.from(ajax.querySelectorAll('input[name="active_pokemon"]'));
    if (teamInputs.length) {
      tabs.appendChild(buttonTab('Team', 'team', !moves.length && !itemInputs.length));
      const view = make('div', `pv-wild-command-view${(!moves.length && !itemInputs.length) ? ' active' : ''}`); view.dataset.pvBattleView = 'team';
      const grid = make('div', 'pv-wild-team-grid');
      teamInputs.forEach(input => {
        const row = input.closest('table');
        const img = row?.querySelector('img[src*="/pokemon/"]');
        const text = normalize(row?.textContent || 'Pokémon');
        const name = normalize(row?.querySelector('strong')?.textContent || text.split('Level:')[0] || 'Pokémon');
        const level = text.match(/Level:\s*(\d+)/i)?.[1] || '?';
        const hp = text.match(/HP:\s*(\d+)/i)?.[1] || '?';
        const btn = make('button'); btn.type = 'button'; btn.disabled = input.disabled;
        btn.innerHTML = `<img src="${esc(img?.getAttribute('src') || '')}" alt=""><span><strong>${esc(name)}</strong><small>Lv. ${esc(level)} · ${esc(hp)} HP</small><i><b style="width:${input.disabled ? 0 : 100}%"></b></i></span>`;
        btn.addEventListener('click', () => submitFormFor(input)); grid.appendChild(btn);
      });
      view.appendChild(grid); panel.appendChild(view); views.push(view);
    }

    if (!tabs.children.length) return null;
    tabs.style.gridTemplateColumns = `repeat(${tabs.children.length},minmax(0,1fr))`;
    panel.insertBefore(tabs, panel.children[1] || null);
    wireTabs(tabs, views);
    return panel;
  };
  const parseLegacyStrike = (text, actorName, targetName, playerSide, type = 'normal') => {
    text = normalize(text);
    if (!text || !actorName || !targetName) return null;
    const escapeRx = (value) => String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const actor = escapeRx(actorName);
    const target = escapeRx(targetName);
    const attackRx = playerSide
      ? new RegExp(String.raw`Your\s+${actor}\s+attacked\s+${target}\s+with\s+(.+?)(?=\s+and\s+(?:did|had)|\s+but\s+missed|\.|$)`, 'i')
      : new RegExp(String.raw`${actor}\s+attacked\s+your\s+${target}\s+with\s+(.+?)(?=\s+and\s+(?:did|had)|\s+but\s+missed|\.|$)`, 'i');
    const usedRx = playerSide
      ? new RegExp(String.raw`Your\s+${actor}\s+used\s+(.+?)(?=\s+and\s+|\.|$)`, 'i')
      : new RegExp(String.raw`${actor}\s+used\s+(.+?)(?=\s+and\s+|\.|$)`, 'i');
    const move = text.match(attackRx)?.[1] || text.match(usedRx)?.[1] || '';
    if (!move) return null;
    const damage = Number(text.match(/(?:did|dealt)\s+(\d+)\s+HP\s+damage/i)?.[1] || 0);
    const missed = /\bmissed\b/i.test(text);
    let effectiveness = 1;
    if (/ultra effective/i.test(text)) effectiveness = 4;
    else if (/super effective/i.test(text)) effectiveness = 2;
    else if (/not very effective|resisted/i.test(text)) effectiveness = .5;
    else if (/no effect|no damage/i.test(text)) effectiveness = 0;
    return { move: normalize(move), type: normalize(type || 'normal').toLowerCase(), hit: !missed, damage, critical: /critical hit/i.test(text), effectiveness };
  };
  const deriveLegacyReplay = (arenaData, battleTable) => {
    const action = body.dataset.pvBattleAction || 'idle';
    const messages = logFromTable(arenaData.rows);
    const replay = { action, player:null, enemy:null, item:body.dataset.pvBattleItem || '' };
    const playerType = battleTable?.dataset?.pvPlayerMoveType || 'normal';
    const enemyType = battleTable?.dataset?.pvEnemyMoveType || 'normal';
    for (const message of messages) {
      if (!replay.player) replay.player = parseLegacyStrike(message, arenaData.player.species, arenaData.enemy.species, true, playerType);
      if (!replay.enemy) replay.enemy = parseLegacyStrike(message, arenaData.enemy.species, arenaData.player.species, false, enemyType);
    }
    return replay;
  };
  const buildContinuePanel = (form) => {
    if (!form) return null;
    const nativeSubmit = form.querySelector('button[type="submit"]:not([disabled]),input[type="submit"]:not([disabled])');
    if (!nativeSubmit) return null;
    const panel = make('section', 'pv-wild-control-panel pv-combat-continue-panel');
    panel.innerHTML = '<div class="pv-map-panel-label">BATTLE COMMAND</div><div class="pv-combat-continue-copy"><strong>Turn resolved</strong><span>Continue to the next combat state.</span></div>';
    const btn = make('button', 'pv-button'); btn.type = 'button'; btn.textContent = normalize(nativeSubmit.value || nativeSubmit.textContent || 'Continue');
    btn.addEventListener('click', () => {
      if (typeof form.requestSubmit === 'function') form.requestSubmit(nativeSubmit);
      else nativeSubmit.click();
    });
    panel.appendChild(btn);
    return panel;
  };
  const vaultLegacy = (keep = []) => {
    const vault = make('div', 'pv-combat-legacy-vault');
    const keepSet = new Set([banner, ...keep]);
    Array.from(ajax.childNodes).forEach(node => { if (!keepSet.has(node)) vault.appendChild(node); });
    ajax.appendChild(vault);
    return vault;
  };

  const upgradeActiveBattle = () => {
    // The recovered engine has a distinct KO/Continue state where the attack
    // radios disappear but the two-fighter table is still the authoritative
    // current turn. Key the modern surface from the battle table itself so a
    // knockout can never fall back to the stacked legacy layout.
    const battleTable = ajax.querySelector('.pv-legacy-combat-table');
    if (!battleTable) return false;
    const attackForm = battleTable.closest('form') || Array.from(ajax.querySelectorAll('form')).find(form => form.contains(battleTable)) || null;
    const arenaData = buildArena(battleTable);
    if (!arenaData) return false;
    arenaData.arena.dataset.pvBattleFxStage = 'standard';
    const surface = make('div', 'pv-combat-modern-surface');
    surface.dataset.pvStandardBattleSurface = '1';
    surface.dataset.pvStandardFx = JSON.stringify(deriveLegacyReplay(arenaData, battleTable));
    surface.appendChild(arenaData.arena);
    const lower = make('div', 'pv-wild-lower-grid');
    lower.appendChild(buildLogPanel(logFromTable(arenaData.rows)));
    const controls = attackForm ? buildControlPanel(attackForm) : null;
    lower.appendChild(controls || buildContinuePanel(attackForm) || make('section', 'pv-wild-control-panel pv-combat-continue-panel', '<div class="pv-map-panel-label">BATTLE COMMAND</div><div class="pv-combat-continue-copy"><strong>Turn resolved</strong><span>Continue to the next battle moment.</span></div>'));
    surface.appendChild(lower);
    vaultLegacy([surface]);
    ajax.insertBefore(surface, ajax.querySelector('.pv-combat-legacy-vault'));
    body.classList.add('pv-combat-surface-upgraded');
    return true;
  };
  const upgradeTeamChoice = () => {
    if (ajax.querySelector('input[name="attack"]')) return false;
    const teamInputs = Array.from(ajax.querySelectorAll('input[name="active_pokemon"]'));
    if (!teamInputs.length) return false;
    const surface = make('div', 'pv-combat-modern-surface pv-combat-team-choice');
    surface.dataset.pvStandardBattleSurface = '1';
    surface.dataset.pvStandardFx = JSON.stringify({action:'intro'});

    const selectedInput = teamInputs.find(input => input.checked && !input.disabled) || teamInputs.find(input => !input.disabled) || teamInputs[0];
    const playerPreview = parseRosterFighter(selectedInput?.closest('.pv-battle-roster-card, table'));
    const enemyTables = Array.from(ajax.querySelectorAll('#opponent_pokemon .pv-battle-roster-card, #opponent_pokemon > table'));
    const enemyTable = enemyTables.find(table => Number(table.dataset.pvRosterCurrentHp || 0) > 0) || enemyTables[0] || null;
    const enemyPreview = parseRosterFighter(enemyTable);

    if (playerPreview && enemyPreview) {
      const arena = make('div', 'pv-wild-arena pv-combat-modern-arena pv-combat-selection-arena');
      arena.dataset.pvBattleFxStage = 'standard';
      arena.appendChild(fighterCard(enemyPreview, true, 'CURRENT TARGET'));
      arena.appendChild(make('div', 'pv-wild-versus', '<span>VS</span><i></i>'));
      arena.appendChild(fighterCard(playerPreview, false, 'REPLACEMENT READY'));
      surface.appendChild(arena);
    }

    const lower = make('div', 'pv-wild-lower-grid');
    lower.appendChild(buildLogPanel(['Choose your next Pokémon to continue the battle.']));
    const controls = make('section', 'pv-wild-control-panel pv-combat-team-choice-panel');
    controls.innerHTML = '<div class="pv-map-panel-label">BATTLE COMMAND // TEAM</div><div class="pv-combat-continue-copy"><strong>Choose your next Pokémon</strong><span>The selected partner is submitted to the existing battle form; no combat state is predicted in the browser.</span></div>';
    const grid = make('div', 'pv-wild-team-grid pv-combat-team-choice-grid');
    teamInputs.forEach(input => {
      const row = input.closest('.pv-battle-roster-card, table');
      const fighter = parseRosterFighter(row);
      const btn = make('button'); btn.type = 'button'; btn.disabled = input.disabled;
      const pct = fighter ? fighter.pct : (input.disabled ? 0 : 100);
      btn.innerHTML = `<img src="${esc(fighter?.src || '')}" alt=""><span><strong>${esc(fighter?.display || 'Pokémon')}</strong><small>Lv. ${esc(fighter?.level ?? '?')} · ${esc(fighter?.current ?? '?')} / ${esc(fighter?.max ?? '?')} HP</small><i><b style="width:${pct}%"></b></i></span>`;
      btn.addEventListener('click', () => submitFormFor(input)); grid.appendChild(btn);
    });
    controls.appendChild(grid);
    lower.appendChild(controls);
    surface.appendChild(lower);
    vaultLegacy([surface]);
    ajax.insertBefore(surface, ajax.querySelector('.pv-combat-legacy-vault'));
    body.classList.add('pv-combat-surface-upgraded');
    return true;
  };
  const upgradeOutcome = () => {
    const heading = Array.from(ajax.querySelectorAll('h2')).find(h => /won the battle|lost the battle/i.test(normalize(h.textContent)));
    if (!heading) return false;
    const won = /won the battle/i.test(normalize(heading.textContent));
    const sub = heading.nextElementSibling?.tagName === 'H3' ? normalize(heading.nextElementSibling.textContent) : '';
    const reward = Array.from(ajax.querySelectorAll('p')).map(p => normalize(p.textContent)).find(t => /experience points|won.*to buy items/i.test(t)) || '';
    const participantImgs = Array.from(ajax.querySelectorAll('img[src*="/pokemon/"]')).slice(0, 6);
    const optionLinks = Array.from(ajax.querySelectorAll('.optionsList a')).filter(a => a.getAttribute('href'));
    const surface = make('div', 'pv-combat-modern-surface pv-combat-outcome-surface');
    const result = make('div', `pv-wild-result ${won ? 'pv-wild-result-won' : 'pv-wild-result-lost'}`);
    result.innerHTML = `<span>${won ? 'VICTORY CONFIRMED' : 'DEFEAT RECORDED'}</span><h2>${esc(won ? 'Battle won' : 'Battle complete')}</h2><p>${esc(sub || normalize(heading.textContent))}</p>`;
    if (reward) {
      const info = make('div', 'pv-combat-outcome-reward'); info.textContent = reward; result.appendChild(info);
    }
    if (participantImgs.length) {
      const roster = make('div', 'pv-combat-outcome-roster');
      participantImgs.forEach(img => { const clone = img.cloneNode(true); clone.removeAttribute('align'); roster.appendChild(clone); });
      result.appendChild(roster);
    }
    if (optionLinks.length) {
      const actions = make('div', 'pv-wild-result-actions');
      optionLinks.slice(0, 6).forEach((link, index) => {
        const a = make('a', `pv-button${index ? ' pv-button-secondary' : ''}`); a.href = link.href; a.textContent = normalize(link.textContent) || 'Continue'; actions.appendChild(a);
      });
      result.appendChild(actions);
    }
    surface.appendChild(result);
    window.PVMotion?.celebrate?.(result, won ? 'victory' : 'defeat');
    vaultLegacy([surface]);
    ajax.insertBefore(surface, ajax.querySelector('.pv-combat-legacy-vault'));
    body.classList.add('pv-combat-surface-upgraded');
    return true;
  };
  const upgradeWaiting = () => {
    const waiting = ajax.querySelector('[data-pv-live-wait]');
    if (!waiting) return false;
    const surface = make('div', 'pv-combat-modern-surface pv-live-wait-modern');
    surface.innerHTML = `<div class="pv-live-sync-core"><span class="pv-wild-scan-ring"></span><i></i><small>LIVE MATCH SYNCHRONIZATION</small><h2>Waiting for the other trainer</h2><p>The server is polling the durable match row. You will advance automatically when the opponent selects a Pokémon, submits a command, or reaches a terminal result.</p><a class="pv-button pv-button-secondary" href="live_battle.php">Check Now</a></div>`;
    vaultLegacy([surface]);
    ajax.insertBefore(surface, ajax.querySelector('.pv-combat-legacy-vault'));
    body.classList.add('pv-combat-surface-upgraded');
    return true;
  };

  // Keep the recovered HTML/forms as a no-JavaScript fallback, but in normal
  // browsers render the actual battle command state using the same visual
  // grammar as Wild Battle. No hidden form value is invented client-side.
  if (!upgradeActiveBattle() && !upgradeTeamChoice() && !upgradeOutcome()) upgradeWaiting();
})();

/* v24.1.1 — one command-tab contract for Wild, Trainer/Snapshot and Live battles. */
(() => {
  'use strict';
  const initialize = () => {
    document.querySelectorAll('[data-pv-unified-command-tabs]').forEach(tabs => {
      if (tabs.dataset.pvTabsReady === '1') return;
      const panel = tabs.closest('.pv-wild-control-panel') || tabs.parentElement;
      if (!panel) return;
      const buttons = Array.from(tabs.querySelectorAll('[data-pv-battle-tab]'));
      const views = Array.from(panel.querySelectorAll('[data-pv-battle-view]'));
      if (!buttons.length || !views.length) return;
      const activate = (target) => {
        buttons.forEach(button => {
          const active = button.dataset.pvBattleTab === target;
          button.classList.toggle('active', active);
          button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        views.forEach(view => view.classList.toggle('active', view.dataset.pvBattleView === target));
      };
      tabs.setAttribute('role', 'tablist');
      buttons.forEach(button => {
        button.setAttribute('role', 'tab');
        button.addEventListener('click', () => activate(button.dataset.pvBattleTab || 'moves'));
      });
      tabs.dataset.pvTabsReady = '1';
      activate(buttons.find(button => button.classList.contains('active'))?.dataset.pvBattleTab || buttons[0].dataset.pvBattleTab || 'moves');
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once:true});
  else initialize();
})();

/* v24.1.1 — unified cinematic battle-turn replay and capture choreography. */
(() => {
  'use strict';

  const reducedMotion = window.PVMotion?.reducedMotion === true || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
  const wait = (ms) => new Promise(resolve => window.setTimeout(resolve, ms));
  const clampNumber = (value, min, max) => Math.max(min, Math.min(max, Number(value) || 0));

  const safeTypeClass = (type) => {
    const key = String(type || 'normal').trim().toLowerCase().replace(/[^a-z]/g, '');
    const allowed = new Set(['normal','fire','water','electric','grass','ice','fighting','poison','ground','flying','psychic','bug','rock','ghost','dragon','dark','steel','fairy']);
    return `pv-fx-type-${allowed.has(key) ? key : 'normal'}`;
  };

  const itemAsset = (name) => {
    const own = document.querySelector('script[src*="vortex-modern.js"]');
    if (!own?.src) return `html/static/images/items/${encodeURIComponent(name)}.png`;
    return new URL(`../../html/static/images/items/${encodeURIComponent(name)}.png`, own.src).href;
  };

  const makeLayer = (stage) => {
    let layer = stage.querySelector(':scope > .pv-cinematic-fx-layer');
    if (layer) return layer;
    layer = document.createElement('div');
    layer.className = 'pv-cinematic-fx-layer';
    layer.setAttribute('aria-hidden', 'true');
    stage.appendChild(layer);
    return layer;
  };

  const localPoint = (stage, element, xRatio = .5, yRatio = .5) => {
    const sr = stage.getBoundingClientRect();
    const er = element.getBoundingClientRect();
    return {
      x: er.left - sr.left + (er.width * xRatio),
      y: er.top - sr.top + (er.height * yRatio),
    };
  };

  const flashStage = async (stage, className = 'pv-fx-stage-impact', duration = 240) => {
    stage.classList.remove(className);
    void stage.offsetWidth;
    stage.classList.add(className);
    await wait(duration);
    stage.classList.remove(className);
  };

  const spawnImpact = (stage, target, data = {}, options = {}) => {
    const layer = makeLayer(stage);
    const impact = document.createElement('span');
    impact.className = `pv-turn-impact ${safeTypeClass(data.type)} ${data.critical ? 'is-critical' : ''} ${options.kind === 'capture' ? 'is-capture' : ''}`;
    const point = localPoint(stage, target, .5, .5);
    impact.style.left = `${point.x}px`;
    impact.style.top = `${point.y}px`;
    impact.style.setProperty('--pv-impact-scale', String(data.critical ? 1.28 : 1));

    const core = document.createElement('b');
    impact.appendChild(core);
    for (let i = 0; i < 12; i += 1) {
      const shard = document.createElement('i');
      shard.style.setProperty('--pv-shard-angle', `${i * 30}deg`);
      shard.style.setProperty('--pv-shard-distance', `${42 + ((i * 17) % 44)}px`);
      shard.style.setProperty('--pv-shard-delay', `${(i % 4) * 18}ms`);
      impact.appendChild(shard);
    }
    layer.appendChild(impact);
    window.setTimeout(() => impact.remove(), 850);
    return impact;
  };

  const spawnFloat = (stage, target, text, className = '') => {
    if (!text) return;
    const layer = makeLayer(stage);
    const point = localPoint(stage, target, .5, .25);
    const label = document.createElement('strong');
    label.className = `pv-turn-float ${className}`;
    label.textContent = text;
    label.style.left = `${point.x}px`;
    label.style.top = `${point.y}px`;
    layer.appendChild(label);
    window.setTimeout(() => label.remove(), 1250);
  };

  const spawnMoveBanner = (stage, actor, text, type) => {
    if (!text) return;
    const layer = makeLayer(stage);
    const point = localPoint(stage, actor, .5, .18);
    const label = document.createElement('span');
    label.className = `pv-turn-move-banner ${safeTypeClass(type)}`;
    label.textContent = text;
    label.style.left = `${point.x}px`;
    label.style.top = `${point.y}px`;
    layer.appendChild(label);
    window.setTimeout(() => label.remove(), 1100);
  };

  const playStrike = async (stage, actor, target, data, actorSide) => {
    if (!stage || !actor || !target || !data) return;
    const hit = data.hit !== false;
    const damage = Math.max(0, Number(data.damage) || 0);
    spawnMoveBanner(stage, actor, String(data.move || 'Attack'), data.type);

    actor.classList.remove('is-fx-lunging-player','is-fx-lunging-enemy');
    void actor.offsetWidth;
    actor.classList.add(actorSide === 'player' ? 'is-fx-lunging-player' : 'is-fx-lunging-enemy');
    stage.classList.add('is-fx-combat-live');
    await wait(205);

    if (hit) {
      target.classList.remove('is-fx-hit-player','is-fx-hit-enemy');
      void target.offsetWidth;
      target.classList.add(actorSide === 'player' ? 'is-fx-hit-enemy' : 'is-fx-hit-player');
      spawnImpact(stage, target, data);
      if (damage > 0) spawnFloat(stage, target, `-${damage}`, data.critical ? 'is-critical' : '');
      if (data.critical) spawnFloat(stage, target, 'CRITICAL!', 'is-critical-callout');
      else if (Number(data.effectiveness) >= 2) spawnFloat(stage, target, 'SUPER EFFECTIVE', 'is-effective');
      else if (Number(data.effectiveness) > 0 && Number(data.effectiveness) < 1) spawnFloat(stage, target, 'RESISTED', 'is-resisted');
      void flashStage(stage, data.critical ? 'pv-fx-stage-critical' : 'pv-fx-stage-impact', data.critical ? 360 : 240);
    } else {
      target.classList.remove('is-fx-dodging');
      void target.offsetWidth;
      target.classList.add('is-fx-dodging');
      spawnFloat(stage, target, 'MISS', 'is-miss');
    }

    await wait(data.critical ? 430 : 360);
    actor.classList.remove('is-fx-lunging-player','is-fx-lunging-enemy');
    target.classList.remove('is-fx-hit-player','is-fx-hit-enemy','is-fx-dodging');
    await wait(120);
  };

  const playHeal = async (stage, player, heal) => {
    if (!heal || !player) return;
    const amount = Math.max(0, Number(heal.amount) || 0);
    const label = amount > 0 ? `+${amount} HP` : String(heal.label || 'RECOVERY').toUpperCase();
    player.classList.remove('is-fx-healing');
    void player.offsetWidth;
    player.classList.add('is-fx-healing');
    spawnFloat(stage, player, label, 'is-heal');
    await wait(650);
    player.classList.remove('is-fx-healing');
  };

  const playFaint = async (stage, fighter) => {
    if (!stage || !fighter || Number(fighter.dataset.pvFighterHp || 1) > 0) return;
    fighter.classList.remove('is-fx-fainting','is-fainted');
    void fighter.offsetWidth;
    fighter.classList.add('is-fx-fainting');
    spawnFloat(stage, fighter, 'FAINTED', 'is-faint');
    await wait(660);
    fighter.classList.remove('is-fx-fainting');
    fighter.classList.add('is-fainted');
  };

  const playSwitch = async (stage, player, sw) => {
    if (!player || !sw) return;
    player.classList.remove('is-fx-switching');
    void player.offsetWidth;
    player.classList.add('is-fx-switching');
    spawnFloat(stage, player, `GO, ${String(sw.name || 'PARTNER').toUpperCase()}!`, 'is-switch');
    await wait(700);
    player.classList.remove('is-fx-switching');
  };

  const captureBallShakes = async (ball, count) => {
    for (let i = 0; i < count; i += 1) {
      await ball.animate([
        { transform:'translate3d(-7px,0,0) rotate(-17deg)' },
        { transform:'translate3d(8px,-2px,0) rotate(19deg)' },
        { transform:'translate3d(0,0,0) rotate(0deg)' },
      ], { duration:260, easing:'cubic-bezier(.22,.8,.3,1)' }).finished.catch(() => {});
      await wait(115);
    }
  };

  const playCapture = async (stage, player, enemy, capture) => {
    if (!capture || !stage || !player || !enemy) return;
    const layer = makeLayer(stage);
    stage.classList.add('is-fx-capture-live');

    const start = localPoint(stage, player, .5, .42);
    const hit = localPoint(stage, enemy, .5, .48);
    const enemyRect = enemy.getBoundingClientRect();
    const stageRect = stage.getBoundingClientRect();
    const ground = {
      x: hit.x,
      y: Math.min(stageRect.height - 42, (enemyRect.bottom - stageRect.top) - 31),
    };

    const ball = document.createElement('img');
    ball.className = 'pv-capture-ball-fx';
    ball.src = itemAsset(String(capture.ball || 'Poke Ball'));
    ball.alt = '';
    ball.style.left = `${start.x}px`;
    ball.style.top = `${start.y}px`;
    layer.appendChild(ball);

    const arcX = hit.x - start.x;
    const arcY = hit.y - start.y;
    await ball.animate([
      { transform:'translate3d(-50%,-50%,0) rotate(0deg) scale(.72)', offset:0 },
      { transform:`translate3d(calc(-50% + ${arcX * .52}px),calc(-50% + ${arcY * .38 - 90}px),0) rotate(420deg) scale(1.12)`, offset:.52 },
      { transform:`translate3d(calc(-50% + ${arcX}px),calc(-50% + ${arcY}px),0) rotate(760deg) scale(.94)`, offset:1 },
    ], { duration:650, easing:'cubic-bezier(.2,.72,.2,1)', fill:'forwards' }).finished.catch(() => {});

    enemy.classList.add('is-fx-capture-target');
    spawnImpact(stage, enemy, {type:'psychic', critical:true}, {kind:'capture'});
    stage.classList.add('pv-fx-stage-capture-hit');
    await wait(110);
    stage.classList.remove('pv-fx-stage-capture-hit');

    const enemyImg = enemy.querySelector('img');
    let absorbAnimation = null;
    if (enemyImg) {
      absorbAnimation = enemyImg.animate([
        { transform:'scale(1)', filter:'brightness(1)' },
        { transform:'scale(1.15)', filter:'brightness(2.5) saturate(.35)', offset:.22 },
        { transform:'translate3d(0,-10px,0) scale(.08)', filter:'brightness(4) saturate(0)', opacity:.08 },
      ], { duration:430, easing:'cubic-bezier(.36,.05,.28,1)', fill:'forwards' });
      await absorbAnimation.finished.catch(() => {});
    }

    const fallX = ground.x - hit.x;
    const fallY = ground.y - hit.y;
    await ball.animate([
      { transform:`translate3d(calc(-50% + ${arcX}px),calc(-50% + ${arcY}px),0) rotate(760deg) scale(.94)` },
      { transform:`translate3d(calc(-50% + ${arcX + fallX}px),calc(-50% + ${arcY + fallY}px),0) rotate(900deg) scale(.94)` },
    ], { duration:340, easing:'cubic-bezier(.3,.05,.6,1)', fill:'forwards' }).finished.catch(() => {});

    ball.classList.add('is-grounded');
    await wait(140);
    await captureBallShakes(ball, capture.caught ? 3 : 2);

    if (capture.caught) {
      ball.classList.add('is-captured');
      const beacon = document.createElement('span');
      beacon.className = 'pv-capture-success-beacon';
      beacon.style.left = `${ground.x}px`;
      beacon.style.top = `${ground.y}px`;
      for (let i=0;i<4;i+=1) beacon.appendChild(document.createElement('i'));
      layer.appendChild(beacon);
      spawnFloat(stage, enemy, 'CAPTURE SECURED!', 'is-capture-success');
      stage.classList.add('pv-fx-stage-capture-success');
      await wait(1050);
      stage.classList.remove('pv-fx-stage-capture-success');
      beacon.remove();
      if (enemyImg) { absorbAnimation?.cancel(); enemyImg.style.opacity = '0'; }
      const resultCard = document.querySelector('.pv-wild-result-captured');
      if (resultCard) window.PVMotion?.celebrate?.(resultCard, 'capture');
    } else {
      ball.classList.add('is-breakout');
      spawnImpact(stage, enemy, {type:'normal', critical:true}, {kind:'capture'});
      spawnFloat(stage, enemy, 'BROKE FREE!', 'is-breakout');
      if (enemyImg) {
        absorbAnimation?.cancel();
        enemyImg.style.opacity = '';
        enemyImg.style.transform = '';
        enemyImg.style.filter = '';
        enemyImg.classList.add('pv-fx-reappear');
      }
      await wait(720);
      enemyImg?.classList.remove('pv-fx-reappear');
    }

    ball.remove();
    enemy.classList.remove('is-fx-capture-target');
    stage.classList.remove('is-fx-capture-live');
  };

  const playWildBattleFx = async () => {
    const stage = document.querySelector('[data-pv-battle-fx-stage]');
    const fxNode = document.getElementById('pv-wild-battle-fx');
    if (!stage || !fxNode) return;
    const player = stage.querySelector('[data-pv-fighter="player"]');
    const enemy = stage.querySelector('[data-pv-fighter="enemy"]');
    if (!player || !enemy) return;

    let fx;
    try { fx = JSON.parse(fxNode.textContent || '{}'); } catch (_) { return; }
    if (reducedMotion) return;

    stage.classList.add('pv-fx-replay-armed');
    await wait(330);

    switch (String(fx.action || 'intro')) {
      case 'attack':
        if (fx.player) {
          await playStrike(stage, player, enemy, fx.player, 'player');
          await playFaint(stage, enemy);
        }
        if (fx.enemy) {
          await playStrike(stage, enemy, player, fx.enemy, 'enemy');
          await playFaint(stage, player);
        }
        break;
      case 'heal':
        await playHeal(stage, player, fx.heal);
        if (fx.enemy) await playStrike(stage, enemy, player, fx.enemy, 'enemy');
        break;
      case 'switch':
        await playSwitch(stage, player, fx.switch);
        if (fx.enemy) await playStrike(stage, enemy, player, fx.enemy, 'enemy');
        break;
      case 'ball':
        await playCapture(stage, player, enemy, fx.capture || {ball:fx.detail,caught:false});
        if (fx.capture?.caught !== true && fx.enemy) await playStrike(stage, enemy, player, fx.enemy, 'enemy');
        break;
      case 'run':
        player.classList.add('is-fx-running');
        await wait(620);
        player.classList.remove('is-fx-running');
        break;
      default:
        player.classList.add('is-fx-intro-player');
        enemy.classList.add('is-fx-intro-enemy');
        await wait(720);
        player.classList.remove('is-fx-intro-player');
        enemy.classList.remove('is-fx-intro-enemy');
        break;
    }
    stage.classList.remove('pv-fx-replay-armed','is-fx-combat-live');
  };

  const playStandardBattleFx = async () => {
    const body = document.body;
    if (!body?.classList.contains('pv-legacy-battle-page') || reducedMotion) return;
    const surface = document.querySelector('[data-pv-standard-battle-surface]');
    const stage = surface?.querySelector('.pv-wild-arena');
    const player = stage?.querySelector('[data-pv-fighter="player"]');
    const enemy = stage?.querySelector('[data-pv-fighter="enemy"]');
    if (!surface || !stage || !player || !enemy || !player.querySelector('img') || !enemy.querySelector('img')) return;

    let fx = {};
    try { fx = JSON.parse(surface.dataset.pvStandardFx || '{}'); } catch (_) { fx = {}; }
    const action = String(fx.action || body.dataset.pvBattleAction || 'idle');
    stage.classList.add('pv-fx-replay-armed');
    await wait(330);

    if (action === 'item') {
      await playHeal(stage, player, {amount:0, label:fx.item || body.dataset.pvBattleItem || 'RECOVERY'});
      if (fx.enemy) {
        await playStrike(stage, enemy, player, fx.enemy, 'enemy');
        await playFaint(stage, player);
      }
    } else if (action === 'attack') {
      // The recovered Standard/Trainer engine resolves the paired turn and renders
      // the opponent event before the player event. Replay that same resolved order
      // and postpone KO collapse until both already-authoritative actions have shown.
      if (fx.enemy) await playStrike(stage, enemy, player, fx.enemy, 'enemy');
      if (fx.player) await playStrike(stage, player, enemy, fx.player, 'player');
      await playFaint(stage, player);
      await playFaint(stage, enemy);
    } else {
      player.classList.add('is-fx-intro-player');
      enemy.classList.add('is-fx-intro-enemy');
      await wait(620);
      player.classList.remove('is-fx-intro-player');
      enemy.classList.remove('is-fx-intro-enemy');
    }
    stage.classList.remove('pv-fx-replay-armed','is-fx-combat-live');
  };

  const playLiveBattleFx = async () => {
    if (reducedMotion) return;
    const surface = document.querySelector('[data-pv-live-runtime][data-pv-live-turn]');
    if (!surface) return;
    const turn = Number(surface.dataset.pvLiveTurn || 0);
    const phase = surface.dataset.pvLivePhase || '';
    if (turn <= 0 || !['command','switch','complete'].includes(phase)) return;
    const stage = surface.querySelector('.pv-wild-arena');
    const player = stage?.querySelector('[data-pv-fighter="player"]');
    const enemy = stage?.querySelector('[data-pv-fighter="enemy"]');
    if (!stage || !player || !enemy || !player.querySelector('img') || !enemy.querySelector('img')) return;

    let moveTypes = {};
    try { moveTypes = JSON.parse(document.getElementById('pv-live-battle-move-types')?.textContent || '{}'); } catch (_) { moveTypes = {}; }
    await wait(360);
    const resolvedTurn = turn;
    const playerName = surface.querySelector('.pv-wild-player .pv-wild-fighter-hud h2')?.textContent?.trim() || '';
    const enemyName = surface.querySelector('.pv-wild-enemy .pv-wild-fighter-hud h2')?.textContent?.trim() || '';
    const entries = Array.from(surface.querySelectorAll('.pv-wild-log > div'))
      .map(row => ({ turn:Number(row.dataset.pvLogTurn || -1), text:row.querySelector('span')?.textContent?.trim() || '' }))
      .filter(entry => entry.turn === resolvedTurn && entry.text)
      .reverse();
    let played = false;
    const playerLevel = Number(player.dataset.pvFighterLevel || 1);
    const enemyLevel = Number(enemy.dataset.pvFighterLevel || 1);
    const userSlot = Number(surface.dataset.pvLiveUserSlot || 1);
    const matchId = Number(surface.dataset.pvLiveMatchId || 0);
    const tieFirstSlot = ((matchId + resolvedTurn) % 2) + 1;
    let mirrorNext = playerLevel > enemyLevel ? player : (enemyLevel > playerLevel ? enemy : (userSlot === tieFirstSlot ? player : enemy));
    stage.classList.add('pv-fx-replay-armed');

    for (const entry of entries) {
      const text = entry.text;
      if (/ restored \d+ HP|cleared its status/i.test(text)) {
        if (playerName && text.startsWith(`${playerName} used `)) {
          const amount = Number(text.match(/restored (\d+) HP/i)?.[1] || 0);
          const item = text.match(/ used (.+?) and (?:restored|cleared)/i)?.[1] || 'RECOVERY';
          await playHeal(stage, player, {amount, label:item});
          played = true;
        } else if (enemyName && text.startsWith(`${enemyName} used `)) {
          const amount = Number(text.match(/restored (\d+) HP/i)?.[1] || 0);
          const item = text.match(/ used (.+?) and (?:restored|cleared)/i)?.[1] || 'RECOVERY';
          await playHeal(stage, enemy, {amount, label:item});
          played = true;
        }
        continue;
      }
      const switchMatch = text.match(/^(.+?) switched to (.+?)\.$/i);
      if (switchMatch) {
        const trainer = switchMatch[1].trim();
        const target = trainer === surface.dataset.pvLiveOwnTrainer ? player : (trainer === surface.dataset.pvLiveOpponentTrainer ? enemy : null);
        if (target) { await playSwitch(stage, target, {name:switchMatch[2].trim()}); played = true; }
        continue;
      }
      const moveMatch = text.match(/^(.+?) used (.+?)(?: and dealt (\d+) HP damage(?: \(([^)]+)\))?|, but the attack missed)\.?$/i);
      if (!moveMatch) continue;
      const attackerName = moveMatch[1].trim();
      const move = moveMatch[2].trim();
      const damage = Number(moveMatch[3] || 0);
      const hit = !/missed/i.test(text);
      const effectivenessLabel = String(moveMatch[4] || '');
      let effectiveness = 1;
      if (/ultra/i.test(effectivenessLabel)) effectiveness = 4;
      else if (/super/i.test(effectivenessLabel)) effectiveness = 2;
      else if (/not very|resist/i.test(effectivenessLabel)) effectiveness = .5;
      else if (/no effect|immune/i.test(effectivenessLabel)) effectiveness = 0;
      const strike = {move, type:moveTypes[move] || 'normal', hit, damage, effectiveness};
      if (playerName && attackerName === playerName && enemyName !== playerName) {
        await playStrike(stage, player, enemy, strike, 'player');
        await playFaint(stage, enemy);
        played = true;
      } else if (enemyName && attackerName === enemyName && playerName !== enemyName) {
        await playStrike(stage, enemy, player, strike, 'enemy');
        await playFaint(stage, player);
        played = true;
      } else {
        // Same-species mirrors are valid. Match the runtime's authoritative
        // initiative order (level first, deterministic match/turn tie-breaker)
        // instead of arbitrarily assigning the log line to the local player.
        const actor = mirrorNext;
        const target = actor === player ? enemy : player;
        await playStrike(stage, actor, target, strike, actor === player ? 'player' : 'enemy');
        await playFaint(stage, target);
        mirrorNext = target;
        played = true;
      }
    }
    if (!played) {
      player.classList.add('is-fx-switching');
      enemy.classList.add('is-fx-switching');
      await wait(580);
      player.classList.remove('is-fx-switching');
      enemy.classList.remove('is-fx-switching');
    }
    stage.classList.remove('pv-fx-replay-armed','is-fx-combat-live');
  };

  const start = () => {
    void playWildBattleFx();
    void playStandardBattleFx();
    void playLiveBattleFx();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
  else start();
})();

/* v25.0.0 — coherent Pokémon-world motion, Rival Network countdowns and card depth. */
(() => {
  'use strict';

  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

  const staticRoot = () => {
    const link = document.querySelector('link[href*="vortex-modern.css"]');
    if (!link?.href) return null;
    try { return new URL('../../html/static/', link.href); } catch (_) { return null; }
  };

  const decorateLegacyPages = () => {
    document.body?.classList.add('pv-pokemon-theme-v25');
    if (document.querySelector('.pv-global-spritefield')) return;
    const root = staticRoot();
    if (!root || !document.body) return;
    const field = document.createElement('div');
    field.className = 'pv-global-spritefield pv-global-spritefield-injected';
    field.setAttribute('aria-hidden', 'true');
    const pokemon = ['Pikachu','Eevee','Charizard','Gengar','Lucario','Mudkip','Treecko','Torchic','Snorlax','Dragonite'];
    pokemon.forEach((name) => {
      const img = document.createElement('img');
      img.alt = '';
      img.loading = 'lazy';
      img.src = new URL(`images/pokemon/${name}.gif`, root).href;
      field.appendChild(img);
    });
    [['ball-a','Poke Ball.png'],['ball-b','Great Ball.png'],['ball-c','Ultra Ball.png']].forEach(([cls,file]) => {
      const holder = document.createElement('i');
      holder.className = `pv-float-ball ${cls}`;
      const img = document.createElement('img');
      img.alt = '';
      img.loading = 'lazy';
      img.src = new URL(`images/items/${file}`, root).href;
      holder.appendChild(img);
      field.appendChild(holder);
    });
    document.body.prepend(field);
  };

  const formatCountdown = (remaining) => {
    remaining = Math.max(0, Math.floor(remaining));
    const hours = Math.floor(remaining / 3600);
    const minutes = Math.floor((remaining % 3600) / 60);
    const seconds = remaining % 60;
    if (hours > 0) return `${hours}h ${String(minutes).padStart(2,'0')}m`;
    return `${String(minutes).padStart(2,'0')}:${String(seconds).padStart(2,'0')}`;
  };

  const armCountdowns = () => {
    const nodes = Array.from(document.querySelectorAll('[data-pv-countdown]'));
    if (!nodes.length) return;
    const tick = () => {
      const now = Math.floor(Date.now() / 1000);
      let hasActive = false;
      nodes.forEach((node) => {
        const end = Number(node.dataset.pvCountdown || 0);
        if (!Number.isFinite(end) || end <= 0) return;
        const remaining = Math.max(0, end - now);
        if (remaining > 0) {
          hasActive = true;
          node.textContent = formatCountdown(remaining);
        } else {
          node.textContent = node.closest('.pv-retaliation-card') ? 'EXPIRED' : 'READY';
          node.classList.add('is-complete');
          const protectedBox = node.closest('.pv-rival-protected');
          if (protectedBox) protectedBox.classList.add('is-expired');
        }
      });
      if (!hasActive && timer) clearInterval(timer);
    };
    let timer = 0;
    tick();
    timer = window.setInterval(tick, 1000);
  };

  const armCardDepth = () => {
    if (reducedMotion || !window.matchMedia?.('(hover:hover) and (pointer:fine)').matches) return;
    const cards = document.querySelectorAll('.pv-rival-target,.pv-dashboard-feature,.pv-ai-zone-grid article');
    cards.forEach((card) => {
      card.addEventListener('pointermove', (event) => {
        const rect = card.getBoundingClientRect();
        const x = (event.clientX - rect.left) / Math.max(1, rect.width);
        const y = (event.clientY - rect.top) / Math.max(1, rect.height);
        card.style.setProperty('--tilt-y', `${((x - .5) * 4.5).toFixed(2)}deg`);
        card.style.setProperty('--tilt-x', `${((.5 - y) * 4.0).toFixed(2)}deg`);
      }, {passive:true});
      card.addEventListener('pointerleave', () => {
        card.style.setProperty('--tilt-y', '0deg');
        card.style.setProperty('--tilt-x', '0deg');
      }, {passive:true});
    });
  };

  const armReveal = () => {
    if (reducedMotion || !('IntersectionObserver' in window)) return;
    const items = document.querySelectorAll('.pv-rival-section,.pv-ranking-summary,.pv-rankings-panel,.pv-ai-kpis,.pv-ai-zone-strip,.pv-ai-feed-panel,.pv-ai-pressure-panel,.pv-dashboard-rival-card');
    items.forEach((item) => item.classList.add('pv-pokemon-reveal'));
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, {threshold:.08, rootMargin:'0px 0px -24px'});
    items.forEach((item) => observer.observe(item));
  };

  const start = () => {
    decorateLegacyPages();
    armCountdowns();
    armCardDepth();
    armReveal();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
  else start();
})();
