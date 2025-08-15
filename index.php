<?php
if (!is_user_logged_in()) {
    $frontend_urls = get_option('trinitykitcms_frontend_app_url');
        if ($frontend_urls) {
        $urls_array = array_map('trim', explode(',', $frontend_urls));
        $main_frontend_url = $urls_array[0];
        wp_redirect($main_frontend_url);
        exit;
    }
}

get_header();
?>

<script src="https://cdn.tailwindcss.com"></script>

<main class="flex flex-col items-center justify-center min-h-screen bg-gray-900">
    <div class="text-center">
        
        <div class="mb-8">
            <img src="<?php echo get_template_directory_uri(); ?>/assets/thetrinityweb.webp" alt="Tiken Logo" class="h-32 mx-auto">
        </div>
        
        <a href="<?php echo esc_url(admin_url()); ?>" class="inline-block px-6 py-3 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 transition duration-300 ease-in-out">
            Acessar Painel Administrativo
        </a>
        <p class="text-gray-400 text-sm my-4">Esta página é visível apenas para usuários logados. <span class="text-white">v1.0.1</span></p>
    </div>
</main>

<?php
get_footer();
?> 