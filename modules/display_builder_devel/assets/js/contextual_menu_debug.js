/**
 * Debug plugin for ContextualMenu.
 * Displays instance, slot, and copy debug info in the menu if a <pre class="debug-info"><code></code></pre> is present.
 */
Drupal.displayBuilder = Drupal.displayBuilder || {};
Drupal.displayBuilder.ContextualMenuPlugin =
  Drupal.displayBuilder.ContextualMenuPlugin || {};

Drupal.displayBuilder.ContextualMenuPlugin.debug = {
  onMenuOpen(menuInstance, instance, slotData, copyInstance) {
    const code = menuInstance.menu.querySelector('pre.debug-info code');
    if (!code) return;
    let debugData = `<strong>Instance:</strong> ${JSON.stringify(instance, null, 2)}<br>`;
    if (slotData?.id) {
      debugData += `<strong>Slot Data:</strong> ${JSON.stringify(slotData, null, 2)}<br>`;
    }
    if (copyInstance?.id || copyInstance?.title) {
      debugData += `<strong>Copy Instance:</strong> ${JSON.stringify(copyInstance, null, 2)}<br>`;
    }
    code.innerHTML = debugData;
  },
};
