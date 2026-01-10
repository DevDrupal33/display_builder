/**
 * @file
 * Attaches behaviors for Drupal's Display Builder Toolbar.
 */
/* eslint no-use-before-define: 0 */

((Drupal, once) => {
  "use strict";

  Drupal.behaviors.displayBuilderToolbar = {
    attach(context) {
      once("dbToolbar", ".db-toolbar", context).forEach((toolbar) => {
        createStartToggle(toolbar);
        createEndToggle(toolbar);
        stickyObserver(toolbar);
      });
    },
  };

  const stickyObserver = (toolbar) => {
    const sentinel = document.querySelector(".db-toolbar-sentinel");
    if (!sentinel) return;

    const observer = new IntersectionObserver(([entry]) => {
      toolbar.classList.toggle("db-toolbar-is-sticky", !entry.isIntersecting);
    });

    observer.observe(sentinel);
  };

  function createStartToggle(toolbar) {
    if (toolbar.querySelector(".db-toolbar__start-toggle")) return;

    const start = toolbar.querySelector(".db-toolbar__start");
    if (!start) return;

    const toggle = document.createElement("sl-button");
    toggle.className = "db-toolbar__start-toggle";
    toggle.variant = "default";
    toggle.textContent = "⋯";

    toggle.addEventListener("click", (e) => {
      e.stopPropagation();
      start.classList.toggle("db-toolbar__start--open");
      closeEnd(toolbar);
    });

    document.addEventListener("click", (e) => {
      if (!start.contains(e.target) && !toggle.contains(e.target)) {
        start.classList.remove("db-toolbar__start--open");
      }
    });

    toolbar.prepend(toggle);
  }

  function createEndToggle(toolbar) {
    if (toolbar.querySelector(".db-toolbar__end-toggle")) return;

    const end = toolbar.querySelector(".db-toolbar__end");
    if (!end) return;

    const toggle = document.createElement("sl-button");
    toggle.className = "db-toolbar__end-toggle";
    toggle.variant = "default";
    toggle.textContent = "⋯";

    toggle.addEventListener("click", (e) => {
      e.stopPropagation();
      end.classList.toggle("db-toolbar__end--open");
      closeStart(toolbar);
    });

    document.addEventListener("click", (e) => {
      if (!end.contains(e.target) && !toggle.contains(e.target)) {
        end.classList.remove("db-toolbar__end--open");
      }
    });

    toolbar.appendChild(toggle);
  }

  function closeStart(toolbar) {
    const start = toolbar.querySelector(".db-toolbar__start");
    if (start) start.classList.remove("db-toolbar__start--open");
  }

  function closeEnd(toolbar) {
    const end = toolbar.querySelector(".db-toolbar__end");
    if (end) end.classList.remove("db-toolbar__end--open");
  }
})(Drupal, once);
