{{-- Wadah notifikasi global (unduhan, update, dsb). Diisi oleh window.karsaToast(). --}}
<div id="karsa-toasts" aria-live="polite" aria-atomic="false"></div>
<script>
    // Notifikasi ringan tanpa dependensi: window.karsaToast('pesan', 'info|success|error', ms)
    window.karsaToast = window.karsaToast || function (message, type = 'info', ms = 6000) {
        const host = document.getElementById('karsa-toasts');
        if (!host) return;
        const icon = type === 'success' ? '✓' : (type === 'error' ? '!' : 'i');
        const el = document.createElement('div');
        el.className = 'karsa-toast karsa-toast-' + type;
        el.setAttribute('role', type === 'error' ? 'alert' : 'status');
        el.innerHTML = '<span class="karsa-toast-icon">' + icon + '</span><span class="karsa-toast-body"></span>';
        el.querySelector('.karsa-toast-body').textContent = message;
        host.appendChild(el);
        if (ms > 0) setTimeout(() => el.remove(), ms);
        return el;
    };
</script>
