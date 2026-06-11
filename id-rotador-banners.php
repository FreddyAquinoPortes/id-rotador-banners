<?php
/**
 * Plugin Name:       ID Rotador de Banners
 * Plugin URI:        https://github.com/FreddyAquinoPortes/id-rotador-banners
 * Description:       Rotador de banners ligero para el Instituto Duartiano. Banners que rotan automáticamente, con enlace opcional (interno o externo) por banner, e importador de sliders antiguos de Slider Revolution.
 * Version:           1.1.1
 * Author:            Instituto Duartiano
 * License:           GPL-2.0-or-later
 * Text Domain:       id-rotador-banners
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IDRB_VERSION', '1.1.1' );
define( 'IDRB_GITHUB_REPO', 'FreddyAquinoPortes/id-rotador-banners' );

/* -------------------------------------------------------------------------
 * Auto-actualización desde GitHub
 *
 * Lee la cabecera "Version:" del archivo principal en la rama main del repo.
 * Si la versión remota es mayor que la instalada, WordPress muestra el botón
 * "Actualizar" normal y descarga el zip de la rama. No requiere releases.
 * ---------------------------------------------------------------------- */

function idrb_remote_version_info() {
	$cache = get_site_transient( 'idrb_remote_version' );
	if ( is_array( $cache ) ) {
		return $cache;
	}

	$raw = wp_remote_get(
		'https://raw.githubusercontent.com/' . IDRB_GITHUB_REPO . '/main/id-rotador-banners.php',
		array( 'timeout' => 10 )
	);

	$info = array( 'version' => '' );
	if ( ! is_wp_error( $raw ) && wp_remote_retrieve_response_code( $raw ) === 200 ) {
		$body = wp_remote_retrieve_body( $raw );
		if ( preg_match( '/^\s*\*\s*Version:\s*([0-9][0-9a-z.\-]*)/mi', $body, $m ) ) {
			$info['version'] = trim( $m[1] );
		}
	}

	// Cachear también los fallos (versión vacía) para no golpear GitHub en cada carga.
	set_site_transient( 'idrb_remote_version', $info, 6 * HOUR_IN_SECONDS );
	return $info;
}

function idrb_package_url() {
	return 'https://github.com/' . IDRB_GITHUB_REPO . '/archive/refs/heads/main.zip';
}

function idrb_check_for_update( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$plugin_file = plugin_basename( __FILE__ );
	$remote      = idrb_remote_version_info();

	if ( $remote['version'] && version_compare( $remote['version'], IDRB_VERSION, '>' ) ) {
		$transient->response[ $plugin_file ] = (object) array(
			'slug'        => 'id-rotador-banners',
			'plugin'      => $plugin_file,
			'new_version' => $remote['version'],
			'url'         => 'https://github.com/' . IDRB_GITHUB_REPO,
			'package'     => idrb_package_url(),
		);
	}

	return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'idrb_check_for_update' );

function idrb_plugins_api( $result, $action, $args ) {
	if ( $action !== 'plugin_information' || empty( $args->slug ) || $args->slug !== 'id-rotador-banners' ) {
		return $result;
	}
	$remote = idrb_remote_version_info();
	return (object) array(
		'name'          => 'ID Rotador de Banners',
		'slug'          => 'id-rotador-banners',
		'version'       => $remote['version'] ?: IDRB_VERSION,
		'author'        => 'Instituto Duartiano',
		'homepage'      => 'https://github.com/' . IDRB_GITHUB_REPO,
		'download_link' => idrb_package_url(),
		'sections'      => array(
			'description' => 'Rotador de banners ligero con enlaces por banner e importador de Slider Revolution. Las actualizaciones se publican en GitHub: ' . IDRB_GITHUB_REPO,
		),
	);
}
add_filter( 'plugins_api', 'idrb_plugins_api', 10, 3 );

/**
 * El zip de GitHub se extrae como "id-rotador-banners-main"; renombrarlo para
 * que WordPress lo instale sobre la carpeta correcta del plugin.
 */
function idrb_fix_source_folder( $source, $remote_source, $upgrader, $hook_extra ) {
	if ( empty( $hook_extra['plugin'] ) || strpos( $hook_extra['plugin'], 'id-rotador-banners' ) === false ) {
		return $source;
	}
	global $wp_filesystem;
	$corrected = trailingslashit( $remote_source ) . 'id-rotador-banners/';
	if ( untrailingslashit( $source ) === untrailingslashit( $corrected ) ) {
		return $source;
	}
	if ( $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $corrected ) ) ) {
		return $corrected;
	}
	return $source;
}
add_filter( 'upgrader_source_selection', 'idrb_fix_source_folder', 10, 4 );

function idrb_clear_update_cache( $upgrader, $hook_extra ) {
	if ( isset( $hook_extra['action'], $hook_extra['type'] ) && $hook_extra['type'] === 'plugin' ) {
		delete_site_transient( 'idrb_remote_version' );
	}
}
add_action( 'upgrader_process_complete', 'idrb_clear_update_cache', 10, 2 );

// "Comprobar de nuevo" en Escritorio → Actualizaciones fuerza una consulta fresca a GitHub.
function idrb_force_check() {
	if ( isset( $_GET['force-check'] ) ) {
		delete_site_transient( 'idrb_remote_version' );
	}
}
add_action( 'load-update-core.php', 'idrb_force_check' );

/* -------------------------------------------------------------------------
 * Tipo de contenido: Banner
 * ---------------------------------------------------------------------- */

function idrb_register_cpt() {
	register_post_type( 'id_banner', array(
		'labels' => array(
			'name'          => 'Banners',
			'singular_name' => 'Banner',
			'add_new_item'  => 'Añadir nuevo banner',
			'edit_item'     => 'Editar banner',
			'menu_name'     => 'Banners',
		),
		'public'          => false,
		'show_ui'         => true,
		'menu_icon'       => 'dashicons-images-alt2',
		'supports'        => array( 'title', 'thumbnail', 'page-attributes' ),
		'hierarchical'    => false,
	) );

	register_taxonomy( 'id_banner_group', 'id_banner', array(
		'labels' => array(
			'name'          => 'Grupos de banners',
			'singular_name' => 'Grupo',
			'menu_name'     => 'Grupos',
		),
		'public'       => false,
		'show_ui'      => true,
		'hierarchical' => true,
	) );
}
add_action( 'init', 'idrb_register_cpt' );

/* -------------------------------------------------------------------------
 * Metabox: enlace del banner
 * ---------------------------------------------------------------------- */

function idrb_add_metabox() {
	add_meta_box( 'idrb_link', 'Enlace del banner', 'idrb_render_metabox', 'id_banner', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'idrb_add_metabox' );

function idrb_render_metabox( $post ) {
	wp_nonce_field( 'idrb_save_link', 'idrb_nonce' );
	$url    = get_post_meta( $post->ID, '_idrb_link_url', true );
	$target = get_post_meta( $post->ID, '_idrb_link_target', true );
	?>
	<p>
		<label for="idrb_link_url"><strong>URL de destino</strong> (déjalo vacío si el banner no debe ser clicable):</label><br>
		<input type="url" id="idrb_link_url" name="idrb_link_url" value="<?php echo esc_attr( $url ); ?>" class="widefat" placeholder="https://...">
	</p>
	<p>
		<label>
			<input type="checkbox" name="idrb_link_target" value="_blank" <?php checked( $target, '_blank' ); ?>>
			Abrir en una pestaña nueva (recomendado para enlaces externos)
		</label>
	</p>
	<?php
}

function idrb_save_metabox( $post_id ) {
	if ( ! isset( $_POST['idrb_nonce'] ) || ! wp_verify_nonce( $_POST['idrb_nonce'], 'idrb_save_link' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_idrb_link_url', esc_url_raw( $_POST['idrb_link_url'] ?? '' ) );
	update_post_meta( $post_id, '_idrb_link_target', ( $_POST['idrb_link_target'] ?? '' ) === '_blank' ? '_blank' : '' );
}
add_action( 'save_post_id_banner', 'idrb_save_metabox' );

/* -------------------------------------------------------------------------
 * Shortcode: [id_banners grupo="" alto="450" intervalo="5000" flechas="1" puntos="1"]
 * ---------------------------------------------------------------------- */

function idrb_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'grupo'     => '',
		'alto'      => '450',
		'intervalo' => '5000',
		'flechas'   => '1',
		'puntos'    => '1',
	), $atts, 'id_banners' );

	$args = array(
		'post_type'      => 'id_banner',
		'posts_per_page' => -1,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	);
	if ( $atts['grupo'] !== '' ) {
		$args['tax_query'] = array( array(
			'taxonomy' => 'id_banner_group',
			'field'    => 'slug',
			'terms'    => sanitize_title( $atts['grupo'] ),
		) );
	}

	$banners = get_posts( $args );
	if ( empty( $banners ) ) {
		return '';
	}

	idrb_enqueue_assets();

	$id   = 'idrb-' . wp_unique_id();
	$alto = max( 100, (int) $atts['alto'] );

	ob_start();
	?>
	<div class="idrb-slider" id="<?php echo esc_attr( $id ); ?>"
		data-interval="<?php echo esc_attr( max( 1000, (int) $atts['intervalo'] ) ); ?>"
		style="height:<?php echo esc_attr( $alto ); ?>px"
		role="region" aria-label="<?php esc_attr_e( 'Carrusel de banners', 'id-rotador-banners' ); ?>">
		<?php foreach ( $banners as $i => $banner ) :
			$img = get_the_post_thumbnail_url( $banner, 'full' );
			if ( ! $img ) {
				continue;
			}
			$url    = get_post_meta( $banner->ID, '_idrb_link_url', true );
			$target = get_post_meta( $banner->ID, '_idrb_link_target', true );
			$style  = 'background-image:url(' . esc_url( $img ) . ')';
			$class  = 'idrb-slide' . ( $i === 0 ? ' idrb-activo' : '' );
			?>
			<?php if ( $url ) : ?>
				<a class="<?php echo esc_attr( $class ); ?>" style="<?php echo esc_attr( $style ); ?>"
					href="<?php echo esc_url( $url ); ?>"
					<?php if ( $target ) : ?>target="_blank" rel="noopener"<?php endif; ?>
					aria-label="<?php echo esc_attr( get_the_title( $banner ) ); ?>"></a>
			<?php else : ?>
				<div class="<?php echo esc_attr( $class ); ?>" style="<?php echo esc_attr( $style ); ?>"
					role="img" aria-label="<?php echo esc_attr( get_the_title( $banner ) ); ?>"></div>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php if ( $atts['flechas'] === '1' ) : ?>
			<button class="idrb-flecha idrb-prev" aria-label="Anterior">&#10094;</button>
			<button class="idrb-flecha idrb-next" aria-label="Siguiente">&#10095;</button>
		<?php endif; ?>
		<?php if ( $atts['puntos'] === '1' ) : ?>
			<div class="idrb-puntos"></div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'id_banners', 'idrb_shortcode' );

/* -------------------------------------------------------------------------
 * CSS y JS (inline, sin dependencias)
 * ---------------------------------------------------------------------- */

function idrb_enqueue_assets() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	add_action( 'wp_footer', 'idrb_print_assets' );
}

function idrb_print_assets() {
	?>
	<style>
	.idrb-slider{position:relative;overflow:hidden;width:100%}
	.idrb-slide{position:absolute;inset:0;display:block;background-size:cover;background-position:center;opacity:0;transition:opacity .8s ease;pointer-events:none}
	.idrb-slide.idrb-activo{opacity:1;pointer-events:auto}
	.idrb-flecha{position:absolute;top:50%;transform:translateY(-50%);z-index:5;background:rgba(0,0,0,.45);color:#fff;border:0;font-size:22px;padding:10px 14px;cursor:pointer;border-radius:4px}
	.idrb-flecha:hover{background:rgba(0,0,0,.7)}
	.idrb-prev{left:12px}.idrb-next{right:12px}
	.idrb-puntos{position:absolute;bottom:14px;left:0;right:0;text-align:center;z-index:5}
	.idrb-punto{display:inline-block;width:11px;height:11px;border-radius:50%;background:rgba(255,255,255,.55);margin:0 5px;cursor:pointer;border:0;padding:0}
	.idrb-punto.idrb-activo{background:#fff}
	</style>
	<script>
	document.querySelectorAll('.idrb-slider').forEach(function(slider){
		var slides=slider.querySelectorAll('.idrb-slide');
		if(slides.length<2)return;
		var actual=0,timer=null,intervalo=parseInt(slider.dataset.interval,10)||5000;
		var puntosWrap=slider.querySelector('.idrb-puntos'),puntos=[];
		if(puntosWrap){
			slides.forEach(function(_,i){
				var p=document.createElement('button');
				p.className='idrb-punto'+(i===0?' idrb-activo':'');
				p.setAttribute('aria-label','Ir al banner '+(i+1));
				p.addEventListener('click',function(){ir(i);});
				puntosWrap.appendChild(p);puntos.push(p);
			});
		}
		function ir(n){
			slides[actual].classList.remove('idrb-activo');
			if(puntos[actual])puntos[actual].classList.remove('idrb-activo');
			actual=(n+slides.length)%slides.length;
			slides[actual].classList.add('idrb-activo');
			if(puntos[actual])puntos[actual].classList.add('idrb-activo');
			reiniciar();
		}
		function reiniciar(){clearInterval(timer);timer=setInterval(function(){ir(actual+1);},intervalo);}
		var prev=slider.querySelector('.idrb-prev'),next=slider.querySelector('.idrb-next');
		if(prev)prev.addEventListener('click',function(){ir(actual-1);});
		if(next)next.addEventListener('click',function(){ir(actual+1);});
		slider.addEventListener('mouseenter',function(){clearInterval(timer);});
		slider.addEventListener('mouseleave',reiniciar);
		reiniciar();
	});
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * Importador desde Slider Revolution (tablas wp_revslider_*)
 * ---------------------------------------------------------------------- */

function idrb_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=id_banner',
		'Importar de Slider Revolution',
		'Importar de Slider Revolution',
		'manage_options',
		'idrb-importar',
		'idrb_render_import_page'
	);
}
add_action( 'admin_menu', 'idrb_admin_menu' );

/**
 * Busca recursivamente en los params de un slide de Slider Revolution
 * la imagen de fondo y el enlace, cubriendo formatos de SR5, SR6 y SR7.
 */
function idrb_extract_from_params( $data, &$image, &$link ) {
	if ( ! is_array( $data ) ) {
		return;
	}
	foreach ( $data as $key => $value ) {
		if ( is_array( $value ) ) {
			idrb_extract_from_params( $value, $image, $link );
			continue;
		}
		if ( ! is_string( $value ) || $value === '' ) {
			continue;
		}
		$key = strtolower( (string) $key );
		if ( ! $image && in_array( $key, array( 'image', 'src', 'imageurl', 'background_image', 'bgimage' ), true )
			&& preg_match( '#^https?://.+\.(jpe?g|png|webp|gif|avif)#i', $value ) ) {
			$image = $value;
		}
		if ( ! $link && in_array( $key, array( 'link', 'slide_link', 'link_slide', 'url', 'image_link', 'jump_to_slide' ), true )
			&& preg_match( '#^https?://#i', $value ) ) {
			$link = $value;
		}
	}
}

function idrb_handle_import() {
	if ( ! isset( $_POST['idrb_import_slider'] ) ) {
		return null;
	}
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'idrb_import' ) ) {
		return null;
	}

	global $wpdb;
	$slider_id = (int) $_POST['idrb_import_slider'];

	$slider = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, title, alias FROM {$wpdb->prefix}revslider_sliders WHERE id = %d", $slider_id
	) );
	if ( ! $slider ) {
		return new WP_Error( 'idrb', 'No se encontró el slider seleccionado.' );
	}

	$slides = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, params, layers, slide_order FROM {$wpdb->prefix}revslider_slides WHERE slider_id = %d ORDER BY slide_order ASC",
		$slider_id
	) );
	if ( empty( $slides ) ) {
		return new WP_Error( 'idrb', 'El slider no tiene diapositivas.' );
	}

	$term = wp_insert_term( $slider->title, 'id_banner_group', array( 'slug' => sanitize_title( $slider->alias ) ) );
	if ( is_wp_error( $term ) && $term->get_error_code() === 'term_exists' ) {
		$term_id = (int) $term->get_error_data();
	} elseif ( is_wp_error( $term ) ) {
		return $term;
	} else {
		$term_id = (int) $term['term_id'];
	}

	$importados = 0;
	foreach ( $slides as $orden => $slide ) {
		$image = '';
		$link  = '';
		foreach ( array( $slide->params, $slide->layers ) as $blob ) {
			$decoded = json_decode( (string) $blob, true );
			if ( is_array( $decoded ) ) {
				idrb_extract_from_params( $decoded, $image, $link );
			}
			// Respaldo por si el JSON no decodifica (formatos serializados antiguos).
			if ( ! $image && preg_match( '#https?://[^"\'\\\\\s]+\.(?:jpe?g|png|webp|gif)#i', (string) $blob, $m ) ) {
				$image = stripslashes( $m[0] );
			}
		}
		if ( ! $image ) {
			continue;
		}

		$post_id = wp_insert_post( array(
			'post_type'   => 'id_banner',
			'post_status' => 'publish',
			'post_title'  => $slider->title . ' — banner ' . ( $orden + 1 ),
			'menu_order'  => (int) $slide->slide_order,
		) );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}

		wp_set_object_terms( $post_id, $term_id, 'id_banner_group' );
		if ( $link ) {
			update_post_meta( $post_id, '_idrb_link_url', esc_url_raw( $link ) );
		}

		// Reusar el adjunto si la imagen ya está en la mediateca; si no, descargarla.
		$attachment_id = attachment_url_to_postid( $image );
		if ( ! $attachment_id ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$attachment_id = media_sideload_image( $image, $post_id, null, 'id' );
		}
		if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
		$importados++;
	}

	return array( 'titulo' => $slider->title, 'alias' => sanitize_title( $slider->alias ), 'importados' => $importados );
}

function idrb_render_import_page() {
	global $wpdb;

	$resultado = idrb_handle_import();

	echo '<div class="wrap"><h1>Importar de Slider Revolution</h1>';

	if ( is_wp_error( $resultado ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $resultado->get_error_message() ) . '</p></div>';
	} elseif ( is_array( $resultado ) ) {
		printf(
			'<div class="notice notice-success"><p>Se importaron <strong>%d</strong> banners del slider «%s». Úsalos con el shortcode: <code>[id_banners grupo="%s"]</code></p></div>',
			(int) $resultado['importados'],
			esc_html( $resultado['titulo'] ),
			esc_attr( $resultado['alias'] )
		);
	}

	$tabla = $wpdb->prefix . 'revslider_sliders';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tabla ) ) !== $tabla ) {
		echo '<p>No se encontraron tablas de Slider Revolution en esta instalación. (Las tablas <code>' . esc_html( $tabla ) . '</code> no existen — quizá el plugin fue desinstalado borrando sus datos.)</p></div>';
		return;
	}

	$sliders = $wpdb->get_results( "SELECT id, title, alias FROM {$tabla} ORDER BY id ASC" );
	if ( empty( $sliders ) ) {
		echo '<p>No hay sliders guardados en Slider Revolution.</p></div>';
		return;
	}

	echo '<p>Selecciona un slider antiguo. Se importará cada diapositiva como un banner (imagen de fondo + enlace, si lo tiene) dentro de un grupo con el nombre del slider. Las capas de texto y animaciones de Slider Revolution no se importan.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'idrb_import' );
	echo '<table class="widefat striped" style="max-width:700px"><thead><tr><th>Slider</th><th>Alias</th><th></th></tr></thead><tbody>';
	foreach ( $sliders as $s ) {
		printf(
			'<tr><td>%s</td><td><code>%s</code></td><td><button class="button button-primary" name="idrb_import_slider" value="%d">Importar</button></td></tr>',
			esc_html( $s->title ),
			esc_html( $s->alias ),
			(int) $s->id
		);
	}
	echo '</tbody></table></form></div>';
}
