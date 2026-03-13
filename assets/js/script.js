const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');

allSideMenu.forEach(item => {
    const li = item.parentElement;

    item.addEventListener('click', function () {
        allSideMenu.forEach(i => {
            i.parentElement.classList.remove('active');
        })
        li.classList.add('active');
    })
});

// TOGGLE SIDEBAR
const menuBar = document.querySelector('#content nav .bx.bx-menu');
const sidebar = document.getElementById('sidebar');

// Sidebar toggle logic with Persistence
if (menuBar && sidebar) {
    menuBar.addEventListener('click', function () {
        sidebar.classList.toggle('hide');
        // Save state
        if (sidebar.classList.contains('hide')) {
            localStorage.setItem('sidebarStart', 'hide');
        } else {
            localStorage.setItem('sidebarStart', 'show');
        }
    });
}

// Adjust sidebar on load and resize
function adjustSidebar() {
    if (!sidebar) return;

    // 1. Mobile Rule: Always hide on small screens (<576px)
    if (window.innerWidth <= 576) {
        sidebar.classList.add('hide');
        sidebar.classList.remove('show');
    } else {
        // 2. Desktop Rule: Respect User Preference
        const savedState = localStorage.getItem('sidebarStart');
        if (savedState === 'hide') {
            sidebar.classList.add('hide');
        } else {
            // Default to SHOW on desktop if no preference is saved
            sidebar.classList.remove('hide');
        }
        sidebar.classList.add('show'); // Ensure transition class is present
    }
}

// Initial check
window.addEventListener('load', function () {
    // Apply saved state logic class-wise
    const savedState = localStorage.getItem('sidebarStart');
    if (savedState === 'hide') {
        sidebar.classList.add('hide');
        // Determine if we need to remove the injected style or just let the class take over
        // The class 'hide' in CSS matches the injected style, so it's seamless.
    }

    // Remove the FOUC fix style block so it doesn't override the toggle class
    const foucFix = document.getElementById('fouc-fix');
    if (foucFix) {
        foucFix.remove();
    }

    adjustSidebar();
});

// Resize check
window.addEventListener('resize', function () {
    if (window.innerWidth <= 576) {
        sidebar.classList.add('hide');
    } else {
        // On resize to desktop, restore user preference
        const savedState = localStorage.getItem('sidebarStart');
        if (savedState === 'hide') {
            sidebar.classList.add('hide');
        } else {
            sidebar.classList.remove('hide');
        }
    }
});

// Arama butonunu toggle etme
const searchButton = document.querySelector('#content nav form .form-input button');
const searchButtonIcon = document.querySelector('#content nav form .form-input button .bx');
const searchForm = document.querySelector('#content nav form');

if (searchButton) {
    searchButton.addEventListener('click', function (e) {
        if (window.innerWidth < 768) {
            e.preventDefault();
            searchForm.classList.toggle('show');
            if (searchForm.classList.contains('show')) {
                searchButtonIcon.classList.replace('bx-search', 'bx-x');
            } else {
                searchButtonIcon.classList.replace('bx-x', 'bx-search');
            }
        }
    })
}

// Dark Mode Switch
// Dark Mode Switch
const switchMode = document.getElementById('switch-mode');

// Load Dark Mode from LocalStorage
if (localStorage.getItem('darkMode') === 'true') {
    document.documentElement.classList.add('dark');
    document.body.classList.add('dark');
    if (switchMode) switchMode.checked = true;
}

if (switchMode) {
    switchMode.addEventListener('change', function () {
        if (this.checked) {
            document.documentElement.classList.add('dark');
            document.body.classList.add('dark');
            localStorage.setItem('darkMode', 'true');
        } else {
            document.documentElement.classList.remove('dark');
            document.body.classList.remove('dark');
            localStorage.setItem('darkMode', 'false');
        }
    })
}

// Notification Menu Toggle
const notif = document.querySelector('.notification');
if (notif) {
    notif.addEventListener('click', function () {
        document.querySelector('.notification-menu').classList.toggle('show');
        document.querySelector('.profile-menu').classList.remove('show'); // Close profile menu if open
    });
}

// Profile Menu Toggle
const profile = document.querySelector('.profile');
if (profile) {
    profile.addEventListener('click', function () {
        document.querySelector('.profile-menu').classList.toggle('show');
        document.querySelector('.notification-menu').classList.remove('show'); // Close notification menu if open
    });
}

// Close menus if clicked outside
window.addEventListener('click', function (e) {
    if (!e.target.closest('.notification') && !e.target.closest('.profile')) {
        const notifMenu = document.querySelector('.notification-menu');
        const profMenu = document.querySelector('.profile-menu');
        if (notifMenu) notifMenu.classList.remove('show');
        if (profMenu) profMenu.classList.remove('show');
    }
});

// Menülerin açılıp kapanması için fonksiyon
function toggleMenu(menuId) {
    var menu = document.getElementById(menuId);
    var allMenus = document.querySelectorAll('.menu');

    // Diğer tüm menüleri kapat
    allMenus.forEach(function (m) {
        if (m !== menu) {
            m.style.display = 'none';
        }
    });

    // Tıklanan menü varsa aç, yoksa kapat
    if (menu.style.display === 'none' || menu.style.display === '') {
        menu.style.display = 'block';
    } else {
        menu.style.display = 'none';
    }
}

// Başlangıçta tüm menüleri kapalı tut
document.addEventListener("DOMContentLoaded", function () {
    var allMenus = document.querySelectorAll('.menu');
    allMenus.forEach(function (menu) {
        menu.style.display = 'none';
    });
});
