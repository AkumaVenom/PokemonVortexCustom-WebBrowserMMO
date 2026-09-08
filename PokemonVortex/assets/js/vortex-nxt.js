/* Pokemon Vortex NXT · 26.1.1 — presentation only.
 * Never submits, clones, intercepts, disables or rewrites gameplay controls.
 * The supplied Pokémon GIFs are static; restrained CSS motion animates them.
 */
(() => {
  'use strict';
  const body = document.body;
  if (!body || !body.dataset.nxtSection) return;
  const html = document.documentElement;
  const imageBase = body.dataset.nxtImages;
  if (!imageBase) return;
  const section = body.dataset.nxtSection;
  const route = body.dataset.nxtRoute;
  const sectionTeams = {
    world: ['Pikachu', 'Eevee'], adventure: ['Treecko', 'Eevee'],
    battle: ['Charizard', 'Lucario'], ranked: ['Dragonite', 'Garchomp'],
    collection: ['Pikachu', 'Bulbasaur'], trade: ['Meowth', 'Togepi'],
    community: ['Chatot', 'Gardevoir'], account: ['Pikachu', 'Eevee']
  };
  const routeTeams = {
    'map_select.php': ['Treecko', 'Mudkip'], 'map.php': ['Pikachu', 'Eevee'],
    'world_map.php': ['Treecko', 'Torchic'], 'sidequest.php': ['Pidgeot', 'Pikachu'],
    'battle_select.php': ['Charizard', 'Blastoise'], 'wildbattle.php': ['Gengar', 'Lucario'],
    'live_battle_arena.php': ['Infernape', 'Empoleon'], 'live_battle_result.php': ['Dragonite', 'Pikachu'],
    'event_center.php': ['Mew', 'Jirachi'], 'your_pokemon.php': ['Eevee', 'Dragonite'],
    'pokedex.php': ['Bulbasaur', 'Rotom'], 'change_team.php': ['Squirtle', 'Charmander'],
    'change_attacks.php': ['Alakazam', 'Lucario'], 'evolve.php': ['Eevee', 'Vaporeon'],
    'fossil_lab.php': ['Omanyte', 'Kabuto'], 'items.php': ['Meowth', 'Pikachu'],
    'trade.php': ['Abra', 'Machop'], 'make_an_offer.php': ['Kadabra', 'Machoke'],
    'put_up_for_trade.php': ['Haunter', 'Graveler'], 'view_offers.php': ['Alakazam', 'Gengar'],
    'community.php': ['Chatot', 'Togekiss'], 'members.php': ['Pikachu', 'Riolu'],
    'messages.php': ['Chatot', 'Delibird'], 'clans.php': ['Arcanine', 'Ninetales'],
    'your_account.php': ['Eevee', 'Pikachu'], 'options.php': ['Rotom', 'Porygon'],
    'about.php': ['Bulbasaur', 'Charmander', 'Squirtle'], 'news.php': ['Pikachu', 'Chatot'],
    'contactus.php': ['Chansey', 'Audino'], '404.php': ['Psyduck', 'Slowpoke'],
    'forgot_password.php': ['Abra', 'Pikachu'], 'credits.php': ['Jirachi', 'Celebi']
  };
  const team = routeTeams[route] || sectionTeams[section] || sectionTeams.world;

  const sprite = (name, size = 96) => {
    const img = document.createElement('img');
    img.src = `${imageBase}pokemon/${encodeURIComponent(name)}.gif`;
    img.alt = ''; img.width = size; img.height = size;
    img.loading = 'eager'; img.decoding = 'async';
    // This is only a decorative sprite. A missing asset should never repeat requests.
    img.addEventListener('error', () => img.remove(), { once:true });
    return img;
  };

  const decorateHead = (head) => {
    if (!head || head.dataset.nxtDecorated === '1' || !head.querySelector('h1')) return;
    head.dataset.nxtDecorated = '1';
    head.classList.add('nxt-section-head');
    // Existing live battle state indicators remain readable, without competing art.
    if (head.closest('.pv-live-runtime-page,.pv-wildbattle-page') || head.classList.contains('pv-map-head') || head.classList.contains('pv-combat-runtime-banner')) {
      head.classList.add('nxt-play-head');
      return;
    }
    const art = document.createElement('div');
    art.className = 'nxt-section-sprites'; art.setAttribute('aria-hidden', 'true');
    team.slice(0,2).forEach(name => art.appendChild(sprite(name)));
    head.appendChild(art);
  };
  document.querySelectorAll('.pv-page-head,.pv-section-hero,.pv-combat-runtime-banner').forEach(decorateHead);

  // Public information has a quieter, document-like layout and small companion art.
  const prose = document.querySelector('.pv-prose');
  if (prose && prose.querySelector('h1') && !prose.querySelector('.nxt-prose-team')) {
    const art = document.createElement('div');
    art.className = 'nxt-prose-team'; art.setAttribute('aria-hidden', 'true');
    team.forEach(name => art.appendChild(sprite(name,72)));
    prose.insertBefore(art, prose.firstChild);
  }
  document.querySelectorAll('.pv-empty-state').forEach(empty => {
    if (empty.querySelector('img') || empty.querySelector('.nxt-empty-sprite')) return;
    const img = sprite(section === 'collection' ? 'Eevee' : 'Snorlax',80);
    img.className = 'nxt-empty-sprite'; img.setAttribute('aria-hidden','true');
    img.loading = 'lazy'; empty.insertBefore(img,empty.firstChild);
  });

  // Decorate the independently assembled trainer-battle header without touching
  // the legacy form vault or its MutationObserver/command adapter.
  const combatHeader = document.querySelector('.pv-combat-topbar');
  if (combatHeader) {
    const mark = combatHeader.querySelector('.pv-brand-mark');
    if (mark && !mark.querySelector('img')) {
      mark.textContent = '';
      const ball = document.createElement('img');
      ball.src = `${imageBase}items/Poke%20Ball.png`; ball.alt=''; ball.width=32; ball.height=32;
      mark.appendChild(ball);
    }
    combatHeader.querySelectorAll('.pv-nav a.active').forEach(a => a.setAttribute('aria-current','page'));
  }
  // Existing battle markup can be replaced after a turn. Only its bounded banner
  // is decorated, and idempotence prevents mutation feedback loops.
  const ajax = document.getElementById('ajax');
  if (ajax && combatHeader && 'MutationObserver' in window) {
    const observer = new MutationObserver(() => decorateHead(ajax.querySelector('.pv-combat-runtime-banner')));
    observer.observe(ajax,{childList:true});
  }

  // Local visual preference only. Storage errors do not prevent any page action.
  const preferenceKey = 'pv_nxt_motion_paused';
  const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
  let userPaused = false;
  try { userPaused = localStorage.getItem(preferenceKey) === '1'; } catch (_) { /* optional */ }
  const controls = Array.from(document.querySelectorAll('[data-nxt-motion]'));
  if (!controls.length) {
    const host = document.querySelector('.pv-combat-footer') || document.querySelector('.pv-auth-links');
    if (host) {
      const control = document.createElement('button');
      control.type = 'button'; control.className='nxt-motion-toggle';
      control.setAttribute('data-nxt-motion','');
      host.appendChild(control);
      controls.push(control);
    }
  }
  const syncMotion = () => {
    const paused = userPaused || motionQuery.matches;
    html.classList.toggle('nxt-motion-paused',paused);
    controls.forEach(control => {
      control.hidden = false;
      control.setAttribute('aria-pressed',String(paused));
      control.textContent = motionQuery.matches ? 'Reduced motion' : paused ? 'Resume motion' : 'Pause motion';
      control.title = motionQuery.matches ? 'Your system preference keeps animation reduced.' : 'Pause or resume decorative animation on this device.';
      control.disabled = motionQuery.matches;
    });
  };
  controls.forEach(control => control.addEventListener('click',() => {
    userPaused = !userPaused;
    try { localStorage.setItem(preferenceKey,userPaused ? '1' : '0'); } catch (_) { /* optional */ }
    syncMotion();
  }));
  if (motionQuery.addEventListener) motionQuery.addEventListener('change',syncMotion);
  else if (motionQuery.addListener) motionQuery.addListener(syncMotion);
  const syncVisibility = () => html.classList.toggle('nxt-document-hidden',document.hidden);
  document.addEventListener('visibilitychange',syncVisibility);
  window.addEventListener('pageshow',syncVisibility);
  window.addEventListener('storage',event => {
    if (event.key !== preferenceKey) return;
    userPaused = event.newValue === '1'; syncMotion();
  });
  syncMotion(); syncVisibility();
})();
