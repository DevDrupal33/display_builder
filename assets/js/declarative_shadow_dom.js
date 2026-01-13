function attachShadowRoots(root) {
  root.querySelectorAll('template').forEach((template) => {
    // See \Drupal\display_builder\Render\DeclarativeShadowDomRenderer
    const shadowRoot = template.parentNode.attachShadow({ mode: 'open' });
    shadowRoot.appendChild(template.content);
    template.remove();
    attachShadowRoots(shadowRoot);
  });
}

document.addEventListener('htmx:load', (event) => {
  if (event.detail.elt.nodeName === 'DB-ISOLATE') {
    attachShadowRoots(event.detail.elt);
  }
});
