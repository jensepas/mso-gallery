/**
 * MSO Gallery Admin JavaScript
 *
 * Handles interactions on the gallery shortcode generator page.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        let mediaFrame;
        const $selectedImagesContainer = $('#mso-gallery-selected-images');
        const $shortcodeOutput = $('#mso-generated-shortcode');
        const $placeholder = $selectedImagesContainer.find('.placeholder');
        let imageIds = []; // Array to store image IDs in order

        // Function to update the shortcode output
        function updateShortcode() {
            if (imageIds.length > 0) {
                $shortcodeOutput.val('[ms_gallery ids="' + imageIds.join(',') + '"]');
                $placeholder.hide();
            } else {
                $shortcodeOutput.val('');
                $placeholder.show();
            }
        }

        // Function to render selected image thumbnails
        function renderThumbnails() {
            $selectedImagesContainer.find('.mso-thumb-wrapper').remove(); // Clear existing thumbs

            imageIds.forEach(id => {
                // Find the attachment details (we need the thumbnail URL)
                // This requires the attachment data to be available, which it should be
                // after selection from the media frame.
                // A more robust way might involve an AJAX call if needed, but let's try this first.
                const attachment = mediaFrame?.state().get('selection').get(id)?.toJSON();
                const thumbUrl = attachment?.sizes?.thumbnail?.url || attachment?.url; // Fallback to full URL

                if (thumbUrl) {
                    const $wrapper = $('<div class="mso-thumb-wrapper">')
                        .attr('data-id', id)
                        .append($('<img>').attr('src', thumbUrl))
                        .append($('<span class="mso-remove-thumb">&times;</span>'));
                    $selectedImagesContainer.append($wrapper);
                }
            });
            updateShortcode(); // Update shortcode after rendering
        }


        // --- Media Library Button ---
        $('#mso-select-images-button').on('click', function(e) {
            e.preventDefault();

            // If the frame already exists, reopen it
            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            // Create a new media frame
            mediaFrame = wp.media({
                title: msoGalleryAdmin.title, // Localized title
                button: {
                    text: msoGalleryAdmin.button // Localized button text
                },
                library: {
                    type: 'image' // Only show images
                },
                multiple: 'add' // Allow multiple selections and adding more later
            });

            // When images are selected, update our list
            mediaFrame.on('select', function() {
                const selection = mediaFrame.state().get('selection');
                const currentIds = new Set(imageIds); // Use Set for efficient adding

                selection.forEach(attachment => {
                    currentIds.add(attachment.id);
                });

                // Preserve order if needed, or just update based on Set
                // For simplicity now, we just rebuild based on the Set
                // To maintain order from selection, you'd need more logic
                imageIds = Array.from(currentIds); // Convert Set back to Array

                renderThumbnails(); // Re-render thumbs
            });

            // Open the frame
            mediaFrame.open();
        });

        // --- Remove Thumbnail Button ---
        $selectedImagesContainer.on('click', '.mso-remove-thumb', function() {
            const $wrapper = $(this).closest('.mso-thumb-wrapper');
            const idToRemove = $wrapper.data('id');

            // Remove the ID from the array
            imageIds = imageIds.filter(id => id !== idToRemove);

            // Remove the thumbnail element
            $wrapper.remove();

            // Update the shortcode
            updateShortcode();
        });

        // --- Make Thumbnails Sortable ---
        $selectedImagesContainer.sortable({
            items: '.mso-thumb-wrapper',
            placeholder: 'ui-sortable-placeholder', // Class for the placeholder
            update: function(event, ui) {
                // Update the imageIds array based on the new order
                imageIds = $selectedImagesContainer.find('.mso-thumb-wrapper').map(function() {
                    return $(this).data('id');
                }).get(); // .get() converts jQuery object to array

                // Update the shortcode
                updateShortcode();
            }
        }).disableSelection(); // Prevent text selection while dragging

    });

})(jQuery);