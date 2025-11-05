jQuery(function($){
  function loadStats(){
    $.get(ajaxurl,{action:'dwic_stats'})
      .done(function(data){
        if(!data || typeof data !== 'object'){
          $('#dwic-map-wrap').html('<div><strong>Stats:</strong> Unavailable</div>');
          return;
        }
        $('#dwic-map-wrap').html(
          '<div><strong>Totals:</strong> Full '+(data.full||0)+
          ' | Partial '+(data.partial||0)+' | None '+(data.none||0)+
          ' | GDPR '+(data.gdpr||0)+' | CCPA '+(data.ccpa||0)+'</div>'
        );
      })
      .fail(function(){
        $('#dwic-map-wrap').html('<div><strong>Stats:</strong> Error loading</div>');
      });
  }
  loadStats();
  setInterval(loadStats,30000);
});