// Helper: Capitalize first character of each word, except the word "and"
function titleCaseExceptAnd(str){
  return (str || '').split(' ').map(w => {
    const wl = w.toLowerCase();
    if (wl === 'and') return 'and';
    if (w === '&') return '&';              // keep ampersand symbol
    if (!w) return w;
    // Capitalize only the first character, leave the rest unchanged
    return w.charAt(0).toUpperCase() + w.slice(1);
  }).join(' ');
}

// ... inside applyPage(slug) after you compute `page`:
const $footLink = $('#cookiejar-footer-primary-link');
if ($footLink.length){
  const explain = page.explain || '';
  $footLink.text(titleCaseExceptAnd(explain));
}