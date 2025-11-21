$(document).ready(function() {
    // Khi click menu
    $('.sidebar .nav-link').on('click', function(e) {
        $('.sidebar .nav-link').removeClass('active');
        $(this).addClass('active');
    });

    // Tự động active theo URL hiện tại
    const currentFile = window.location.pathname.split('/').pop(); // lấy tên file hiện tại

    $('.sidebar .nav-link').each(function() {
        const hrefFile = $(this).attr('href').split('/').pop(); // lấy tên file từ href
        if (hrefFile === currentFile) {
            $(this).addClass('active');
        }
    });
});