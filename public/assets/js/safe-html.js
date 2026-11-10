(function () {
    'use strict';

    function escape(value) {
        const entities = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
        return String(value ?? '').replace(/[&<>"']/g, character => entities[character]);
    }

    function imageUrl(value) {
        if (typeof value !== 'string' || value.trim() === '') return '';
        try {
            const url = new URL(String(value ?? '').trim(), window.location.origin);
            return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password
                ? url.href
                : '';
        } catch {
            return '';
        }
    }

    window.erpSafeHtml = Object.freeze({ escape, imageUrl });
}());
