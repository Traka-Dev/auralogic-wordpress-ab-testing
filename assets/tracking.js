(() => {
  'use strict';
  const config = window.BAT_CONFIG;
  if (!config || /bot|crawler|spider|headless/i.test(navigator.userAgent)) return;
  const running = new Map();
  const cleanups = new Map();
  const key = (experiment) => `bat:${experiment.id}:${experiment.revision}`;
  const identity = (value) => {
    const url = new URL(value, location.href);
    const ids = ['page_id', 'p', 'pagename'].filter((name) => url.searchParams.has(name));
    return `${url.origin}${url.pathname.replace(/\/+$/, '')}?${ids.map((name) => `${name}=${url.searchParams.get(name)}`).join('&')}`;
  };
  const matches = (url) => Boolean(url) && identity(url) === identity(location.href);
  const allowed = (experiment) => !experiment.consent || window.BAT_CONSENT === true;
  const read = (experiment) => {
    try {
      const data = JSON.parse(localStorage.getItem(key(experiment)) || 'null');
      return data && data.expires * 1000 > Date.now() ? data : null;
    } catch { return null; }
  };
  const post = async (route, data) => {
    const response = await fetch(config.endpoint + route, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store', keepalive: true,
      headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data),
    });
    if (!response.ok) throw new Error(`A/B request failed: ${response.status}`);
    return response.json();
  };
  const nodesFor = (experiment) => Array.from(document.querySelectorAll(`[data-bat-experiment="${Number(experiment.id)}"][data-bat-variant]`));
  const display = (nodes, variant) => {
    for (const node of nodes) node.hidden = node.dataset.batVariant !== variant;
  };
  const measure = (experiment, event, nodes = null) => {
    let exposure;
    let observer;
    const expose = () => {
      if (document.visibilityState === 'hidden') return Promise.resolve();
      if (!exposure) exposure = event('exposure').catch((error) => { exposure = null; throw error; });
      return exposure;
    };
    const attempt = () => {
      if (document.visibilityState === 'hidden') return;
      if (nodes && !nodes.some((node) => {
        const rect = node.getBoundingClientRect();
        return !node.hidden && rect.width > 0 && rect.height > 0 && rect.bottom > 0 && rect.top < window.innerHeight && rect.right > 0 && rect.left < window.innerWidth;
      })) return;
      void expose().catch(() => {});
    };
    if (nodes && typeof IntersectionObserver !== 'undefined') {
      observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting && !entry.target.hidden)) attempt();
      });
      nodes.forEach((node) => observer.observe(node));
    } else if (nodes) {
      window.addEventListener('scroll', attempt, { passive: true });
      window.addEventListener('resize', attempt);
    }
    attempt();
    document.addEventListener('visibilitychange', attempt);
    const click = async (action) => {
      if (!(action.target instanceof Element) || !allowed(experiment)) return;
      if (nodes && !nodes.some((node) => !node.hidden && node.contains(action.target))) return;
      if (!action.target.closest(experiment.selector)) return;
      try { await expose(); await event('conversion'); } catch { /* Never block the user's click. */ }
    };
    if (experiment.goal === 'click' && experiment.selector) {
      try { document.querySelector(experiment.selector); document.addEventListener('click', click, true); } catch { /* Invalid selectors do not affect content. */ }
    }
    cleanups.set(experiment.id, () => {
      if (observer) observer.disconnect();
      document.removeEventListener('visibilitychange', attempt);
      document.removeEventListener('click', click, true);
      if (nodes) {
        window.removeEventListener('scroll', attempt);
        window.removeEventListener('resize', attempt);
      }
    });
  };
  const run = async (experiment) => {
    if (running.has(experiment.id) || !allowed(experiment)) return;
    const ticket = Symbol();
    running.set(experiment.id, ticket);
    try {
      const probe = `${key(experiment)}:probe`;
      localStorage.setItem(probe, '1'); localStorage.removeItem(probe);
      let assignment = read(experiment);
      const elements = experiment.mode === 'elements';
      let nodes;
      if (matches(experiment.entry)) {
        if (elements) {
          nodes = nodesFor(experiment);
          if (!['a', 'b'].every((variant) => nodes.some((node) => node.dataset.batVariant === variant))) return;
          // Nested variant wrappers can hide the selected child; fail back to the server's baseline.
          if (nodes.some((node) => node.parentElement?.closest(`[data-bat-experiment="${Number(experiment.id)}"][data-bat-variant]`))) return;
        }
        assignment = await post('assign', { id: experiment.id, token: assignment?.token || '' });
        if (!allowed(experiment) || running.get(experiment.id) !== ticket) return;
        localStorage.setItem(key(experiment), JSON.stringify(assignment));
        if (elements) {
          display(nodes, assignment.variant);
          window.dispatchEvent(new CustomEvent('bat:variant-ready', { detail: { id: experiment.id, variant: assignment.variant } }));
          window.dispatchEvent(new Event('resize'));
        } else {
          const destination = new URL(assignment.url, location.href);
          if (destination.origin !== location.origin || identity(destination.href) === identity(location.href)) return;
          for (const [name, value] of new URL(location.href).searchParams) {
            if (/^(utm_[a-z_]+|gclid|fbclid|msclkid)$/.test(name)) destination.searchParams.set(name, value);
          }
          location.replace(destination.href);
          return;
        }
      }
      if (!assignment) return;
      const event = (type) => {
        if (!allowed(experiment) || read(experiment)?.token !== assignment.token || running.get(experiment.id) !== ticket) return Promise.resolve();
        return post('event', { id: experiment.id, token: assignment.token, type, page: location.href });
      };
      if (elements && nodes) {
        measure(experiment, event, nodes.filter((node) => node.dataset.batVariant === assignment.variant));
      } else if (!elements && matches(experiment[assignment.variant])) {
        measure(experiment, event);
      } else if (experiment.goal === 'page' && matches(experiment.thankYou)) {
        await event('conversion');
      }
    } catch { /* REST/storage failures leave the original page usable. */ }
  };
  const start = () => config.experiments.forEach((experiment) => { void run(experiment); });
  const revoke = () => {
    if (window.BAT_CONSENT !== false) { start(); return; }
    for (const experiment of config.experiments) {
      if (!experiment.consent) continue;
      try { localStorage.removeItem(key(experiment)); } catch { /* Storage may be unavailable. */ }
      cleanups.get(experiment.id)?.();
      cleanups.delete(experiment.id);
      running.delete(experiment.id);
      if (experiment.mode === 'elements') display(nodesFor(experiment), 'a');
    }
  };
  window.addEventListener('bat:consent', revoke);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  else start();
})();
