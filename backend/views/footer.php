    </div> <!-- Close container-fluid -->
</div> <!-- Close main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const toggleBtn = document.getElementById('toggleBtn');
    const darkToggle = document.getElementById('darkToggle');
    const darkIcon = document.getElementById('darkIcon');
    const body = document.body;
    const html = document.documentElement;

    // Sidebar Toggle
    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('shifted');
        toggleBtn.classList.toggle('shifted');
        const icon = toggleBtn.querySelector('i');
        icon.classList.toggle('bi-list');
        icon.classList.toggle('bi-x');
    });

    // Dark Mode Toggle
    let isDark = localStorage.getItem('darkMode') === 'true';

    // Apply initial state
    if (isDark) {
        body.classList.add('dark-mode');
        html.setAttribute('data-bs-theme', 'dark');
        if (darkIcon) darkIcon.classList.replace('bi-moon-stars-fill', 'bi-sun-fill');
    }

    // Toggle on click
    if (darkToggle) {
        darkToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            isDark = body.classList.contains('dark-mode');
            html.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
            localStorage.setItem('darkMode', isDark);

            if (darkIcon) {
                if (isDark) {
                    darkIcon.classList.replace('bi-moon-stars-fill', 'bi-sun-fill');
                } else {
                    darkIcon.classList.replace('bi-sun-fill', 'bi-moon-stars-fill');
                }
            }
        });
    }

    // Auto-collapse on small screens
    if (window.innerWidth <= 992) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('shifted');
        toggleBtn.classList.add('shifted');
        toggleBtn.querySelector('i').classList.replace('bi-list', 'bi-x');
    }
</script>
</body>
</html>