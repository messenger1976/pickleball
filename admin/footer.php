<script type="text/javascript" src="js/admin.js"></script>
<script>
function openSidebar() {
    var sidebar = document.getElementById('mySidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.add('is-open');
    if (overlay) overlay.classList.add('is-open');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    var sidebar = document.getElementById('mySidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.remove('is-open');
    if (overlay) overlay.classList.remove('is-open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSidebar();
});

closeSidebar();
</script>

</body>
