(function ($) {
    'use strict';

    function initializeAccessibleHelp() {
        $('.ichz-help').each(function () {
            var $help = $(this);
            var $tip = $help.find('.ichz-tip').first();

            if (!$tip.length) {
                return;
            }

            if (!$tip.attr('id')) {
                $tip.attr('id', 'ichz-help-' + Math.random().toString(36).slice(2, 10));
            }

            $help.attr({
                role: 'button',
                tabindex: '0',
                'aria-describedby': $tip.attr('id')
            });
        });
    }

    function initializeCheckboxActions() {
        $(document).on('click', '[data-ichz-checkbox-action]', function () {
            var action = $(this).attr('data-ichz-checkbox-action');
            var target = $(this).attr('data-ichz-checkbox-target');
            var $inputs = $(target).find('input[type="checkbox"]');

            $inputs.prop('checked', action === 'select');
        });
    }

    function initializeScriptToggles() {
        $('[data-ichz-script-toggle]').each(function () {
            var $toggle = $(this);
            var target = $toggle.attr('data-ichz-script-toggle');
            var $textarea = $(target);

            if (!$textarea.length) {
                return;
            }

            function sync() {
                var enabled = $toggle.prop('checked');
                $textarea.prop('readonly', !enabled).attr('aria-disabled', enabled ? 'false' : 'true');
            }

            $toggle.on('change', sync);
            sync();
        });
    }

    $(function () {
        initializeAccessibleHelp();
        initializeCheckboxActions();
        initializeScriptToggles();
    });
})(jQuery);
