(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('allDataToggle');
    var dateInputs = Array.prototype.slice.call(document.querySelectorAll('.date-range-input'));
    if (!toggle) return;

    function updateDateInputs() {
      dateInputs.forEach(function (input) { input.disabled = toggle.checked; });
    }

    toggle.addEventListener('change', updateDateInputs);
    updateDateInputs();
  });
}());
