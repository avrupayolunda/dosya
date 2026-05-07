</main>

<footer class="dashboard-footer">
    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['site_name'] ?? 'Admin Panel'); ?>. Tum haklari saklidir.</p>
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script>
function toggleSubmenu(btn) {
    var submenu = btn.nextElementSibling;
    var isOpen = submenu.classList.contains('show');
    
    document.querySelectorAll('.nav-submenu.show').forEach(function(s) {
        if (s !== submenu) {
            s.classList.remove('show');
            s.previousElementSibling.classList.remove('expanded');
        }
    });
    
    if (isOpen) {
        submenu.classList.remove('show');
        btn.classList.remove('expanded');
    } else {
        submenu.classList.add('show');
        btn.classList.add('expanded');
    }
}

document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('show');
    document.getElementById('sidebarOverlay').classList.toggle('show');
});

document.getElementById('sidebarOverlay').addEventListener('click', function() {
    document.getElementById('sidebar').classList.remove('show');
    this.classList.remove('show');
});

document.getElementById('headerUser').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('headerDropdown').classList.toggle('show');
});

document.addEventListener('click', function() {
    document.getElementById('headerDropdown').classList.remove('show');
});
</script>

</body>
</html>
