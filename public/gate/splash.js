// Fades out the HTML loading screen (index.html #splash) once Flutter has
// drawn its first frame. A separate file because the web image's CSP has no
// 'unsafe-inline' for scripts.
(function () {
  window.addEventListener('flutter-first-frame', function () {
    var splash = document.getElementById('splash');
    if (!splash) return;
    splash.classList.add('hide');
    setTimeout(function () { splash.remove(); }, 400);
  }, { once: true });
})();
