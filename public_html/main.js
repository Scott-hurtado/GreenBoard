// public/js/main.js
function toggleSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    sidebar.classList.toggle('active');
    
    if (overlay) {
        overlay.classList.toggle('active');
    }
}