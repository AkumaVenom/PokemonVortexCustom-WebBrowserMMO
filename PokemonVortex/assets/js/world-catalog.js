(() => {
  'use strict';
  const input = document.getElementById('pv-region-search');
  const results = document.getElementById('pv-region-area-results');
  const count = document.getElementById('pv-region-search-count');
  if (!input || !results || !count) return;
  const normalize = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[-—–]/g, ' ');
  const groups = Array.from(results.querySelectorAll('.pv-map-group')).map(element => ({
    element,
    cards: Array.from(element.querySelectorAll('.pv-map-card')).map(card => ({
      element: card, text: normalize(card.textContent),
    })),
  }));
  const total = groups.reduce((n, group) => n + group.cards.length, 0);
  input.closest('.pv-region-search').hidden = false;
  input.addEventListener('input', () => {
    const words = normalize(input.value).trim().split(/\s+/).filter(Boolean);
    let visible = 0;
    groups.forEach(group => {
      let matching = 0;
      group.cards.forEach(card => {
        const match = words.every(word => card.text.includes(word));
        card.element.hidden = !match;
        if (match) matching++;
      });
      group.element.hidden = matching === 0;
      visible += matching;
    });
    count.textContent = visible === 0 ? 'No matching areas. Try another name.' : `${visible} of ${total} areas`;
  });
})();
