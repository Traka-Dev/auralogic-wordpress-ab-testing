(() => {
  const mode = document.getElementById('bat-mode');
  const goal = document.getElementById('bat-goal');
  if (!mode || !goal) return;
  const show = (id, visible) => { document.getElementById(id)?.closest('tr')?.toggleAttribute('hidden', !visible); };
  const update = () => {
    show('bat-a', mode.value === 'pages');
    show('bat-b', mode.value === 'pages');
    show('bat-thank_you', goal.value === 'page');
    show('bat-selector', goal.value === 'click');
    const label = document.querySelector('label[for="bat-entry"]');
    if (label) label.textContent = mode.value === 'elements' ? 'Página del experimento' : 'Página de entrada';
  };
  mode.addEventListener('change', update);
  goal.addEventListener('change', update);
  update();
})();
