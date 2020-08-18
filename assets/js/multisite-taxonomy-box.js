/* jshint curly: false, eqeqeq: false */
/* global ajaxurl */

var multiTagBox, array_multi_unique_noempty;

( function( $ ) {
	var tagDelimiter = ( window.multiTaxL10n && window.multiTaxL10n.tagDelimiter ) || ',';

	// Return an array with any duplicate, whitespace or empty values removed
	array_multi_unique_noempty = function( array ) {
		var out = [];

		$.each( array, function( key, val ) {
			val = $.trim( val );

			if ( val && $.inArray( val, out ) === -1 ) {
				out.push( val );
			}
		} );

		return out;
    };

    // Add tabs functionality
    $( "#multisite-tax-picker" ).tabs().addClass( "ui-tabs-vertical ui-helper-clearfix" );
    $( "#multisite-tax-picker li" ).removeClass( "ui-corner-top" ).addClass( "ui-corner-left" );

	multiTagBox = {
		clean : function( tags ) {
			if ( ',' !== tagDelimiter ) {
				tags = tags.replace( new RegExp( tagDelimiter, 'g' ), ',' );
			}

			tags = tags.replace(/\s*,\s*/g, ',').replace(/,+/g, ',').replace(/[,\s]+$/, '').replace(/^[,\s]+/, '');

			if ( ',' !== tagDelimiter ) {
				tags = tags.replace( /,/g, tagDelimiter );
			}

			return tags;
		},

		parseTags : function(el) {
			var id = el.id,
				num = id.split('-check-num-')[1],
				taxbox = $(el).closest('.multitaxonomydiv'),
				thetags = taxbox.find('.the-multi-taxonomy'),
				current_tags = thetags.val().split( tagDelimiter ),
				new_tags = [];

			delete current_tags[num];

			$.each( current_tags, function( key, val ) {
				val = $.trim( val );
				if ( val ) {
					new_tags.push( val );
				}
			});

			thetags.val( this.clean( new_tags.join( tagDelimiter ) ) );

			this.quickClicks( taxbox );
			return false;
		},

		quickClicks : function( el ) {
			var thetags = $('.the-multi-taxonomy', el),
				tagchecklist = $('.multitaxonomychecklist', el),
				id = $(el).attr('id'),
				current_tags, disabled;

			if ( ! thetags.length )
				return;

			disabled = thetags.prop('disabled');

			current_tags = thetags.val().split( tagDelimiter );
			tagchecklist.empty();

			$.each( current_tags, function( key, val ) {
				var listItem, xbutton;

				val = $.trim( val );

				if ( ! val )
					return;

				// Create a new list item, and ensure the text is properly escaped.
				listItem = $( '<li />' ).text( val );

				// If tags editing isn't disabled, create the X button.
				if ( ! disabled ) {
					/*
					 * Build the X buttons, hide the X icon with aria-hidden and
					 * use visually hidden text for screen readers.
					 */
					xbutton = $( '<button type="button" id="' + id + '-check-num-' + key + '" class="ntmultidelbutton">' +
						'<span class="remove-multi-tag-icon" aria-hidden="true"></span>' +
						'<span class="screen-reader-text">' + window.multiTaxL10n.removeTerm + ' ' + listItem.html() + '</span>' +
						'</button>' );

					xbutton.on( 'click keypress', function( e ) {
						// On click or when using the Enter/Spacebar keys.
						if ( 'click' === e.type || 13 === e.keyCode || 32 === e.keyCode ) {
							/*
							 * When using the keyboard, move focus back to the
							 * add new tag field. Note: when releasing the pressed
							 * key this will fire the `keyup` event on the input.
							 */
							if ( 13 === e.keyCode || 32 === e.keyCode ) {
 								$( this ).closest( '.multitagsdiv' ).find( 'input.newmultiterm' ).focus();
 							}

							multiTagBox.userAction = 'remove';
							multiTagBox.parseTags( this );
						}
					});

					listItem.prepend( '&nbsp;' ).prepend( xbutton );
				}

				// Append the list item to the tag list.
				tagchecklist.append( listItem );
			});
			// The buttons list is built now, give feedback to screen reader users.
			multiTagBox.screenReadersMessage();
		},

		flushTags : function( el, a, f ) {
			var tagsval, newtags, text,
				tags = $( '.the-multi-taxonomy', el ),
				newtag = $( 'input.newmultiterm', el );

			a = a || false;

			text = a ? $(a).text() : newtag.val();

			/*
			 * Return if there's no new tag or if the input field is empty.
			 * Note: when using the keyboard to add tags, focus is moved back to
			 * the input field and the `keyup` event attached on this field will
			 * fire when releasing the pressed key. Checking also for the field
			 * emptiness avoids to set the tags and call quickClicks() again.
			 */
			if ( 'undefined' == typeof( text ) || '' === text ) {
				return false;
			}

			tagsval = tags.val();
			newtags = tagsval ? tagsval + tagDelimiter + text : text;

			newtags = this.clean( newtags );
			newtags = array_multi_unique_noempty( newtags.split( tagDelimiter ) ).join( tagDelimiter );
			tags.val( newtags );
			this.quickClicks( el );

			if ( ! a )
				newtag.val('');
			if ( 'undefined' == typeof( f ) )
				newtag.focus();

			return false;
		},

		get : function( id ) {
            var tax = id.substr( id.indexOf('-') + 1 );

			$.post( ajaxurl, { 'action': 'ajax-get-multisite-term-cloud', 'tax': tax, 'security': mtaxsecurity.noncecloud }, function( r, stat ) {
				if ( 0 === r || 'success' != stat ) {
					return;
				}

				r = $( '<div id="multisite-terms-cloud-' + tax + '" class="the-multisite-terms-cloud">' + r + '</div>' );

				$( 'a', r ).click( function() {
					multiTagBox.userAction = 'add';
					multiTagBox.flushTags( $( '#multi-taxonomy-' + tax ), this );
					return false;
				});

				$( '#' + id ).after( r );
			});
		},

		/**
		 * Track the user's last action.
		 *
		 * @since 4.7.0
		 */
		userAction: '',

		/**
		 * Dispatch an audible message to screen readers.
		 *
		 * @since 4.7.0
		 */
		screenReadersMessage: function() {
			var message;

			switch ( this.userAction ) {
				case 'remove':
					message = window.multiTaxL10n.termRemoved;
					break;

				case 'add':
                    message = window.multiTaxL10n.termAdded;
					break;

				default:
					return;
			}

			window.wp.a11y.speak( message, 'assertive' );
		},

		init : function() {
			var ajaxtag = $('div.ajaxmultitaxonomy');

			$('.multitaxonomydiv').each( function() {
				multiTagBox.quickClicks( this );
			});

			$( '.multitermadd', ajaxtag ).click( function() {
				multiTagBox.userAction = 'add';
				multiTagBox.flushTags( $( this ).closest( '.multitaxonomydiv' ) );
			});

			$( 'input.newmultiterm', ajaxtag ).keypress( function( event ) {
				if ( 13 == event.which ) {
					multiTagBox.userAction = 'add';
					multiTagBox.flushTags( $( this ).closest( '.multitaxonomydiv' ) );
					event.preventDefault();
					event.stopPropagation();
				}
			}).keypress( function( event ) {
				if ( 13 == event.which ) {
					event.preventDefault();
					event.stopPropagation();
				}
			}).each( function( i, element ) {
				$( element ).multiTagsSuggest();
			});

			// save tags on post save/publish
			$('#post').submit(function(){
				$('div.multitaxonomydiv').each( function() {
					multiTagBox.flushTags(this, false, 1);
                });
            });

			// Fetch and toggle the Tag cloud.
			$('.multitaxonomycloud-link').click(function(){
                // On the first click, fetch the tag cloud and insert it in the DOM.
				multiTagBox.get( $( this ).attr( 'id' ) );
				// Update button state, remove previous click event and attach a new one to toggle the cloud.
				$( this )
					.attr( 'aria-expanded', 'true' )
					.unbind()
					.click( function() {
						$( this )
							.attr( 'aria-expanded', 'false' === $( this ).attr( 'aria-expanded' ) ? 'true' : 'false' )
							.siblings( '.the-multisite-terms-cloud' ).toggle();
					});
			});
		}
    };

    // Multiple Taxonomies.
    window.multiTagBox && window.multiTagBox.init();
}( jQuery ));
