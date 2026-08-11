    </div><!-- .content -->
  </div><!-- .main -->
</div><!-- .app-shell -->

<script>
// Sidebar toggle mobile
const sidebar   = document.getElementById('sidebar');
const hamburger = document.getElementById('hamburger');
if (hamburger && sidebar) {
    hamburger.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', e => {
        if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    });
}
</script>
</body>
</html>
