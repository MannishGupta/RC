/**
 * Lazy-load EasyQRCode (local first). Version: 20261002.11
 */
(function (w) {
  var loading = null;
  w.rcLoadQr = function () {
    if (w.EasyQRCode || w.QRCode) return Promise.resolve(w.EasyQRCode || w.QRCode);
    if (loading) return loading;
    loading = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = '/assets/vendor/easy.qrcode.min.js';
      s.async = true;
      s.onload = function () {
        resolve(w.EasyQRCode || w.QRCode || true);
      };
      s.onerror = function () {
        var s2 = document.createElement('script');
        s2.src = 'https://cdn.jsdelivr.net/npm/easyqrcodejs@4.6.2/dist/easy.qrcode.min.js';
        s2.async = true;
        s2.onload = function () { resolve(w.EasyQRCode || w.QRCode || true); };
        s2.onerror = function () { reject(new Error('QR library failed')); };
        document.head.appendChild(s2);
      };
      document.head.appendChild(s);
    });
    return loading;
  };
})(window);
