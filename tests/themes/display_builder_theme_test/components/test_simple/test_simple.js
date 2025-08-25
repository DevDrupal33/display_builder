/* eslint func-names: 0 */
document.addEventListener('DOMContentLoaded', function () {
  document
    .querySelectorAll('.test_simple .test_change_color')
    .forEach(function (btn) {
      btn.addEventListener('click', function () {
        const container = btn.closest('.test_simple');
        if (container) {
          // Generate a random hex color
          const randomColor = `#${Math.floor(Math.random() * 16777215)
            .toString(16)
            .padStart(6, '0')}`;
          container.style.backgroundColor = randomColor;
        }
      });
    });
});
