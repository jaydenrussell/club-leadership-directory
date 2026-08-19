/**
 * Club Leadership Module - Photo Toggle JS
 * Jayden Russell
 * Toggles photo visibility and has-photo class on cards
 */

(function() {
    'use strict';

    function toggleSectionPhotos(section, show) {
        document.querySelectorAll('.clubleaddir-card-photo[data-section="' + section + '"]').forEach(function(photo) {
            photo.classList.toggle('is-visible', show);

            var card = photo.closest('.clubleaddir-card');
            if (card) {
                card.classList.toggle('has-photo', show && photo.querySelector('img') !== null);
            }
        });
    }

    window.SCCClubleaddir = {
        toggleSectionPhotos: toggleSectionPhotos
    };

    function init() {
        toggleSectionPhotos('officers', true);
        toggleSectionPhotos('directors', false);
        toggleSectionPhotos('staff', false);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
