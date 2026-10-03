/**
 * Lazy-load Chart.js (local first) only when a report/tab needs it.
 * Version: 20261002.08
 */
(function (w) {
  var loading = null;
  w.rcLoadChart = function () {
    if (w.Chart) return Promise.resolve(w.Chart);
    if (loading) return loading;
    loading = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = '/assets/vendor/chart.umd.min.js';
      s.async = true;
      s.onload = function () {
        if (w.Chart) resolve(w.Chart);
        else reject(new Error('Chart global missing'));
      };
      s.onerror = function () {
        // Last-resort CDN only if local file missing
        var s2 = document.createElement('script');
        s2.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js';
        s2.async = true;
        s2.onload = function () {
          if (w.Chart) resolve(w.Chart);
          else reject(new Error('Chart CDN failed'));
        };
        s2.onerror = function () { reject(new Error('Chart.js failed to load')); };
        document.head.appendChild(s2);
      };
      document.head.appendChild(s);
    });
    return loading;
  };
})(window);
