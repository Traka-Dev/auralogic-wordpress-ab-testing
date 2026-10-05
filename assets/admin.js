(() => {
  'use strict';
  const search = document.getElementById('bat-search');
  const filter = document.getElementById('bat-filter');
  const cards = Array.from(document.querySelectorAll('.bat-experiment'));
  const normalize = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
  const filterCards = () => {
    const term = normalize(search?.value.trim() || '');
    let visible = 0;
    for (const card of cards) {
      card.hidden = !normalize(card.dataset.batName).includes(term) || (filter?.value !== 'all' && card.dataset.batStatus !== filter?.value);
      if (!card.hidden) visible++;
    }
    const empty = document.getElementById('bat-no-results');
    if (empty) empty.hidden = visible > 0;
  };
  search?.addEventListener('input', filterCards);
  filter?.addEventListener('change', filterCards);
  const mode = document.getElementById('bat-mode');
  const goal = document.getElementById('bat-goal');
  if (!mode || !goal) return;
  const show = (id, visible) => { document.getElementById(id)?.closest('tr')?.toggleAttribute('hidden', !visible); };
  const update = () => {
    show('bat-a', mode.value === 'pages');
    show('bat-b', mode.value === 'pages');
    show('bat-thank_you', goal.value === 'page');
    show('bat-selector', goal.value === 'click');
    const builderId = document.getElementById('bat-builder-id-row');
    if (builderId) builderId.hidden = mode.value !== 'elements';
    const builderHelp = document.querySelector('.bat-builder-help');
    if (builderHelp) builderHelp.hidden = mode.value !== 'elements';
    const label = document.querySelector('label[for="bat-entry"]');
    if (label) label.textContent = mode.value === 'elements' ? 'Página del experimento' : 'Página de entrada';
    for (const id of ['bat-entry', 'bat-a', 'bat-b', 'bat-thank_you', 'bat-selector']) {
      const input = document.getElementById(id);
      if (input) input.required = !input.closest('tr').hidden;
    }
  };
  mode.addEventListener('change', update);
  goal.addEventListener('change', update);
  update();
})();
