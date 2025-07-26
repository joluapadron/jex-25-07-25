document.addEventListener('DOMContentLoaded', function() {
    const mobileNavToggle = document.querySelector('.mobile-nav-toggle');
    const navWrapper = document.querySelector('.nav-wrapper');
    const navLinks = document.querySelectorAll('.main-nav a');

    // Función para abrir/cerrar el menú
    mobileNavToggle.addEventListener('click', () => {
        navWrapper.classList.toggle('active');
        // Cambia el icono de hamburguesa a una 'X' y viceversa
        const icon = mobileNavToggle.querySelector('i');
        if (icon.classList.contains('fa-bars')) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-times');
        } else {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    });

    // Cierra el menú al hacer clic en un enlace (útil para páginas de una sola vista)
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (navWrapper.classList.contains('active')) {
                navWrapper.classList.remove('active');
                mobileNavToggle.querySelector('i').classList.remove('fa-times');
                mobileNavToggle.querySelector('i').classList.add('fa-bars');
            }
        });
    });
});