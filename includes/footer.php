  </div><!-- /.page-content -->
</div><!-- /.main-wrap -->

<script>
// Hamburger for mobile
const hamburger = document.getElementById('hamburger');
if (hamburger && window.innerWidth <= 768) {
  hamburger.style.display = 'flex';
}
window.addEventListener('resize', () => {
  if (hamburger) hamburger.style.display = window.innerWidth <= 768 ? 'flex' : 'none';
});

// Auto-hide alerts
document.querySelectorAll('.alert[data-auto-hide]').forEach(el => {
  setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 4000);
});
</script>
</body>
</html>
