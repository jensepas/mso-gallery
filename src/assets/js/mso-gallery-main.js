(function($) {
    'use strict';

    // --- Shared State and Elements ---
    const sharedElements = {
        overlay: null, // Initialized once
        container: null,
        image: null,
        caption: null,
        prev: null,
        next: null,
        close: null,
        loader: null,
        cache: null
    };

    const sharedState = {
        activeGalleryInstance: null, // Reference to the currently active gallery instance
        currentIndex: -1,
        preloadedImages: {}, // Store preloaded images per gallery { galleryId: [img1, img2] }
        totalImagesLoaded: {}, // { galleryId: count }
        isOverlayVisible: false,
        animationDuration: 300 // Default, can be overridden
    };

    /**
     * Represents a single gallery instance on the page.
     */
    class MSOGalleryInstance {
        constructor(element, galleryId, imagesData) {
            this.element = $(element); // The .mso-gallery-thumbnails container
            this.galleryId = galleryId;
            this.images = imagesData || [];
            this.config = {
                animationDuration: sharedState.animationDuration
            };

            // Initialize preloading state for this gallery
            sharedState.preloadedImages[this.galleryId] = [];
            sharedState.totalImagesLoaded[this.galleryId] = 0;

            this.init();
        }

        init() {
            this.generateThumbnails();
            this.preloadImages();
        }

        generateThumbnails() {
            this.images.forEach((img, i) => {
                $('<img>', {
                    src: img.src,
                    alt: img.alt || '',
                    class: 'thumbnail',
                    'data-index': i
                })
                    // Pass 'this' (the instance) to the click handler
                    .on('click', () => this.activateAndShowFullscreen(i))
                    .appendTo(this.element);
            });
        }

        preloadImages() {
            if (!this.images.length) return;

            sharedElements.loader?.show(); // Use shared loader

            const onLoadComplete = () => {
                sharedState.totalImagesLoaded[this.galleryId]++;
                // Check if *all* images *across all* galleries are loaded (simple check)
                // A more robust check might be needed depending on desired UX
                const allLoaded = Object.values(sharedState.totalImagesLoaded).every((count, idx) => {
                    const galleryId = Object.keys(sharedState.totalImagesLoaded)[idx];
                    return count === (sharedState.preloadedImages[galleryId]?.length || 0);
                });

                if (allLoaded) {
                    sharedElements.loader?.hide();
                }
            };

            this.images.forEach((imgData, i) => {
                const img = new Image();
                $(img).on('load error', onLoadComplete);
                img.src = imgData.full;
                img.alt = imgData.alt || '';
                sharedState.preloadedImages[this.galleryId][i] = img;
                sharedElements.cache?.append(img); // Use shared cache
            });
        }

        /**
         * Activates this gallery instance and shows the overlay starting at index.
         * @param {number} index
         */
        activateAndShowFullscreen(index) {
            if (index < 0 || index >= this.images.length) return;

            // Set this instance as the active one
            sharedState.activeGalleryInstance = this;
            sharedState.currentIndex = index;

            // Update and show the shared overlay
            updateSharedOverlay();
            showSharedOverlay();
        }
    } // --- End MSOGalleryInstance Class ---


    // --- Shared Overlay Functions ---

    /**
     * Updates the content of the shared overlay based on the active gallery.
     */
    function updateSharedOverlay() {
        const instance = sharedState.activeGalleryInstance;
        const i = sharedState.currentIndex;

        if (!instance || i < 0 || i >= instance.images.length) return;

        // Use preloaded image if available, otherwise fallback to data
        const preloaded = sharedState.preloadedImages[instance.galleryId]?.[i];
        const imgData = instance.images[i];
        const src = preloaded?.src || imgData?.full; // Prefer preloaded full src
        const alt = preloaded?.alt || imgData?.alt || '';

        sharedElements.image?.attr({ src: src || '', alt: alt });
        sharedElements.caption?.text(alt || 'Aucune description disponible'); // Use shared caption

        updateNavButtons();
    }

    /**
     * Shows the shared overlay with animation.
     */
    function showSharedOverlay() {
        if (!sharedElements.overlay || !sharedElements.container) return;

        sharedElements.container.attr('class', 'slide-out'); // Reset animation class
        sharedElements.overlay.show().addClass('visible');
        sharedState.isOverlayVisible = true;

        // Force reflow
        sharedElements.container[0].offsetHeight;

        sharedElements.container.attr('class', 'slide-in');
    }

    /**
     * Hides the shared overlay with animation.
     */
    function hideSharedOverlay() {
        if (!sharedElements.overlay || !sharedElements.container || !sharedState.isOverlayVisible) return;

        sharedElements.container.attr('class', 'slide-out');

        setTimeout(() => {
            sharedElements.overlay.removeClass('visible').hide();
            sharedState.currentIndex = -1;
            sharedState.activeGalleryInstance = null; // Deactivate instance
            sharedState.isOverlayVisible = false;
        }, sharedState.animationDuration);
    }

    /**
     * Shows the previous image in the active gallery.
     * @param {Event} [e]
     */
    function showPrev(e) {
        e?.stopPropagation();
        const instance = sharedState.activeGalleryInstance;
        if (!instance || instance.images.length <= 1 || !sharedState.isOverlayVisible) return;

        const { container } = sharedElements;
        const { animationDuration } = sharedState;

        container.attr('class', 'slide-out-left');

        setTimeout(() => {
            sharedState.currentIndex = (sharedState.currentIndex - 1 + instance.images.length) % instance.images.length;
            updateSharedOverlay();
            container.attr('class', 'slide-in-from-right');
        }, animationDuration);
    }

    /**
     * Shows the next image in the active gallery.
     * @param {Event} [e]
     */
    function showNext(e) {
        e?.stopPropagation();
        const instance = sharedState.activeGalleryInstance;
        if (!instance || instance.images.length <= 1 || !sharedState.isOverlayVisible) return;

        const { container } = sharedElements;
        const { animationDuration } = sharedState;

        container.attr('class', 'slide-out-right');

        setTimeout(() => {
            sharedState.currentIndex = (sharedState.currentIndex + 1) % instance.images.length;
            updateSharedOverlay();
            container.attr('class', 'slide-in-from-left');
        }, animationDuration);
    }

    /**
     * Updates the state of the shared navigation buttons.
     */
    function updateNavButtons() {
        const instance = sharedState.activeGalleryInstance;
        const singleImage = !instance || instance.images.length <= 1;
        sharedElements.prev?.prop('disabled', singleImage);
        sharedElements.next?.prop('disabled', singleImage);
    }

    /**
     * Handles keypress events for the shared overlay.
     * @param {KeyboardEvent} e
     */
    function handleKeypress(e) {
        if (!sharedState.isOverlayVisible) return;

        switch (e.key) {
            case 'ArrowLeft': showPrev(); break;
            case 'ArrowRight': showNext(); break;
            case 'Escape': hideSharedOverlay(); break;
            default: break;
        }
    }

    /**
     * Binds events to the shared overlay elements.
     */
    function bindSharedEvents() {
        if (!sharedElements.overlay) return; // Don't bind if overlay isn't found

        sharedElements.prev?.on('click', showPrev);
        sharedElements.next?.on('click', showNext);
        sharedElements.close?.on('click', hideSharedOverlay);

        // Click on overlay background to close
        sharedElements.overlay.on('click', e => {
            if (e.target === e.currentTarget) hideSharedOverlay();
        });

        // Keydown listener on the document
        $(document).on('keydown', handleKeypress);
    }

    /**
     * Initializes all gallery instances found on the page.
     */
    function initializeGalleries() {
        // Find shared elements once
        sharedElements.overlay = $('#fullscreen-overlay');
        sharedElements.container = $('#image-container');
        sharedElements.image = $('#fullscreen-image');
        sharedElements.caption = $('#image-caption');
        sharedElements.prev = $('#prev-btn');
        sharedElements.next = $('#next-btn');
        sharedElements.close = $('#close-btn');
        sharedElements.loader = $('#loading-indicator');
        sharedElements.cache = $('#preload-cache');

        // Check if essential overlay parts exist
        if (!sharedElements.overlay.length || !sharedElements.container.length) {
            console.warn('MSO Gallery: Fullscreen overlay elements not found. Aborting initialization.');
            return;
        }

        // Bind events to shared elements once
        bindSharedEvents();

        // Check if gallery data exists
        if (typeof window.MSO_GALLERIES_DATA === 'undefined' || $.isEmptyObject(window.MSO_GALLERIES_DATA)) {
            console.log('MSO Gallery: No gallery data found.');
            return;
        }

        // Find all thumbnail containers and initialize instances
        const galleryInstances = [];
        $('.mso-gallery-thumbnails').each(function() {
            const galleryId = $(this).data('gallery-id');
            const imagesData = window.MSO_GALLERIES_DATA[galleryId];

            if (galleryId && imagesData) {
                galleryInstances.push(new MSOGalleryInstance(this, galleryId, imagesData));
            } else {
                console.warn(`MSO Gallery: Data not found for gallery container with ID: ${galleryId || '(not set)'}`);
            }
        });

        // Optional: Store instances globally if needed for debugging
        window.msoGalleryInstances = galleryInstances;
        console.log(`MSO Gallery: Initialized ${galleryInstances.length} galleries.`);
    }

    // --- Document Ready ---
    $(document).ready(initializeGalleries);

})(jQuery);