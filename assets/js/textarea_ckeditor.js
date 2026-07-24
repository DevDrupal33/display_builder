/**
 * @file
 * Attaches a standalone CKEditor 5 instance to Display Builder's plain
 * textarea sources.
 *
 * This intentionally bypasses Drupal core's editor.module / #type =>
 * 'text_format' integration (format selector, Drupal.editors attach
 * system): that integration only syncs its <textarea> back to the DOM on
 * native form submit, which HTMX never triggers (it always
 * preventDefault()s and serializes the form itself). Instead, this
 * instantiates CKEditor 5 directly from its plain core/ckeditor5.* DLL
 * builds and keeps the <textarea> synced itself, so HTMX just reads a
 * <textarea> that is always current - no core patch, no format selector,
 * no editor.module JS.
 *
 * No Drupal.behaviors either, on either end of the lifecycle: Display
 * Builder only calls Drupal.attachBehaviors() from its own custom events
 * (htmx:oobAfterSwap, htmx:drupal:load - @see
 * components/display_builder/js/display_builder.js), and that dispatch has
 * been observed live to simply not fire for some content (a contextual form
 * panel restored into a fresh page load, notably) - core's normal
 * DOMContentLoaded pass doesn't cover it either. A plain MutationObserver
 * doesn't depend on any of that wiring; it notices textareas appearing (or
 * being removed) directly.
 */
((Drupal, once) => {
  const editors = new WeakMap();
  const hideObservers = new WeakMap();

  /**
   * Keeps a CKEditor 5-attached <textarea> hidden.
   *
   * CKEditor 5's ElementReplacer hides the source <textarea> once, at
   * create() time. Some later DOM update in this app's HTMX-swapped
   * contextual form panel can reset that inline style - observed live: the
   * <textarea> reappears stacked below the editor while the editor itself
   * keeps working - so this re-applies the hide on every style mutation
   * instead of trusting the one-shot hide to stick.
   *
   * @param {HTMLTextAreaElement} textarea
   *   The CKEditor-attached textarea to keep hidden.
   *
   * @return {MutationObserver}
   *   The observer, so it can be disconnected on detach.
   */
  function keepHidden(textarea) {
    const enforce = () => {
      if (textarea.style.display !== 'none') {
        textarea.style.display = 'none';
      }
    };
    enforce();
    const observer = new MutationObserver(enforce);
    observer.observe(textarea, {
      attributes: true,
      attributeFilter: ['style'],
    });
    return observer;
  }

  /**
   * Checks whether every CKEditor 5 DLL bundle this config needs is loaded.
   *
   * @return {boolean}
   *   TRUE once window.CKEditor5's plugin namespaces are all populated.
   */
  function ckeditor5Ready() {
    const { CKEditor5 } = window;
    return !!(
      CKEditor5 &&
      CKEditor5.editorClassic &&
      CKEditor5.essentials &&
      CKEditor5.paragraph &&
      CKEditor5.heading &&
      CKEditor5.basicStyles &&
      CKEditor5.link &&
      CKEditor5.list &&
      CKEditor5.blockQuote &&
      CKEditor5.htmlSupport &&
      CKEditor5.sourceEditing &&
      CKEditor5.showBlocks
    );
  }

  /**
   * Resolves once the CKEditor 5 DLL bundles are loaded.
   *
   * On a cold page load - e.g. reloading straight into a restored
   * contextual form panel - Drupal.attachBehaviors() can run before this
   * library's own dependencies (core/ckeditor5.* DLL builds) have finished
   * executing: the <script> tags are present but not all evaluated yet, so
   * window.CKEditor5's plugin namespaces aren't populated. Reading them
   * then either silently produces a broken config or throws synchronously
   * (bypassing the .catch() below, since that throw happens while building
   * arguments to .create(), before any promise exists). once() has already
   * claimed the element by the time attach() runs, so there's no second
   * Drupal.attachBehaviors() pass to fall back on - polling here is what
   * makes the very first attach reliable instead of only working after a
   * subsequent HTMX round-trip (e.g. clicking Update) has fully warmed
   * these scripts.
   *
   * @param {number} timeout
   *   Max time to wait, in milliseconds.
   * @param {number} interval
   *   Poll interval, in milliseconds.
   *
   * @return {Promise<void>}
   *   Resolves when ready, rejects if the timeout is reached first.
   */
  function waitForCkeditor5(timeout = 5000, interval = 50) {
    return new Promise((resolve, reject) => {
      if (ckeditor5Ready()) {
        resolve();
        return;
      }
      const start = Date.now();
      const id = setInterval(() => {
        if (ckeditor5Ready()) {
          clearInterval(id);
          resolve();
        } else if (Date.now() - start > timeout) {
          clearInterval(id);
          reject(new Error('CKEditor 5 did not finish loading in time'));
        }
      }, interval);
    });
  }

  /**
   * Checks whether the optional ui_icons_ckeditor5 plugin bundle is loaded.
   *
   * @return {boolean}
   *   TRUE once window.CKEditor5.icon.Icon is populated.
   */
  function iconReady() {
    const { CKEditor5 } = window;
    return !!(CKEditor5 && CKEditor5.icon && CKEditor5.icon.Icon);
  }

  /**
   * Best-effort wait for the ui_icons_ckeditor5 bundle before create().
   *
   * That bundle (js/build/icon.js) is a separate library from the core
   * CKEditor 5 DLL builds waitForCkeditor5() covers, so on a cold HTMX swap
   * the editor can otherwise be created while window.CKEditor5.icon is still
   * undefined - buildConfig()'s presence check then skips the Icon plugin and
   * <drupal-icon> markup is silently dropped. Only called for instances the
   * widget flagged with data-db-ckeditor-icon (i.e. the module is enabled).
   * Unlike waitForCkeditor5() this never rejects: if the bundle genuinely
   * never lands the editor must still boot without icon support rather than
   * fail entirely, so the timeout just resolves.
   *
   * @param {number} timeout
   *   Max time to wait, in milliseconds.
   * @param {number} interval
   *   Poll interval, in milliseconds.
   *
   * @return {Promise<void>}
   *   Resolves once the bundle is ready or the timeout elapses.
   */
  function waitForIcon(timeout = 5000, interval = 50) {
    return new Promise((resolve) => {
      if (iconReady()) {
        resolve();
        return;
      }
      const start = Date.now();
      const id = setInterval(() => {
        if (iconReady() || Date.now() - start > timeout) {
          clearInterval(id);
          resolve();
        }
      }, interval);
    });
  }

  /**
   * Basic CKEditor 5 config, matching the 'display_builder_html' text
   * format's allowed tags (a, em, strong, blockquote, ul, ol, li, h2-h6).
   *
   * @return {object}
   *   The CKEditor 5 editor configuration.
   */
  function buildConfig() {
    const { CKEditor5 } = window;
    const plugins = [
      CKEditor5.basicStyles.Bold,
      CKEditor5.basicStyles.Italic,
      CKEditor5.essentials.Essentials,
      CKEditor5.heading.Heading,
      CKEditor5.htmlSupport.GeneralHtmlSupport,
      CKEditor5.link.Link,
      CKEditor5.list.List,
      CKEditor5.paragraph.Paragraph,
      CKEditor5.showBlocks.ShowBlocks,
      CKEditor5.sourceEditing.SourceEditing,
    ];
    // ui_icons integration. When the ui_icons_ckeditor5 plugin bundle is on
    // the page (its library is attached by the widget only when that module
    // is enabled), load its Icon plugin. We deliberately pass NO `icon`
    // editor config below, so IconUi.init() early-returns and adds no toolbar
    // button, while IconEditing still registers the `drupalIcon` model
    // element, its upcast/downcast converters and the live preview. That
    // registration - not GeneralHtmlSupport - is what preserves a pasted
    // <drupal-icon> across source<->WYSIWYG round-trips: <drupal-icon> is a
    // custom tag absent from CKEditor's DataSchema element registry, so a GHS
    // `allow` rule matches nothing and the element is dropped when leaving
    // source mode. Rendering the preserved tag to a real icon on output still
    // requires the ui_icons_text (icon_embed) filter on the
    // 'display_builder_html' format.
    if (CKEditor5.icon && CKEditor5.icon.Icon) {
      plugins.push(CKEditor5.icon.Icon);
    }
    return {
      // Required since CKEditor 5 v44: the open-source distribution used
      // here (core/ckeditor5.* DLL builds) is GPL-licensed, and CKEditor
      // now refuses to boot without this being set explicitly.
      // @see \Drupal\ckeditor5\Plugin\Editor\CKEditor5::getJSSettings()
      licenseKey: 'GPL',
      plugins,
      toolbar: [
        {
          label: Drupal.t('Text'),
          icon: false,
          items: ['heading', 'bold', 'italic'],
        },
        '|',
        'link',
        '|',
        {
          label: Drupal.t('Lists'),
          icon: false,
          items: ['bulletedList', 'numberedList'],
        },
        '|',
        'showBlocks',
        'sourceEditing',
      ],
      heading: {
        options: [
          {
            model: 'paragraph',
            title: Drupal.t('Paragraph'),
            class: 'ck-heading_paragraph',
          },
          {
            model: 'heading2',
            view: 'h2',
            title: Drupal.t('Heading 2'),
            class: 'ck-heading_heading2',
          },
          {
            model: 'heading3',
            view: 'h3',
            title: Drupal.t('Heading 3'),
            class: 'ck-heading_heading3',
          },
          {
            model: 'heading4',
            view: 'h4',
            title: Drupal.t('Heading 4'),
            class: 'ck-heading_heading4',
          },
          {
            model: 'heading5',
            view: 'h5',
            title: Drupal.t('Heading 5'),
            class: 'ck-heading_heading5',
          },
          {
            model: 'heading6',
            view: 'h6',
            title: Drupal.t('Heading 6'),
            class: 'ck-heading_heading6',
          },
        ],
      },
      link: {
        defaultProtocol: 'https://',
      },
    };
  }

  /**
   * Creates and wires a CKEditor 5 instance onto one textarea.
   *
   * @param {HTMLTextAreaElement} textarea
   *   The textarea to attach CKEditor 5 to.
   */
  function attachEditor(textarea) {
    waitForCkeditor5()
      .then(() =>
        // Instances the widget flagged expect the optional ui_icons_ckeditor5
        // bundle; wait for it (best effort) so buildConfig() sees the Icon
        // plugin, otherwise create straight away.
        textarea.hasAttribute('data-db-ckeditor-icon')
          ? waitForIcon()
          : undefined,
      )
      .then(() =>
        window.CKEditor5.editorClassic.ClassicEditor.create(
          textarea,
          buildConfig(),
        ),
      )
      .then((editor) => {
        editors.set(textarea, editor);
        hideObservers.set(textarea, keepHidden(textarea));
        // Cheap continuous safety-net sync: keeps the hidden
        // <textarea> current for anything that reads it directly.
        editor.model.document.on('change:data', () => {
          editor.updateSourceElement();
        });
        // Native <textarea> only fires 'change' on blur, and the
        // HTMX autosave request is wired to listen for exactly that
        // (@see \Drupal\display_builder\HtmxEvents::onInstanceFormChange).
        // CKEditor hides the real <textarea>, so the browser never
        // fires it - dispatch it ourselves once focus leaves the
        // editor.
        editor.ui.focusTracker.on(
          'change:isFocused',
          (evt, name, isFocused) => {
            if (isFocused) {
              return;
            }
            editor.updateSourceElement();
            textarea.dispatchEvent(new Event('change', { bubbles: true }));
          },
        );
      })
      // eslint-disable-next-line no-console
      .catch((error) =>
        console.error('CKEditor 5 failed to initialize', error),
      );
  }

  /**
   * Tears down a CKEditor 5 instance and its hide-enforcing observer.
   *
   * @param {HTMLTextAreaElement} textarea
   *   The textarea whose CKEditor 5 instance should be destroyed.
   */
  function detachEditor(textarea) {
    const editor = editors.get(textarea);
    if (editor) {
      editors.delete(textarea);
      editor.destroy();
    }
    const observer = hideObservers.get(textarea);
    if (observer) {
      hideObservers.delete(textarea);
      observer.disconnect();
    }
  }

  /**
   * Attaches CKEditor 5 to every not-yet-claimed textarea under root.
   *
   * @param {Element|Document} root
   *   The element (or document) to scan.
   */
  function scan(root) {
    once('dbTextareaCkeditor', '[data-db-ckeditor]', root).forEach(
      attachEditor,
    );
  }

  /**
   * Collects [data-db-ckeditor] textareas within a node, inclusive.
   *
   * @param {Node} node
   *   A node from a MutationRecord's addedNodes or removedNodes list.
   *
   * @return {HTMLTextAreaElement[]}
   *   The matching textareas, if any.
   */
  function collectTextareas(node) {
    if (node.nodeType !== Node.ELEMENT_NODE) {
      return [];
    }
    const nested = Array.from(node.querySelectorAll('[data-db-ckeditor]'));
    return node.matches('[data-db-ckeditor]') ? [node, ...nested] : nested;
  }

  // Attach on anything already present when this script runs (e.g. a
  // contextual form panel restored on a fresh page load), and again on
  // every subsequent DOM change (an HTMX swap, most of the time) - scoped
  // to each mutation's own addedNodes rather than a fresh document-wide
  // scan(document), since that would redo a full-page querySelectorAll on
  // every unrelated mutation anywhere in the page. once() inside scan()
  // makes the redundancy between the initial scan and this one, and any
  // given editor already having been attached, harmless. Detach
  // symmetrically: a swap that removes a claimed textarea is exactly when
  // its CKEditor 5 instance needs destroying, so there's no need for a
  // second, separate removal-watching mechanism. once.remove() releases
  // the once() marker too - without it, a DOM node reused for a different
  // field by a later morph would keep looking "already claimed" and
  // never get its own fresh CKEditor 5 instance.
  scan(document);
  new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.removedNodes.forEach((node) => {
        collectTextareas(node).forEach((textarea) => {
          once.remove('dbTextareaCkeditor', textarea);
          detachEditor(textarea);
        });
      });
      mutation.addedNodes.forEach((node) => {
        once('dbTextareaCkeditor', collectTextareas(node)).forEach(
          attachEditor,
        );
      });
    });
  }).observe(document.body, {
    childList: true,
    subtree: true,
  });
})(Drupal, once);
