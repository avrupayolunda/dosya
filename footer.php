            </div> <!-- .content-area -->
        </main>
    </div> <!-- .app -->
    <?php if(isset($extra_js)): ?>
    <?php echo $extra_js; ?>
<?php endif; ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <script src="assets/js/panel/main.js"></script>
    
    <script>
    function toggleUserMenu() {
        document.getElementById('userMenu').classList.toggle('show');
    }
    
    document.addEventListener('click', function(e) {
        if(!e.target.closest('.user-card')) {
            document.getElementById('userMenu').classList.remove('show');
        }
    });
    
    document.getElementById('sidebarToggle')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    });
    
    document.getElementById('mobileMenuBtn')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('mobile-open');
        document.getElementById('sidebarOverlay').classList.toggle('show');
    });
    
    document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.remove('mobile-open');
        this.classList.remove('show');
    });
    
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type} show`;
        toast.innerHTML = `
            <div class="toast-icon"><span class="material-icons-round">${type === 'success' ? 'check_circle' : 'error'}</span></div>
            <div class="toast-content"><div class="toast-title">${type === 'success' ? 'Başarılı' : 'Hata'}</div><div class="toast-message">${message}</div></div>
            <button class="toast-close" onclick="this.parentElement.remove()"><span class="material-icons-round">close</span></button>
        `;
        container.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => { if(toast.parentElement) toast.remove(); }, 5000);
    }
    </script>
</body>
</html>