jQuery(document).ready(function($) {
	if (ewww_wvars.webp == 1) {
		$('.batch-image img, .image-wrapper a, .ngg-pro-masonry-item a').each(function() {
			var ewww_attr = $(this).attr('data-webp');
			if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
				$(this).attr('data-src', ewww_attr);
			}
			var ewww_attr = $(this).attr('data-webp-thumbnail');
			if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
				$(this).attr('data-thumbnail', ewww_attr);
			}
		});
		$('.image-wrapper a, .ngg-pro-masonry-item a').each(function() {
			var ewww_attr = $(this).attr('data-webp');
			if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
				$(this).attr('href', ewww_attr);
			}
		});
	}
	$('.ewww_webp').each(function() {
		var ewww_img = document.createElement('img');
		if (ewww_wvars.webp == 1) {
			$(ewww_img).attr('src', $(this).attr('data-webp'));
		} else {
			$(ewww_img).attr('src', $(this).attr('data-img'));
		}
		var ewww_attr = $(this).attr('data-align');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('align', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-alt');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('alt', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-border');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('border', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-crossorigin');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('crossorigin', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-height');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('height', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-hspace');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('hspace', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-ismap');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('ismap', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-longdesc');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('longdesc', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-usemap');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('usemap', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-vspace');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('vspace', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-width');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('width', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-accesskey');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('accesskey', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-class');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('class', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-contenteditable');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('contenteditable', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-contextmenu');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('contextmenu', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-dir');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('dir', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-draggable');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('draggable', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-dropzone');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('dropzone', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-hidden');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('hidden', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-id');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('id', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-lang');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('lang', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-spellcheck');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('spellcheck', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-style');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('style', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-tabindex');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('tabindex', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-title');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('title', ewww_attr);
		}
		var ewww_attr = $(this).attr('data-translate');
		if (typeof ewww_attr !== typeof undefined && ewww_attr !== false) {
			$(ewww_img).attr('translate', ewww_attr);
		}
		$(this).after(ewww_img);
	});
});
/*				if ( $image->getAttribute('align') )
					$nscript->setAttribute('data-align', $image->getAttribute('align'));
				if ( $image->getAttribute('alt') )
					$nscript->setAttribute('data-alt', $image->getAttribute('alt'));
				if ( $image->getAttribute('border') )
					$nscript->setAttribute('data-border', $image->getAttribute('border'));
				if ( $image->getAttribute('crossorigin') )
					$nscript->setAttribute('data-crossorigin', $image->getAttribute('crossorigin'));
				if ( $image->getAttribute('height') )
					$nscript->setAttribute('data-height', $image->getAttribute('height'));
				if ( $image->getAttribute('hspace') )
					$nscript->setAttribute('data-hspace', $image->getAttribute('hspace'));
				if ( $image->getAttribute('ismap') )
					$nscript->setAttribute('data-ismap', $image->getAttribute('ismap'));
				if ( $image->getAttribute('longdesc') )
					$nscript->setAttribute('data-longdesc', $image->getAttribute('longdesc'));
				if ( $image->getAttribute('usemap') )
					$nscript->setAttribute('data-usemap', $image->getAttribute('usemap'));
				if ( $image->getAttribute('vspace') )
					$nscript->setAttribute('data-vspace', $image->getAttribute('vspace'));
				if ( $image->getAttribute('width') )
					$nscript->setAttribute('data-width', $image->getAttribute('width'));
				if ( $image->getAttribute('accesskey') )
					$nscript->setAttribute('data-accesskey', $image->getAttribute('accesskey'));
				if ( $image->getAttribute('class') )
					$nscript->setAttribute('data-class', $image->getAttribute('class'));
				if ( $image->getAttribute('contenteditable') )
					$nscript->setAttribute('data-contenteditable', $image->getAttribute('contenteditable'));
				if ( $image->getAttribute('contextmenu') )
					$nscript->setAttribute('data-contextmenu', $image->getAttribute('contextmenu'));
				if ( $image->getAttribute('dir') )
					$nscript->setAttribute('data-dir', $image->getAttribute('dir'));
				if ( $image->getAttribute('draggable') )
					$nscript->setAttribute('data-draggable', $image->getAttribute('draggable'));
				if ( $image->getAttribute('dropzone') )
					$nscript->setAttribute('data-dropzone', $image->getAttribute('dropzone'));
				if ( $image->getAttribute('hidden') )
					$nscript->setAttribute('data-hidden', $image->getAttribute('hidden'));
				if ( $image->getAttribute('id') )
					$nscript->setAttribute('data-id', $image->getAttribute('id'));
				if ( $image->getAttribute('lang') )
					$nscript->setAttribute('data-lang', $image->getAttribute('lang'));
				if ( $image->getAttribute('spellcheck') )
					$nscript->setAttribute('data-spellcheck', $image->getAttribute('spellcheck'));
				if ( $image->getAttribute('style') )
					$nscript->setAttribute('data-style', $image->getAttribute('style'));
				if ( $image->getAttribute('tabindex') )
					$nscript->setAttribute('data-tabindex', $image->getAttribute('tabindex'));
				if ( $image->getAttribute('title') )
					$nscript->setAttribute('data-title', $image->getAttribute('title'));
				if ( $image->getAttribute('translate') )
					$nscript->setAttribute('data-translate', $image->getAttribute('translate'));*/
