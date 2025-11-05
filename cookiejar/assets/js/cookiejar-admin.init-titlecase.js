$(function(){
  const $footLink = $('#cookiejar-footer-primary-link');
  if ($footLink.length) {
    $footLink.text(titleCaseExceptAnd($footLink.text()));
  }
});