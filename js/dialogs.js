/*
 * Lamixtape dialog handler — vanilla JS, no jQuery.
 *
 * Replaces the Bootstrap 4 modal JS used pre-Phase-4 for the
 * #donatemodal and #contactmodal modals. Uses native <dialog> +
 * showModal() so the browser handles:
 *   - focus trap (focus is auto-confined to the dialog)
 *   - aria-modal=true (auto-applied)
 *   - Escape key close (default <dialog> behavior)
 *   - focus restoration on close (returns focus to the trigger)
 *
 * This script only needs to wire:
 *   - click on [data-lmt-dialog="<id>"] -> open the dialog
 *   - click on .lmt-dialog-close inside a dialog -> close it
 *   - click on the dialog backdrop (outside the content) -> close
 *
 * Event delegation on document so dialogs added later (e.g. via
 * AJAX) still work without re-binding.
 */
( function () {
    'use strict';

    /**
     * Close every currently-open dialog. Used before opening a new
     * one so the top-layer stack stays at depth 1 (e.g. donate ->
     * contact via the "kind words" link inside the donate modal
     * doesn't end up layered).
     */
    function closeAllDialogs() {
        var openDialogs = document.querySelectorAll( 'dialog[open]' );
        for ( var i = 0; i < openDialogs.length; i++ ) {
            openDialogs[ i ].close();
        }
    }

    function openDialogById( id ) {
        var dialog = document.getElementById( id );
        if ( ! dialog || typeof dialog.showModal !== 'function' ) {
            return;
        }
        closeAllDialogs();
        dialog.showModal();
    }

    document.addEventListener( 'click', function ( event ) {
        // Trigger: <a data-lmt-dialog="donatemodal"> or
        // <button data-lmt-dialog="contactmodal">.
        var trigger = event.target.closest( '[data-lmt-dialog]' );
        if ( trigger ) {
            event.preventDefault();
            var id = trigger.getAttribute( 'data-lmt-dialog' );
            if ( id ) {
                openDialogById( id );
            }
            return;
        }

        // Close button inside a dialog.
        var closer = event.target.closest( '.lmt-dialog-close' );
        if ( closer ) {
            event.preventDefault();
            var parentDialog = closer.closest( 'dialog' );
            if ( parentDialog && typeof parentDialog.close === 'function' ) {
                parentDialog.close();
            }
            return;
        }

        // Backdrop click: clicking the <dialog> element directly
        // (not its children) means the click landed on the backdrop.
        // The bounding-rect check is a defensive belt-and-suspenders
        // in case some browser counts a click on the rounded corner
        // or padding as a child target.
        if ( event.target.tagName === 'DIALOG' && event.target.open ) {
            var rect = event.target.getBoundingClientRect();
            var x = event.clientX;
            var y = event.clientY;
            var insideContent = (
                x >= rect.left && x <= rect.right &&
                y >= rect.top  && y <= rect.bottom
            );
            if ( ! insideContent ) {
                event.target.close();
            }
        }
    } );
}() );
