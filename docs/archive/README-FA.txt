Status now loads Font Awesome from the main app vendor (no CDN):

  /assets/vendor/fontawesome.min.css
  /assets/vendor/webfonts/*.woff2

Ensure those files exist on the host (same as dashboard). Do not delete CDN-only
webfonts under status/ — they are unused after this change.
