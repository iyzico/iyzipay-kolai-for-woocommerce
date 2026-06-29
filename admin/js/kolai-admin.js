/**
 * The admin-specific JavaScript for the plugin.
 *
 * Provides a small accessible disclosure helper: any
 * `<button data-kolai-toggle="target-id">` shows/hides the element with that id
 * and keeps aria-expanded / the hidden attribute in sync. Used so views can drop
 * inline onclick handlers.
 *
 * @package    Kolai
 * @subpackage Kolai/admin/js
 */

(function ($) {
    'use strict';

    $(document).on('click', '[data-kolai-toggle]', function () {
        var $btn    = $(this);
        var target  = $btn.attr('data-kolai-toggle');
        var $target = target ? $('#' + target) : $();
        if (!$target.length) {
            return;
        }
        var isHidden = $target.prop('hidden');
        $target.prop('hidden', !isHidden);
        $btn.attr('aria-expanded', isHidden ? 'true' : 'false');
    });

})(jQuery);
