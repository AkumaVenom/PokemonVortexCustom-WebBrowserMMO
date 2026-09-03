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
  banner.innerHTML = `<div><span class="pv-eyebrow">BATTLE NETWORK // ${isLive ? 'SYNCHRONIZED PVP' : 'AUTHORITATIVE NPC COMBAT'}</span><h1>${isLive ? 'Live Battle' : 'Trainer Battle'}</h1><p>${isLive ? 'Both trainers resolve against the same durable match identity. Terminal results automatically converge across both browser sessions.' : 'League, Event, Sidequest and trainer commands are validated server-side before the combat engine resolves the turn.'}</p></div><div class="pv-combat-runtime-signal"><i></i><strong>COMBAT LINK</strong><span>STABLE</span></div>`;

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
    let display = heading;
    if (playerSide) display = display.replace(/^Your\s+/i, '');
    return { heading, display, src: img?.getAttribute('src') || '', level: levelMatch ? Number(levelMatch[1]) : 1, current, max, pct };
  };
  const fighterCard = (fighter, enemy) => {
    const article = make('article', `pv-wild-fighter ${enemy ? 'pv-wild-enemy' : 'pv-wild-player'} pv-combat-fighter-card`);
    const hud = `<div class="pv-wild-fighter-hud"><div><small>${enemy ? (isLive ? 'OPPOSING TRAINER' : 'NPC TARGET') : 'ACTIVE PARTNER'}</small><h2>${esc(fighter.display)}</h2><span>Lv. ${fighter.level} · ${enemy ? (isLive ? 'Live opponent' : 'Trainer battle') : trainer}</span></div><strong>${fighter.max > 0 ? `${fighter.current} / ${fighter.max} HP` : `${fighter.current} HP`}</strong></div><div class="pv-wild-hp"><i style="width:${fighter.pct}%"></i></div>`;
    const sprite = `<div class="pv-wild-sprite-zone"><span class="pv-wild-scan-ring"></span><img src="${esc(fighter.src)}" alt="${esc(fighter.display)}"></div>`;
    article.innerHTML = enemy ? `${hud}${sprite}` : `${sprite}${hud}`;
    return article;
  };
  const buildArena = (battleTable) => {
    const rows = Array.from(battleTable?.rows || []);
    if (rows.length < 2 || rows[0].cells.length < 2 || rows[1].cells.length < 2) return null;
    const player = parseFighter(rows[0].cells[0], rows[1].cells[0], true);
    const enemy = parseFighter(rows[0].cells[1], rows[1].cells[1], false);
    if (!player.src || !enemy.src) return null;
    const arena = make('div', 'pv-wild-arena pv-combat-modern-arena');
    arena.appendChild(fighterCard(enemy, true));
    const vs = make('div', 'pv-wild-versus', '<span>VS</span><i></i>');
    arena.appendChild(vs);
    arena.appendChild(fighterCard(player, false));
    return { arena, rows, player, enemy };
  };
  const logFromTable = (rows) => {
    const entries = [];
    const sharedEvent = isLive ? normalize(ajax.querySelector('[data-pv-live-sync-event]')?.textContent || '') : '';
    if (sharedEvent) entries.push(sharedEvent);
    rows.slice(2).forEach(row => {
      if (row.querySelector('input[name="attack"],input[name="item"],input[type="submit"]')) return;
      const text = normalize(row.textContent);
      if (!text || /Select an attack|Attacks:/i.test(text)) return;
      if (!entries.includes(text)) entries.push(text);
    });
    return entries.slice(-8);
  };
  const buildLogPanel = (entries) => {
    const panel = make('section', 'pv-wild-log-panel');
    panel.innerHTML = '<div class="pv-map-panel-label">BATTLE TELEMETRY</div>';
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
        btn.innerHTML = `<small>MOVE SLOT ${esc(slot)}</small><strong>${esc(name)}</strong><span>SERVER-AUTHORITATIVE COMMAND</span>`;
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
  const vaultLegacy = (keep = []) => {
    const vault = make('div', 'pv-combat-legacy-vault');
    const keepSet = new Set([banner, ...keep]);
    Array.from(ajax.childNodes).forEach(node => { if (!keepSet.has(node)) vault.appendChild(node); });
    ajax.appendChild(vault);
    return vault;
  };

  const upgradeActiveBattle = () => {
    const attackInput = ajax.querySelector('input[name="attack"]');
    const attackForm = attackInput?.form;
    if (!attackForm) return false;
    const battleTable = directTable(attackForm);
    const arenaData = buildArena(battleTable);
    if (!arenaData) return false;
    const surface = make('div', 'pv-combat-modern-surface');
    surface.appendChild(arenaData.arena);
    const lower = make('div', 'pv-wild-lower-grid');
    lower.appendChild(buildLogPanel(logFromTable(arenaData.rows)));
    const controls = buildControlPanel(attackForm);
    if (controls) lower.appendChild(controls);
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
    const head = make('div', 'pv-section-heading');
    head.innerHTML = `<div><span>BATTLE COMMAND</span><h2>Choose your next Pokémon</h2></div><small>${isLive ? 'LIVE PVP' : 'TRAINER BATTLE'}</small>`;
    surface.appendChild(head);
    const grid = make('div', 'pv-wild-team-grid pv-combat-team-choice-grid');
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
    surface.appendChild(grid);
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
