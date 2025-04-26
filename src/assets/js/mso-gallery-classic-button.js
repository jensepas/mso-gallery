/**
 * MSO Gallery Classic Editor Button Script
 *
 * Handles the "Ajouter MSO Gallery" button in the Classic Editor.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Check if the button exists
        const $button = $('#mso-insert-gallery-button');
        if (!$button.length) {
            return;
        }

        let mediaFrame;

        $button.on('click', function(event) {
            event.preventDefault();

            // If the frame already exists, reopen it
            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            // Create a new media frame
            mediaFrame = wp.media({
                title: msoGalleryClassic.title || 'Select Images for MSO Gallery', // Use localized title
                button: {
                    text: msoGalleryClassic.button || 'Insert MSO Gallery Shortcode' // Use localized button text
                },
                library: {
                    type: 'image' // Only show images
                },
                multiple: 'add' // Allow multiple selections and adding to selection
            });

            // When images are selected, run a callback.
            mediaFrame.on('select', function() {
                const selection = mediaFrame.state().get('selection');
                const ids = [];

                if (selection) {
                    // Map the selection to get the IDs
                    selection.map(function(attachment) {
                        ids.push(attachment.id);
                    });
                }

                if (ids.length > 0) {
                    // Build the shortcode string
                    const shortcode = '[mso_gallery ids="' + ids.join(',') + '"]';

                    // Insert the shortcode into the editor
                    // wp.media.editor.insert() is the standard way for classic editor buttons
                    if (typeof wp.media.editor !== 'undefined' && typeof wp.media.editor.insert === 'function') {
                        wp.media.editor.insert(shortcode);
                    } else {
                        // Fallback or alternative insertion method if needed
                        // (e.g., directly manipulating the textarea if wp.media.editor is unavailable)
                        console.error("wp.media.editor.insert is not available.");
                        // You might try inserting into the active editor's content area directly
                        // This depends on whether you're in Visual or Text mode.
                        // Example for TinyMCE (Visual mode):
                        // if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                        //     tinymce.activeEditor.execCommand('mceInsertContent', false, shortcode);
                        // }
                        // Example for Quicktags (Text mode):
                        // if (typeof QTags !== 'undefined') {
                        //     QTags.insertContent(shortcode);
                        // }
                    }
                }
            });

            // Finally, open the modal
            mediaFrame.open();
        });
    });

})(jQuery);