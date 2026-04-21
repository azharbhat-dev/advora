<?php
function advoraLogoFullSvg($height = 48) {
    return '<svg style="height:' . (int)$height . 'px;width:auto;display:block;" viewBox="0 0 680 160" role="img" xmlns="http://www.w3.org/2000/svg">
  <title>Advora logo</title>
  <desc>Advora brand logo with gold badge and white wordmark</desc>
  <defs>
    <linearGradient id="advoraBadge" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#FFD836"/>
      <stop offset="100%" stop-color="#F5A000"/>
    </linearGradient>
  </defs>
  <rect x="20" y="20" width="120" height="120" rx="28" fill="url(#advoraBadge)"/>
  <polygon points="80,36 116,134 44,134" fill="none" stroke="#1a1000" stroke-width="18" stroke-linejoin="round" stroke-linecap="round"/>
  <rect x="50" y="103" width="60" height="13" fill="url(#advoraBadge)"/>
  <polygon points="80,58 104,122 56,122" fill="url(#advoraBadge)"/>
  <text x="162" y="108" font-family="\'Google Sans\',\'Helvetica Neue\',Arial,sans-serif" font-size="72" font-weight="700" fill="#ffffff" letter-spacing="-1">A</text>
  <text x="215" y="108" font-family="\'Google Sans\',\'Helvetica Neue\',Arial,sans-serif" font-size="72" font-weight="300" fill="#ffffff" letter-spacing="-1">dvora</text>
</svg>';
}

function advoraLogoMarkOnly($size = 32) {
    $s = (int)$size;
    $id = 'advoraMark' . $s;
    return '<svg width="' . $s . '" height="' . $s . '" viewBox="20 20 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <defs>
    <linearGradient id="' . $id . '" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#FFD836"/>
      <stop offset="100%" stop-color="#F5A000"/>
    </linearGradient>
  </defs>
  <rect x="20" y="20" width="120" height="120" rx="28" fill="url(#' . $id . ')"/>
  <polygon points="80,36 116,134 44,134" fill="none" stroke="#1a1000" stroke-width="18" stroke-linejoin="round" stroke-linecap="round"/>
  <rect x="50" y="103" width="60" height="13" fill="url(#' . $id . ')"/>
  <polygon points="80,58 104,122 56,122" fill="url(#' . $id . ')"/>
</svg>';
}