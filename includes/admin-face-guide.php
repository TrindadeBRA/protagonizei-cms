<?php
if (!defined('ABSPATH')) {
    exit;
}

// Adiciona CSS/JS no admin para sobreposição de guia de rosto no campo de imagem do ACF
add_action('admin_enqueue_scripts', function ($hook_suffix) {
    // Carregar apenas em telas de edição de pedidos
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'orders') {
        return;
    }

    // CSS inline para wrapper com overlay
    $css = '
    .acf-field-image[data-name="child_face_photo"] .acf-image-uploader {
        position: relative;
    }
    .acf-field-image[data-name="child_face_photo"] .acf-image-uploader .face-guide-overlay {
        position: absolute;
        inset: 0;
        pointer-events: none;
        display: none;
        align-items: center;
        justify-content: center;
    }
    .acf-field-image[data-name="child_face_photo"] .acf-image-uploader.has-image .face-guide-overlay {
        display: flex;
    }
    .acf-field-image[data-name="child_face_photo"] .face-guide-overlay svg {
        width: 75%;
        max-width: 480px;
        opacity: 0.6;
    }
    ';
    add_action('admin_head', function() use ($css) {
        echo '<style>'.$css.'</style>';
    });

    // JS para inserir overlay após a imagem renderizada pelo ACF
    $js = '
    (function(){
      function ensureOverlay(container){
        if(!container) return;
        if(container.querySelector(".face-guide-overlay")) return;
        var overlay = document.createElement("div");
        overlay.className = "face-guide-overlay";
        overlay.innerHTML = '
          + JSON.stringify('<svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">\n'+
          '  <defs>\n'+
          '    <radialGradient id="g" cx="50%" cy="40%" r="38%">\n'+
          '      <stop offset="60%" stop-color="#00E5FF" stop-opacity="0.0"/>\n'+
          '      <stop offset="61%" stop-color="#00E5FF" stop-opacity="0.9"/>\n'+
          '      <stop offset="100%" stop-color="#00E5FF" stop-opacity="0.9"/>\n'+
          '    </radialGradient>\n'+
          '  </defs>\n'+
          '  <circle cx="200" cy="170" r="120" fill="url(#g)" stroke="#00E5FF" stroke-width="3"/>\n'+
          '  <line x1="200" y1="30" x2="200" y2="310" stroke="#00E5FF" stroke-opacity="0.35" stroke-width="2" stroke-dasharray="6 8"/>\n'+
          '  <line x1="40" y1="170" x2="360" y2="170" stroke="#00E5FF" stroke-opacity="0.35" stroke-width="2" stroke-dasharray="6 8"/>\n'+
          '  <rect x="5" y="5" width="390" height="390" fill="none" stroke="#00E5FF" stroke-opacity="0.25" stroke-width="2"/>\n'+
          '  <text x="200" y="350" font-size="16" text-anchor="middle" fill="#00E5FF" fill-opacity="0.9">Centralize o rosto dentro do círculo</text>\n'+
          '</svg>') +
          ';
        container.appendChild(overlay);
        container.classList.add("has-image");
      }

      function init(){
        var field = document.querySelector(".acf-field-image[data-name=\\"child_face_photo\\"] .acf-image-uploader");
        if(!field) return;
        ensureOverlay(field);
      }

      if(document.readyState === "loading"){ document.addEventListener("DOMContentLoaded", init); }
      else { init(); }

      // Re-aplicar quando ACF trocar imagem
      document.addEventListener("click", function(e){
        var t = e.target;
        if(!t.closest) return;
        if(t.closest(".acf-field-image[data-name=\\"child_face_photo\\"]")){
          setTimeout(init, 120);
        }
      }, true);
    })();
    ';

    add_action('admin_footer', function() use ($js) {
        echo '<script>'.$js.'</script>';
    });
});


