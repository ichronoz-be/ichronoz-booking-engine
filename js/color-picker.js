(function($){
  function ensurePicker($input){
    if (!$input.length) return false;
    if (!$input.data('wpWpColorPicker') && typeof $input.wpColorPicker === 'function') {
      try { $input.wpColorPicker(); } catch(e) {}
    }
    return !!$input.data('wpWpColorPicker');
  }

  function attachSwatch($input){
    var $swatch = $input.next('.ichz-color-swatch');
    if (!$swatch.length) {
      $swatch = $('<span/>', {
        class: 'ichz-color-swatch',
        css: {
          display: 'inline-block',
          width: '18px',
          height: '18px',
          marginLeft: '8px',
          verticalAlign: 'middle',
          border: '1px solid #ccd0d4',
          borderRadius: '2px',
          background: $input.val() || '#ffffff'
        },
        title: 'Selected color preview'
      });
      $input.after($swatch);
    }
    return $swatch;
  }

  $(function(){
    // Initialize WP color pickers with swatches
    $('.color-picker').each(function(){
      var $input = $(this);
      var $swatch = attachSwatch($input);
      if (ensurePicker($input)) {
        $input.on('change.ichz-color', function(){
          $swatch.css('background', $input.val() || '#ffffff');
        });
      }
    });

    // Reset All UI Colors button handler
    $(document).on('click', '#ichz-reset-all-ui-colors', function(e){
      e.preventDefault();
      if (!window.confirm('Reset all UI colors to defaults?')) return;
      var defaults = {
        ichronoz_selected_day_color: '#0071c2',
        ichronoz_search_button_color: '#007BFF',
        ichronoz_room_hover_bg_color: '#e6e6e6',
        ichronoz_secondary_color: '#6c757d',
        ichronoz_success_color: '#198754',
        ichronoz_warning_color: '#ffc107',
        ichronoz_link_color: '',
        ichronoz_calendar_range_bg: '#e3f2ff'
      };
      Object.keys(defaults).forEach(function(key){
        var $input = $('input[name="'+key+'"].color-picker');
        if (!$input.length) return;
        var val = defaults[key];
        $input.val(val).trigger('change');
        var $swatch = attachSwatch($input);
        $swatch.css('background', val);
        if (ensurePicker($input)) {
          try { $input.wpColorPicker('color', val); } catch(e) {}
        }
      });
    });

    // Delegated handler for per-field Reset links using data-default-color
    $(document).on('click', 'a.button-link[data-default-color]', function(e){
      e.preventDefault();
      var $a = $(this);
      var val = $a.attr('data-default-color');
      // Find the related input within same cell/row
      var $td = $a.closest('td');
      var $input = $td.find('input.color-picker').first();
      if (!$input.length) {
        $input = $a.closest('tr').find('input.color-picker').first();
      }
      // Fallback: explicit target by name if provided
      if (!$input.length) {
        var targetName = $a.attr('data-target-name');
        if (targetName) {
          $input = $('input[name="'+targetName+'"].color-picker');
        }
      }
      if (!$input.length) return;
      $input.val(val).trigger('change');
      var $swatch = attachSwatch($input);
      $swatch.css('background', val);
      if (ensurePicker($input)) {
        try { $input.wpColorPicker('color', val); } catch(e) {}
      }
    });
  });
})(jQuery);
